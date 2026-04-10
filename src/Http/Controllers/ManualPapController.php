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
use Seat\Web\Models\User;

class ManualPapController extends Controller
{
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

    /**
     * Build aggregated report: main character -> total PAPs for a given month/year.
     * Uses SUM(value) so bulk-imported FAT counts are reflected correctly.
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

        if (empty($paps)) {
            return [];
        }

        // Step 2: Batch resolve character_id -> user_id
        $charToUser = DB::table('refresh_tokens')
            ->whereIn('character_id', array_keys($paps))
            ->pluck('user_id', 'character_id')
            ->toArray();

        // Step 3: Batch resolve user_id -> main character_id
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

        // Step 4: Aggregate PAPs by main character
        $aggregated = [];
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

        // Step 5: Batch load character names
        $charNames = CharacterInfo::whereIn('character_id', array_keys($aggregated))
            ->pluck('name', 'character_id')
            ->toArray();

        // Step 6: Build final result array
        $results = [];
        foreach ($aggregated as $charId => $total) {
            $results[] = [
                'character_id'   => $charId,
                'character_name' => $charNames[$charId] ?? ('Unknown #' . $charId),
                'total_paps'     => $total,
            ];
        }

        usort($results, fn($a, $b) => $b['total_paps'] <=> $a['total_paps']);

        return $results;
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
