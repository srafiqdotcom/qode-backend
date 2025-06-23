<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller as Controller;
use App\Repositories\V1\TagRepository;
use App\Utilities\ResponseHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TagController extends Controller
{
    protected TagRepository $tagRepository;

    public function __construct(TagRepository $tagRepository, Request $request)
    {
        parent::__construct($request);
        $this->tagRepository = $tagRepository;
    }

    public function index(Request $request): JsonResponse
    {
        return $this->tagRepository->tagListing($request);
    }

    public function show(Request $request, $id): JsonResponse
    {
        return $this->tagRepository->getTagById($id);
    }

    public function store(Request $request): JsonResponse
    {
        $rules = [
            'name' => 'required|string|max:100|unique:tags,name',
            'description' => 'sometimes|string|max:500',
            'color' => 'sometimes|string|regex:/^#[0-9A-Fa-f]{6}$/',
        ];

        $validated = $this->validated($rules, $request->all());

        if ($validated->fails()) {
            return ResponseHandler::error(__('common.errors.validation'), 422, 60, $validated->errors());
        }

        return $this->tagRepository->createTag($validated->validated());
    }

    public function update(Request $request, $id): JsonResponse
    {
        $rules = [
            'name' => 'sometimes|string|max:100|unique:tags,name,' . $id,
            'description' => 'sometimes|string|max:500',
            'color' => 'sometimes|string|regex:/^#[0-9A-Fa-f]{6}$/',
        ];

        $validated = $this->validated($rules, $request->all());

        if ($validated->fails()) {
            return ResponseHandler::error(__('common.errors.validation'), 422, 61, $validated->errors());
        }

        $validatedData = $validated->validated();
        $validatedData['id'] = $id;

        return $this->tagRepository->updateTag($validatedData);
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $validatedData = ['id' => $id];
        return $this->tagRepository->deleteTag($validatedData);
    }


}
