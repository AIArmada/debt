<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BankImport;
use App\Models\BankImportRow;
use App\Services\ProfileAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ImportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate(['profile_id' => ['required', 'uuid']]);
        $profile = app(ProfileAccess::class)
            ->accessibleProfiles($request->user())
            ->whereKey($validated['profile_id'])
            ->firstOrFail();
        Gate::authorize('view', $profile);

        $perPage = min(100, max(1, $request->integer('per_page', 25)));
        $imports = $profile->bankImports()
            ->with(['rows:id,bank_import_id,obligation_id,financial_transaction_id,row_number,occurred_on,description,amount,currency,suggested_direction,external_reference,status'])
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'data' => $imports->getCollection()->map(fn (BankImport $import): array => [
                'id' => $import->getKey(),
                'format' => $import->format,
                'currency' => $import->currency,
                'status' => $import->status,
                'row_count' => $import->row_count,
                'matched_count' => $import->matched_count,
                'error_message' => $import->error_message,
                'rows' => $import->rows->map(fn (BankImportRow $row): array => [
                    'id' => $row->getKey(),
                    'obligation_id' => $row->obligation_id,
                    'financial_transaction_id' => $row->financial_transaction_id,
                    'row_number' => $row->row_number,
                    'occurred_on' => $row->occurred_on?->toDateString(),
                    'description' => $row->description,
                    'amount' => $row->amount,
                    'currency' => $row->currency,
                    'suggested_direction' => $row->suggested_direction,
                    'external_reference' => $row->external_reference,
                    'status' => $row->status,
                ])->values()->all(),
            ])->values(),
            'meta' => [
                'current_page' => $imports->currentPage(),
                'per_page' => $imports->perPage(),
                'total' => $imports->total(),
                'last_page' => $imports->lastPage(),
            ],
        ]);
    }
}
