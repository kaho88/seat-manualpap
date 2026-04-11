<?php

namespace Seat\ManualPap\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Seat\Eveapi\Models\Character\CharacterInfo;
use Seat\Kassie\Calendar\Models\Operation;
use Seat\Kassie\Calendar\Models\Tag;
use Seat\ManualPap\Models\CharacterBlocklist;
use Seat\ManualPap\Models\CorporationWhitelist;
use Seat\Web\Models\User;

class ManualPapController extends Controller
{
    /**
     * Get whitelisted corporation IDs from the database.
     *
     * @return int[]
     */
    public static function getWhitelistedCorpIds(): array
    {
        return CorporationWhitelist::pluck('corporation_id')->toArray();
    }

    /**
     * Get blocked character IDs from the blocklist.
     *
     * @return int[]
     */
    public static function getBlockedCharIds(): array
    {
        return CharacterBlocklist::pluck('character_id')->toArray();
    }

    // -------------------------------------------------------
    // Single PAP (existing)
    // -------------------------------------------------------

    public function index(): View
    {
        $operations = Operation::orderBy('start_at', 'desc')
            ->limit(100)
            ->get();

        return view('manualpap::index', compact('operations'));
    }

    public function store(): RedirectResponse
    {
        $data = request()->validate([
            'operation_id'  => ['required', 'integer'],
            'character_id'  => ['required', 'integer'],
            'ship_type_id'  => ['nullable', 'integer'],
            'value'         => ['nullable', 'integer', 'min:0'],
        ]);

        $operationId = (int) $data['operation_id'];
        $characterId = (int) $data['character_id'];
        $shipTypeId  = !empty($data['ship_type_id']) ? (int) $data['ship_type_id'] : 0;
        $value       = $data['value'] ?? $this->resolveOperationValue($operationId);

        $this->insertPap($operationId, $characterId, $shipTypeId, $value);

        return redirect()->route('manualpap.index')
            ->with('success', trans('manualpap::manualpap.pap_added', [
                'op'   => $operationId,
                'char' => $characterId,
            ]));
    }

    // -------------------------------------------------------
    // Bulk import
    // -------------------------------------------------------

    public function bulk(): View
    {
        return view('manualpap::bulk');
    }

    public function bulkStore(): RedirectResponse
    {
        $data = request()->validate([
            'character_list' => ['required', 'string'],
            'month'          => ['required', 'integer', 'between:1,12'],
            'year'           => ['required', 'integer', 'min:2020'],
        ]);

        $month = (int) $data['month'];
        $year  = (int) $data['year'];

        // Datum: immer der 28. des gewaehlten Monats
        $date = Carbon::create($year, $month, 28, 0, 0, 0, 'UTC');

        // Build operation title: "Allianz FAT <Monat> <Jahr>"
        $monthName = $date->isoFormat('MMMM');
        $opTitle   = sprintf('Allianz FAT %s %s', ucfirst($monthName), $year);

        // Operation suchen oder erstellen
        $operation = $this->findOrCreateOperation($opTitle, $date);

        // Tag "Allypap" suchen oder erstellen und der Operation zuweisen
        $this->ensureAllypapTag($operation);

        // Character-Liste parsen
        $entries = $this->parseBulkList($data['character_list']);

        $totalInput = count($entries);
        $results = $this->processBulkList($entries, $operation->id, $month, $year);

        return redirect()->route('manualpap.bulk')
            ->with('success', trans('manualpap::manualpap.bulk_result', [
                'total'  => $totalInput,
                'ok'     => $results['ok'],
                'failed' => $results['failed'],
                'op'     => $opTitle,
            ]))
            ->with('merged_names', $results['merged_names'])
            ->with('failed_names', $results['failed_names'])
            ->withInput();
    }

    // -------------------------------------------------------
    // Report
    // -------------------------------------------------------

    public function report(): View
    {
        $month = (int) request('month', now()->month);
        $year  = (int) request('year', now()->year);

        $results = $this->buildReportData($month, $year);

        // Available months for the filter dropdown
        $availableMonths = DB::table('kassie_calendar_paps')
            ->select('month', 'year')
            ->groupBy('month', 'year')
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->get();

        return view('manualpap::report', compact('results', 'month', 'year', 'availableMonths'));
    }

    // -------------------------------------------------------
    // Inactive Report (no PAPs in last 3 months)
    // -------------------------------------------------------

    public function inactive(): View
    {
        $corporationIds = self::getWhitelistedCorpIds();

        $results = $this->buildInactiveData($corporationIds);

        return view('manualpap::inactive', compact('results', 'corporationIds'));
    }

    /**
     * Build a list of main characters from whitelisted corporations
     * who have had ZERO PAPs in the last 3 full calendar months.
     * Uses corporation_members table so ALL corp members are included,
     * not just those registered in SeAT.
     *
     * @param int[] $corporationIds
     */
    public function buildInactiveData(array $corporationIds): array
    {
        if (empty($corporationIds)) {
            return [];
        }

        // Determine the last 3 full months
        $months = [];
        for ($i = 1; $i <= 3; $i++) {
            $date = Carbon::now()->subMonthsNoOverflow($i)->startOfMonth();
            $months[] = ['month' => $date->month, 'year' => $date->year];
        }

        // Step 1: Get ALL character_ids from whitelisted corporations
        $allCorpCharIds = DB::table('corporation_members')
            ->whereIn('corporation_id', $corporationIds)
            ->pluck('character_id')
            ->unique()
            ->values()
            ->toArray();

        if (empty($allCorpCharIds)) {
            return [];
        }

        // Step 2: Resolve each character to their main character
        $resolved = $this->resolveCharactersToMains($allCorpCharIds);

        // Step 3: Find main characters that DO have PAPs in any of the last 3 months
        $activeQuery = DB::table('kassie_calendar_paps');
        foreach ($months as $m) {
            $activeQuery->orWhere(function ($q) use ($m) {
                $q->where('month', $m['month'])->where('year', $m['year']);
            });
        }
        $activeMainIds = $activeQuery
            ->pluck('character_id')
            ->unique()
            ->toArray();

        // Step 4: Build inactive list - unique mains NOT in the active set and NOT on blocklist
        $blockedCharIds = self::getBlockedCharIds();
        $blockedMainIds = $this->resolveBlockedToMains($blockedCharIds);
        $uniqueMains = $resolved->unique('main_char_id');
        $inactive = $uniqueMains->filter(fn($r) => !in_array($r['main_char_id'], $activeMainIds))
            ->filter(fn($r) => !in_array($r['main_char_id'], $blockedMainIds));

        if ($inactive->isEmpty()) {
            return [];
        }

        // Step 5: Build result - all fields come from resolveCharactersToMains
        $results = $inactive->values()->map(fn($r) => [
            'character_name'  => $r['main_char_name'],
            'corporation_name' => $r['corporation_name'],
            'alliance_name'   => $r['alliance_name'],
            'has_token'       => $r['has_token'],
        ])->toArray();

        // Sort: registered users first, then by corp name, then char name
        usort($results, function ($a, $b) {
            // Registered (has_token) first
            if ($a['has_token'] !== $b['has_token']) {
                return $b['has_token'] ? 1 : -1;
            }
            $cmp = strcasecmp($a['corporation_name'], $b['corporation_name']);
            if ($cmp !== 0) return $cmp;
            return strcasecmp($a['character_name'], $b['character_name']);
        });

        return $results;
    }

    /**
     * Build aggregated report: main character -> total PAPs for a given month/year.
     * Uses SUM(value) so bulk-imported FAT counts are reflected correctly.
     * Includes ALL whitelisted corporation members (via corporation_members table)
     * with 0 FATs, even if not registered in SeAT.
     * Returns corporation, alliance and token status for each character.
     */
    public function buildReportData(int $month, int $year): array
    {
        // Step 1: Get PAP value sums per character_id
        $paps = DB::table('kassie_calendar_paps')
            ->where('month', $month)
            ->where('year', $year)
            ->select('character_id', DB::raw('SUM(value) as total'))
            ->groupBy('character_id')
            ->pluck('total', 'character_id')
            ->toArray();

        // Step 2: Aggregate PAPs by main character
        $aggregated = [];
        if (!empty($paps)) {
            $charToUser = DB::table('refresh_tokens')
                ->whereIn('character_id', array_keys($paps))
                ->pluck('user_id', 'character_id')
                ->toArray();

            $userIds = array_unique(array_values($charToUser));
            $users = User::whereIn('id', $userIds)->get()->keyBy('id');

            $allUserChars = DB::table('refresh_tokens')
                ->whereIn('user_id', $userIds)
                ->get()
                ->groupBy('user_id')
                ->map(fn($items) => $items->pluck('character_id')->toArray());

            $userToMainChar = [];
            foreach ($users as $userId => $user) {
                $userToMainChar[$userId] = $user->main_character_id
                    ?: ($allUserChars[$userId][0] ?? null);
            }

            foreach ($paps as $characterId => $total) {
                $userId = $charToUser[$characterId] ?? null;

                if (!$userId) {
                    $mainCharId = (int) $characterId;
                } else {
                    $mainCharId = (int) ($userToMainChar[$userId] ?? $characterId);
                }

                if (!isset($aggregated[$mainCharId])) {
                    $aggregated[$mainCharId] = 0;
                }
                $aggregated[$mainCharId] += (int) $total;
            }
        }

        // Step 3: Resolve ALL whitelisted corporation members with metadata
        $corpIds = self::getWhitelistedCorpIds();
        $metaMap = []; // main_char_id => metadata from resolver

        if (!empty($corpIds)) {
            $allCorpCharIds = DB::table('corporation_members')
                ->whereIn('corporation_id', $corpIds)
                ->pluck('character_id')
                ->unique()
                ->values()
                ->toArray();

            $resolved = $this->resolveCharactersToMains($allCorpCharIds);

            foreach ($resolved as $r) {
                $mainCharId = $r['main_char_id'];
                if (!isset($aggregated[$mainCharId])) {
                    $aggregated[$mainCharId] = 0;
                }
                // Store metadata (first occurrence per main wins)
                if (!isset($metaMap[$mainCharId])) {
                    $metaMap[$mainCharId] = $r;
                }
            }
        }

        if (empty($aggregated)) {
            return [];
        }

        // Step 4: Load character names for any main_char_ids not in metaMap
        $missingIds = array_diff(array_keys($aggregated), array_keys($metaMap));
        $charNames = [];
        if (!empty($missingIds)) {
            $charNames = DB::table('character_infos')
                ->whereIn('character_id', $missingIds)
                ->pluck('name', 'character_id')
                ->toArray();
        }

        // Step 5: Build final result array
        $results = [];
        foreach ($aggregated as $charId => $total) {
            $meta = $metaMap[$charId] ?? null;
            if ($meta) {
                $results[] = [
                    'character_name'   => $meta['main_char_name'],
                    'corporation_name' => $meta['corporation_name'],
                    'alliance_name'    => $meta['alliance_name'],
                    'has_token'        => $meta['has_token'],
                    'total_paps'       => $total,
                ];
            } else {
                $results[] = [
                    'character_name'   => $charNames[$charId] ?? ('Unknown #' . $charId),
                    'corporation_name' => null,
                    'alliance_name'    => null,
                    'has_token'        => false,
                    'total_paps'       => $total,
                ];
            }
        }

        // Sort: registered first, then by FATs descending
        usort($results, function ($a, $b) {
            if ($a['has_token'] !== $b['has_token']) {
                return $b['has_token'] ? 1 : -1;
            }
            return $b['total_paps'] <=> $a['total_paps'];
        });

        return $results;
    }

    // -------------------------------------------------------
    // Corporation member helpers
    // -------------------------------------------------------

    /**
     * Resolve a batch of character_ids to their main characters.
     * Uses corporation_members to get corporation_id, and refresh_tokens/users
     * to resolve main characters. Characters not registered in SeAT keep their
     * own character_id as the "main".
     *
     * @param int[] $characterIds
     * @return \Illuminate\Support\Collection  each item: ['char_id', 'main_char_id', 'main_char_name', 'corporation_id', 'corporation_name', 'alliance_name', 'has_token']
     */
    protected function resolveCharactersToMains(array $characterIds): \Illuminate\Support\Collection
    {
        // Get corporation_id for each character from corporation_members
        $charToCorp = DB::table('corporation_members')
            ->whereIn('character_id', $characterIds)
            ->pluck('corporation_id', 'character_id')
            ->toArray();

        // Try to resolve character_id -> user_id via refresh_tokens
        $charToUser = DB::table('refresh_tokens')
            ->whereIn('character_id', $characterIds)
            ->pluck('user_id', 'character_id')
            ->toArray();

        // Batch resolve user_id -> main_character_id
        $userToMainChar = [];
        if (!empty($charToUser)) {
            $userIds = array_unique(array_values($charToUser));
            $users = User::whereIn('id', $userIds)->get()->keyBy('id');

            $allUserChars = DB::table('refresh_tokens')
                ->whereIn('user_id', $userIds)
                ->get()
                ->groupBy('user_id')
                ->map(fn($items) => $items->pluck('character_id')->toArray());

            foreach ($users as $userId => $user) {
                $userToMainChar[$userId] = $user->main_character_id
                    ?: ($allUserChars[$userId][0] ?? null);
            }
        }

        // Collect all unique main_char_ids and original char_ids for name lookups
        $allMainCharIds = [];
        foreach ($charToUser as $charId => $userId) {
            $allMainCharIds[] = (int) ($userToMainChar[$userId] ?? $charId);
        }
        // Also include char_ids that have no user (they become their own "main")
        foreach ($characterIds as $charId) {
            if (!isset($charToUser[$charId])) {
                $allMainCharIds[] = (int) $charId;
            }
        }
        $allMainCharIds = array_unique($allMainCharIds);

        // Load main character names
        $charNames = DB::table('character_infos')
            ->whereIn('character_id', $allMainCharIds)
            ->pluck('name', 'character_id')
            ->toArray();

        // Load corporation names and alliance_ids from corporation_infos
        $corpIds = array_unique(array_values($charToCorp));
        $corpRows = DB::table('corporation_infos')
            ->whereIn('corporation_id', $corpIds)
            ->select('corporation_id', 'name', 'alliance_id')
            ->get();

        $corpNames = $corpRows->pluck('name', 'corporation_id')->toArray();
        $corpToAlliance = $corpRows->pluck('alliance_id', 'corporation_id')->toArray();

        // Load alliance names from alliances table
        $allianceIds = array_unique(array_filter($corpToAlliance));
        $allianceNames = [];
        if (!empty($allianceIds)) {
            $allianceNames = DB::table('alliances')
                ->whereIn('alliance_id', $allianceIds)
                ->pluck('name', 'alliance_id')
                ->toArray();
        }

        // Build resolved collection
        $results = collect();
        foreach ($characterIds as $charId) {
            $userId = $charToUser[$charId] ?? null;
            $mainCharId = $userId
                ? ((int) ($userToMainChar[$userId] ?? $charId))
                : (int) $charId;

            $corpId = (int) ($charToCorp[$charId] ?? 0);
            $allianceId = $corpToAlliance[$corpId] ?? null;

            $results->push([
                'char_id'         => (int) $charId,
                'main_char_id'    => $mainCharId,
                'main_char_name'  => $charNames[$mainCharId] ?? ('Unknown #' . $mainCharId),
                'corporation_id'  => $corpId,
                'corporation_name' => $corpNames[$corpId] ?? ('Unknown Corp #' . $corpId),
                'alliance_name'   => $allianceId ? ($allianceNames[$allianceId] ?? null) : null,
                'has_token'       => $userId !== null,
            ]);
        }

        return $results;
    }

    /**
     * Resolve a list of blocked character_ids (which may be alts) to their main character IDs.
     * Characters not registered in SeAT are returned as-is (they are their own "main").
     *
     * @param int[] $characterIds
     * @return int[]  unique main character IDs
     */
    protected function resolveBlockedToMains(array $characterIds): array
    {
        if (empty($characterIds)) {
            return [];
        }

        $charToUser = DB::table('refresh_tokens')
            ->whereIn('character_id', $characterIds)
            ->pluck('user_id', 'character_id')
            ->toArray();

        $userIds = array_unique(array_values($charToUser));
        $userToMainChar = [];
        if (!empty($userIds)) {
            $users = User::whereIn('id', $userIds)->get()->keyBy('id');

            $allUserChars = DB::table('refresh_tokens')
                ->whereIn('user_id', $userIds)
                ->get()
                ->groupBy('user_id')
                ->map(fn($items) => $items->pluck('character_id')->toArray());

            foreach ($users as $userId => $user) {
                $userToMainChar[$userId] = $user->main_character_id
                    ?: ($allUserChars[$userId][0] ?? null);
            }
        }

        $mainIds = [];
        foreach ($characterIds as $charId) {
            $userId = $charToUser[$charId] ?? null;
            $mainIds[] = $userId
                ? (int) ($userToMainChar[$userId] ?? $charId)
                : (int) $charId;
        }

        return array_unique($mainIds);
    }

    // -------------------------------------------------------
    // Bulk helpers
    // -------------------------------------------------------

    /**
     * Parse the bulk list into [name => fat_count] pairs.
     * Format: "CharacterName\t13" or just "CharacterName" (defaults to 1).
     * Entries with 0 FATs are skipped.
     *
     * @return array<string, int>  name => fat_count
     */
    protected function parseBulkList(string $rawList): array
    {
        $entries = [];

        $lines = explode("\n", $rawList);

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            // Split by tab or multiple whitespace
            $parts = preg_split('/[\t]+/', $line);

            $name = trim($parts[0] ?? '');

            if ($name === '') {
                continue;
            }

            // Parse the count from the second column
            $count = 1;
            if (isset($parts[1])) {
                $parsed = (int) trim($parts[1]);
                if ($parsed > 0) {
                    $count = $parsed;
                } elseif ($parsed === 0) {
                    continue; // skip entries with 0 FATs
                }
            }

            $entries[$name] = $count;
        }

        return $entries;
    }

    /**
     * Process parsed entries: resolve each name to main character and insert PAPs.
     * If a main character already has a PAP, the FAT count is added to the existing value.
     *
     * @param array<string, int> $entries  name => fat_count
     */
    protected function processBulkList(array $entries, int $operationId, int $month, int $year): array
    {
        $ok = 0;
        $failed = 0;
        $failedNames = [];
        $mergedNames = [];

        // Bereits vorhandene character_ids und deren value fuer diese Operation laden
        $existingPaps = DB::table('kassie_calendar_paps')
            ->where('operation_id', $operationId)
            ->pluck('value', 'character_id')
            ->toArray();

        foreach ($entries as $name => $fatCount) {
            $mainCharId = $this->resolveNameToMainCharacter($name);

            if (!$mainCharId) {
                $failed++;
                $failedNames[] = $name;
                continue;
            }

            // Wenn bereits ein PAP fuer diesen Haupt-Character existiert: FATs addieren
            if (array_key_exists($mainCharId, $existingPaps)) {
                $newValue = (int) $existingPaps[$mainCharId] + $fatCount;

                DB::table('kassie_calendar_paps')
                    ->where('operation_id', $operationId)
                    ->where('character_id', $mainCharId)
                    ->update(['value' => $newValue]);

                $existingPaps[$mainCharId] = $newValue;
                $mergedNames[] = $name . ' (+' . $fatCount . ')';
                $ok++;
                continue;
            }

            $this->insertPapForMonth($operationId, $mainCharId, 0, $fatCount, $month, $year);
            $existingPaps[$mainCharId] = $fatCount;
            $ok++;
        }

        return [
            'ok'            => $ok,
            'failed'        => $failed,
            'failed_names'  => $failedNames,
            'merged_names'  => $mergedNames,
        ];
    }

    /**
     * Resolve a character name to the main character ID of the owning user.
     */
    protected function resolveNameToMainCharacter(string $characterName): ?int
    {
        $characterId = CharacterInfo::where('name', $characterName)->value('character_id');

        if (!$characterId) {
            return null;
        }

        $userId = DB::table('refresh_tokens')
            ->where('character_id', $characterId)
            ->value('user_id');

        if (!$userId) {
            return null;
        }

        $user = User::find($userId);

        if ($user && $user->main_character_id) {
            return (int) $user->main_character_id;
        }

        return (int) $characterId;
    }

    /**
     * Find or create an operation with the given title on the given date.
     */
    protected function findOrCreateOperation(string $title, Carbon $date): Operation
    {
        $operation = Operation::where('title', $title)->first();

        if ($operation) {
            return $operation;
        }

        $operation = new Operation;
        $operation->title      = $title;
        $operation->user_id    = auth()->id();
        $operation->start_at   = $date->copy()->startOfDay();
        $operation->end_at     = $date->copy()->endOfDay();
        $operation->importance = '5';
        $operation->save();

        return $operation;
    }

    /**
     * Ensure the "Allypap" tag exists and is attached to the operation.
     */
    protected function ensureAllypapTag(Operation $operation): void
    {
        $tag = Tag::where('name', 'Allypap')->first();

        if (!$tag) {
            $tag = Tag::create([
                'name'       => 'Allypap',
                'quantifier' => 1,
                'bg_color'   => '#28a745',
                'text_color' => '#ffffff',
                'order'      => 100,
            ]);
        }

        if (!$operation->tags()->where('calendar_tags.id', $tag->id)->exists()) {
            $operation->tags()->attach($tag->id);
        }
    }

    // -------------------------------------------------------
    // Shared helpers
    // -------------------------------------------------------

    protected function resolveOperationValue(int $operationId): int
    {
        $operation = Operation::with('tags')->find($operationId);

        if ($operation && $operation->tags->count() > 0) {
            return (int) $operation->tags->max('quantifier');
        }

        return 0;
    }

    protected function insertPap(int $operationId, int $characterId, int $shipTypeId, int $value): void
    {
        $now = Carbon::now('UTC');

        DB::table('kassie_calendar_paps')->upsert(
            [[
                'operation_id' => $operationId,
                'character_id' => $characterId,
                'ship_type_id' => $shipTypeId,
                'join_time'    => $now->toDateTimeString(),
                'value'        => $value,
                'week'         => $now->weekOfMonth,
                'month'        => $now->month,
                'year'         => $now->year,
            ]],
            ['operation_id', 'character_id'],
            ['ship_type_id', 'join_time', 'value', 'week', 'month', 'year']
        );
    }

    /**
     * Insert a PAP with a specific month/year (used by bulk import).
     * Uses the 28th of the month for join_time and derives week/month/year from it.
     */
    protected function insertPapForMonth(int $operationId, int $characterId, int $shipTypeId, int $value, int $month, int $year): void
    {
        $joinTime = Carbon::create($year, $month, 28, 12, 0, 0, 'UTC');

        DB::table('kassie_calendar_paps')->insert(
            [[
                'operation_id' => $operationId,
                'character_id' => $characterId,
                'ship_type_id' => $shipTypeId,
                'join_time'    => $joinTime->toDateTimeString(),
                'value'        => $value,
                'week'         => $joinTime->weekOfMonth,
                'month'        => $joinTime->month,
                'year'         => $joinTime->year,
            ]]
        );
    }
}
