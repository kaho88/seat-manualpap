<?php

namespace Seat\ManualPap\Http\Controllers\Api;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Seat\Kassie\Calendar\Models\Operation;
use Seat\ManualPap\Http\Controllers\ManualPapController;
use Seat\Web\Models\User;

class ManualPapApiController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);

        if (!$user) {
            return response()->json([
                'error' => 'Unauthorized. Provide a valid X-Token header.',
            ], 401);
        }

        if (!$user->isAdmin() && !$user->can('manualpap.api')) {
            return response()->json([
                'error' => 'Forbidden. User does not have manualpap.api permission.',
            ], 403);
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
     * Resolve a SeAT user from the X-Token header.
     * Uses the same token column as SeAT's built-in API authentication.
     */
    protected function resolveUser(Request $request): ?User
    {
        $token = $request->header('X-Token', '');

        if (empty($token)) {
            return null;
        }

        return User::where('api_token', $token)->first();
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
        $user = $this->resolveUser($request);

        if (!$user) {
            return response()->json([
                'error' => 'Unauthorized. Provide a valid X-Token header.',
            ], 401);
        }

        if (!$user->isAdmin() && !$user->can('manualpap.api')) {
            return response()->json([
                'error' => 'Forbidden. User does not have manualpap.api permission.',
            ], 403);
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
}
