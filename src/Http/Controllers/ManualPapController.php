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
            'date'           => ['required', 'date'],
        ]);

        $date = Carbon::parse($data['date'], 'UTC');

        // Build operation title: "Allianz FAT <Monat> <Jahr>"
        $monthName = $date->isoFormat('MMMM');   // lokalisiertes Monatsname
        $opTitle   = sprintf('Allianz FAT %s %s', ucfirst($monthName), $date->year);

        // Operation suchen oder erstellen
        $operation = $this->findOrCreateOperation($opTitle, $date);

        // Tag "Allypap" suchen oder erstellen und der Operation zuweisen
        $this->ensureAllypapTag($operation);

        // Value aus dem Tag-Quantifier holen
        $value = $this->resolveOperationValue($operation->id);

        // Character-Liste parsen (ein Name pro Zeile)
        $names = array_filter(
            array_map('trim', explode("\n", $data['character_list'])),
            fn($name) => strlen($name) > 0
        );

        $results = $this->processBulkList($names, $operation->id, $value);

        return redirect()->route('manualpap.bulk')
            ->with('success', trans('manualpap::manualpap.bulk_result', [
                'total'  => count($names),
                'ok'     => $results['ok'],
                'failed' => $results['failed'],
                'op'     => $opTitle,
            ]))
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
     * Uses batched queries for performance.
     */
    public function buildReportData(int $month, int $year): array
    {
        // Step 1: Get PAP counts per character_id
        $paps = DB::table('kassie_calendar_paps')
            ->where('month', $month)
            ->where('year', $year)
            ->select('character_id', DB::raw('COUNT(*) as total'))
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
            $charIds = $allUserChars[$userId] ?? [];
            $mainCharId = CharacterInfo::where('name', $user->name)
                ->whereIn('character_id', $charIds)
                ->value('character_id');
            $userToMainChar[$userId] = $mainCharId ?: ($charIds[0] ?? null);
        }

        // Step 4: Aggregate PAPs by main character
        $aggregated = [];
        foreach ($paps as $characterId => $total) {
            $userId = $charToUser[$characterId] ?? null;

            if (!$userId) {
                // Character not registered in SeAT - still count it
                $mainCharId = (int) $characterId;
            } else {
                $mainCharId = (int) ($userToMainChar[$userId] ?? $characterId);
            }

            if (!isset($aggregated[$mainCharId])) {
                $aggregated[$mainCharId] = 0;
            }
            $aggregated[$mainCharId] += $total;
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

        // Sort by total PAPs descending
        usort($results, fn($a, $b) => $b['total_paps'] <=> $a['total_paps']);

        return $results;
    }

    // -------------------------------------------------------
    // Bulk helpers
    // -------------------------------------------------------

    /**
     * Process a list of character names: resolve to main character and insert PAPs.
     */
    protected function processBulkList(array $names, int $operationId, int $value): array
    {
        $ok = 0;
        $failed = 0;
        $failedNames = [];

        foreach ($names as $name) {
            $mainCharId = $this->resolveNameToMainCharacter($name);

            if (!$mainCharId) {
                $failed++;
                $failedNames[] = $name;
                continue;
            }

            $this->insertPap($operationId, $mainCharId, 0, $value);
            $ok++;
        }

        return [
            'ok'           => $ok,
            'failed'       => $failed,
            'failed_names' => $failedNames,
        ];
    }

    /**
     * Resolve a character name to the main character ID of the owning user.
     *
     * Flow: character name -> character_infos.character_id
     *       -> refresh_tokens.user_id -> User
     *       -> main character (user name matches character name convention)
     */
    protected function resolveNameToMainCharacter(string $characterName): ?int
    {
        // 1. Find character_id by name
        $characterId = CharacterInfo::where('name', $characterName)->value('character_id');

        if (!$characterId) {
            return null;
        }

        // 2. Find the user who owns this character via refresh_tokens
        $userId = DB::table('refresh_tokens')
            ->where('character_id', $characterId)
            ->value('user_id');

        if (!$userId) {
            // Character exists in EVE but is not registered in SeAT
            return null;
        }

        // 3. Get all character IDs for this user
        $userCharacterIds = DB::table('refresh_tokens')
            ->where('user_id', $userId)
            ->pluck('character_id')
            ->toArray();

        // 4. Resolve main character:
        //    In SeAT the user's name is typically their main character's name.
        $user = User::find($userId);
        $mainCharId = CharacterInfo::where('name', $user->name)
            ->whereIn('character_id', $userCharacterIds)
            ->value('character_id');

        if ($mainCharId) {
            return (int) $mainCharId;
        }

        // Fallback: use the original character
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

        return Operation::create([
            'title'          => $title,
            'user_id'        => auth()->id(),
            'start_at'       => $date->copy()->startOfDay(),
            'end_at'         => $date->copy()->endOfDay(),
            'importance'     => 'full',
            'description'    => 'Auto-created by Manual PAP bulk import',
        ]);
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

        // Attach tag if not already attached
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
}
