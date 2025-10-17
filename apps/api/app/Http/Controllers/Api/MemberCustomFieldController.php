<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CustomField\StoreMemberCustomFieldRequest;
use App\Http\Requests\CustomField\UpdateMemberCustomFieldRequest;
use App\Http\Resources\MemberCustomFieldResource;
use App\Models\MemberCustomField;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MemberCustomFieldController extends Controller
{
    public function __construct()
    {
        $this->middleware('feature:members');
        $this->middleware('can:member_custom_fields.manage');
    }

    public function index(Request $request): JsonResponse
    {
        $fields = MemberCustomField::query()
            ->orderBy('name')
            ->paginate($request->integer('per_page', 25))
            ->appends($request->query());

        return MemberCustomFieldResource::collection($fields)->response();
    }

    public function store(StoreMemberCustomFieldRequest $request): JsonResponse
    {
        $data = $request->validated();
        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $field = MemberCustomField::create($data);

        return MemberCustomFieldResource::make($field)->response()->setStatusCode(201);
    }

    public function show(MemberCustomField $memberCustomField): JsonResponse
    {
        return MemberCustomFieldResource::make($memberCustomField)->response();
    }

    public function update(UpdateMemberCustomFieldRequest $request, MemberCustomField $memberCustomField): JsonResponse
    {
        $data = $request->validated();
        if (!empty($data['name']) && empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $memberCustomField->fill($data);
        $memberCustomField->save();

        return MemberCustomFieldResource::make($memberCustomField)->response();
    }

    public function destroy(MemberCustomField $memberCustomField): JsonResponse
    {
        if ($memberCustomField->values()->exists()) {
            return response()->json([
                'message' => 'Cannot delete custom field with existing values.',
            ], 422);
        }

        $memberCustomField->delete();

        return response()->json([], 204);
    }
}
