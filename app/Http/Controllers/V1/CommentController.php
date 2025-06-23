<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller as Controller;
use App\Repositories\V1\CommentRepository;
use App\Services\MarkdownService;
use App\Utilities\ResponseHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    protected CommentRepository $commentRepository;
    protected MarkdownService $markdownService;

    public function __construct(CommentRepository $commentRepository, MarkdownService $markdownService, Request $request)
    {
        parent::__construct($request);
        $this->commentRepository = $commentRepository;
        $this->markdownService = $markdownService;
    }

    public function getCommentsByBlog(Request $request, $blogId): JsonResponse
    {
        $rules = [

            'order_by' => 'sometimes|in:created_at,updated_at',
            'order' => 'sometimes|in:asc,desc',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ];

        $validated = $this->validated($rules, $request->all());

        if ($validated->fails()) {
            return ResponseHandler::error(__('common.errors.validation'), 422, 90, $validated->errors());
        }

        return $this->commentRepository->getCommentsByBlog($blogId, $request);
    }

    public function show(Request $request, $id): JsonResponse
    {
        return $this->commentRepository->getCommentById($id);
    }

    public function store(Request $request): JsonResponse
    {
        $rules = [
            'blog_id' => 'required|string',
            'content' => 'required|string|min:3|max:5000',
            'parent_id' => 'sometimes|integer|exists:comments,id',
        ];

        $validated = $this->validated($rules, $request->all());

        if ($validated->fails()) {
            return ResponseHandler::error(__('common.errors.validation'), 422, 91, $validated->errors());
        }

        $validatedData = $validated->validated();

        $markdownErrors = $this->markdownService->validateMarkdown($validatedData['content']);
        if (!empty($markdownErrors)) {
            return ResponseHandler::error('Content validation failed', 422, 92, $markdownErrors);
        }

        return $this->commentRepository->createComment($validatedData);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $rules = [
            'content' => 'required|string|min:3|max:5000',
        ];

        $validated = $this->validated($rules, $request->all());

        if ($validated->fails()) {
            return ResponseHandler::error(__('common.errors.validation'), 422, 93, $validated->errors());
        }

        $validatedData = $validated->validated();
        $validatedData['id'] = $id;

        $markdownErrors = $this->markdownService->validateMarkdown($validatedData['content']);
        if (!empty($markdownErrors)) {
            return ResponseHandler::error('Content validation failed', 422, 94, $markdownErrors);
        }

        return $this->commentRepository->updateComment($validatedData);
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $validatedData = ['id' => $id];
        return $this->commentRepository->deleteComment($validatedData);
    }






}
