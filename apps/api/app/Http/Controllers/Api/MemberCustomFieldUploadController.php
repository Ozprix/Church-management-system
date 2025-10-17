<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CustomField\StoreCustomFieldUploadRequest;
use App\Models\MemberCustomField;
use App\Services\CustomFields\CustomFieldFileService;
use Illuminate\Http\JsonResponse;

class MemberCustomFieldUploadController extends Controller
{
    public function __construct(private readonly CustomFieldFileService $fileService)
    {
        $this->middleware('feature:members');
        $this->middleware('can:member_custom_fields.manage');
    }

    public function store(StoreCustomFieldUploadRequest $request, MemberCustomField $memberCustomField): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');
        $tenantId = $tenant?->id ?? auth()->user()?->tenant_id ?? $memberCustomField->tenant_id;

        $metadata = $this->fileService->store($memberCustomField, $request->file('file'), $tenantId);
        $metadata['url'] = $this->fileService->temporaryUrlFromMetadata($metadata);

        return response()->json([
            'data' => [
                'field_id' => $memberCustomField->id,
                'file' => $metadata,
            ],
        ], 201);
    }
}
