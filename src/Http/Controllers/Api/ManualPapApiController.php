<?php

namespace Seat\ManualPap\Http\Controllers\Api;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Seat\Kassie\Calendar\Models\Operation;
use Seat\ManualPap\Http\Controllers\ManualPapController;

class ManualPapApiController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        if (!$this->validateToken($request)) {
            return response()->json([
                'error' => 'Unauthorized. Provide a valid X-Token header.',
            ], 401);
        }

        $data = $request->validate([
            'operation_id'  => ['required', 'integer'],
            'character_id'  => ['required', 'integer'],
            'ship_type_id'  => ['nullable', 'integer'],
            'value'         => ['nullable', 'integer', 'min:0'],
        ]);

        $operationId = (int) $data['operation_id'];
        $characterId = (int) $data['character_id'];
        $shipTypeId  = !empty($data['ship_type_id']) ? (int) $data['ship_type_id'] : 0;
        $value       = $data['value'] ?? $this->resolveOperationValue($operationId);

        $now = Carbon::now('UTC');

        DB::table('kassie_calendar_paps')->upsert(
            [[
                'operation_id' => $operationId,
                'character_id' => $characterId,
                'ship_type_id' => $shipTypeId,
                'join_time'    => $now->toDateTimeString(),
                'value'        => (int) $value,
                'week'         => $now->weekOfMonth,
                'month'        => $now->month,
                'year'         => $now->year,
            ]],
            ['operation_id', 'character_id'],
            ['ship_type_id', 'join_time', 'value', 'week', 'month', 'year']
        );

        return response()->json([
            'message' => 'PAP added successfully.',
            'data' => [
                'operation_id' => $operationId,
                'character_id' => $characterId,
                'ship_type_id' => $shipTypeId,
                'value'        => (int) $value,
                'join_time'    => $now->toDateTimeString(),
            ],
        ], 201);
    }

    /**
     * Validate the X-Token header against the api_tokens table.
     * Tokens are instance-wide and optionally restricted by source IP.
     */
    protected function validateToken(Request $request): bool
    {
        $token = $request->header('X-Token', '');

        if (empty($token)) {
            return false;
        }

        $record = DB::table('api_tokens')->where('token', $token)->first();

        if (!$record) {
            return false;
        }

        // Check IP restriction if allowed_src is set (0.0.0.0 means allow all)
        if ($record->allowed_src && $record->allowed_src !== '0.0.0.0') {
            $clientIp = $request->ip();
            if ($clientIp !== $record->allowed_src) {
                return false;
            }
        }

        return true;
    }

    protected function resolveOperationValue(int $operationId): int
    {
        $operation = Operation::with('tags')->find($operationId);

        if ($operation && $operation->tags->count() > 0) {
            return (int) $operation->tags->max('quantifier');
        }

        return 0;
    }

    /**
     * GET /api/manual-pap/report/{year}/{month}
     * Returns monthly PAP report as JSON.
     */
    public function report(Request $request, int $year, int $month): JsonResponse
    {
        if (!$this->validateToken($request)) {
            return response()->json([
                'error' => 'Unauthorized. Provide a valid X-Token header.',
            ], 401);
        }

        $webController = new ManualPapController();
        $results = $webController->buildReportData($month, $year);

        $totalPaps = array_sum(array_column($results, 'total_paps'));

        return response()->json([
            'month'             => $month,
            'year'              => $year,
            'total_paps'        => $totalPaps,
            'total_characters'  => count($results),
            'data'              => $results,
        ]);
    }

    /**
     * GET /api/manual-pap/inactive
     * Returns main characters with zero PAPs in the last 3 months (whitelisted corporations).
     */
    public function inactive(Request $request): JsonResponse
    {
        if (!$this->validateToken($request)) {
            return response()->json([
                'error' => 'Unauthorized. Provide a valid X-Token header.',
            ], 401);
        }

        $webController = new ManualPapController();
        $corporationIds = ManualPapController::getWhitelistedCorpIds();
        $results = $webController->buildInactiveData($corporationIds);

        return response()->json([
            'total_inactive'    => count($results),
            'corporation_ids'   => $corporationIds,
            'data'              => $results,
        ]);
    }
}
