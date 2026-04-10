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
        $monthName = $date->isoFormat('MMMM');
        $opTitle   = sprintf('Allianz FAT %s %s', ucfirst($monthName), $date->year);

        // Operation suchen oder erstellen
        $operation = $this->findOrCreateOperation($opTitle, $date);

        // Tag "Allypap" suchen oder erstellen und der Operation zuweisen
        $this->ensureAllypapTag($operation);

        // Character-Liste parsen
        // Format: "Name\tAnzahl" pro Zeile, z.B. "BennyMar    13"
        // Ohne Tab/Anzahl: Name ohne Anzahl -> FAT-Wert = 1
        $entries = $this->parseBulkList($data['character_list']);

        $totalInput = count($entries);
        $results = $this->processBulkList($entries, $operation->id);

        return redirect()->route('manualpap.bulk')
            ->with('success', trans('manualpap::manualpap.bulk_result', [
                'total'  => $totalInput,
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
            // Use main_character_id directly from user model
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

        // Sort by total PAPs descending
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

            // Split by tab or multiple whitespace (handles tab-separated and space-padded)
            $parts = preg_split('/[\t]+/', $line);

            $name = trim($parts[0] ?? '');

            if ($name === '') {
                continue;
            }

            // Parse the count from the second column
            $count = 1; // default
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
     *
     * @param array<string, int> $entries  name => fat_count
     */
    protected function processBulkList(array $entries, int $operationId): array
    {
        $ok = 0;
        $failed = 0;
        $failedNames = [];

        foreach ($entries as $name => $fatCount) {
            $mainCharId = $this->resolveNameToMainCharacter($name);

            if (!$mainCharId) {
                $failed++;
                $failedNames[] = $name;
                continue;
            }

            $this->insertPap($operationId, $mainCharId, 0, $fatCount);
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
            return null;
        }

        // 3. Use main_character_id from user model directly
        $user = User::find($userId);

        if ($user && $user->main_character_id) {
            return (int) $user->main_character_id;
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

        // Direkte Attribut-Zuweisung umgeht $fillable von Operation-Model
        // Orientiert an bestehender Operation (id=6): importance=2, user_id gesetzt
        $operation = new Operation;
        $operation->title      = $title;
        $operation->user_id    = auth()->id();
        $operation->start_at   = $date->copy()->startOfDay();
        $operation->end_at     = $date->copy()->endOfDay();
        $operation->importance = '2';
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
}
