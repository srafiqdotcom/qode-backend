<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller as Controller;
use App\Repositories\V1\BlogRepository;
use App\Utilities\ResponseHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BlogController extends Controller
{
    protected BlogRepository $blogRepository;

    public function __construct(BlogRepository $blogRepository, Request $request)
    {
        parent::__construct($request);
        $this->blogRepository = $blogRepository;
    }

    public function index(Request $request): JsonResponse
    {
        return $this->blogRepository->blogListing($request);
    }

    public function show(Request $request, $id): JsonResponse
    {
        return $this->blogRepository->getBlogById($id);
    }

    public function store(Request $request): JsonResponse
    {
        $rules = [
            'title' => 'required|string|max:255',
            'excerpt' => 'required|string|max:500',
            'description' => 'required|string',
            'keywords' => 'sometimes|array',
            'keywords.*' => 'string|max:50',
            'meta_title' => 'sometimes|string|max:255',
            'meta_description' => 'sometimes|string|max:500',
            'status' => 'sometimes|in:draft,published,scheduled',
            'scheduled_at' => 'required_if:status,scheduled|date|after:now',
            'image' => 'sometimes|string|max:500',
            'image_alt' => 'sometimes|string|max:255',
            'tags' => 'sometimes|array',
            'tags.*' => 'string|max:50',
        ];

        $validated = $this->validated($rules, $request->all());

        if ($validated->fails()) {
            return ResponseHandler::error(__('common.errors.validation'), 422, 40, $validated->errors());
        }

        $validatedData = $validated->validated();

        // **qode** Handle image field - can be either uploaded file or URL string
        if ($request->hasFile('image')) {
            // If image is uploaded as file, keep the file object
            $validatedData['image'] = $request->file('image');
        } elseif ($request->has('image') && is_string($request->input('image'))) {
            // If image is provided as URL string (already uploaded), convert to path
            $imageUrl = $request->input('image');
            if (str_contains($imageUrl, '/uploads/')) {
                // Extract path from URL: http://localhost:8000/uploads/blogs/... -> uploads/blogs/...
                $parsedUrl = parse_url($imageUrl);
                if (isset($parsedUrl['path'])) {
                    $validatedData['image_path'] = ltrim($parsedUrl['path'], '/');
                }
            }
        }

        return $this->blogRepository->createBlog($validatedData, $request->user());
    }

    public function update(Request $request, $id): JsonResponse
    {
        $rules = [
            'title' => 'sometimes|string|max:255',
            'excerpt' => 'sometimes|string|max:500',
            'description' => 'sometimes|string',
            'keywords' => 'sometimes|array',
            'keywords.*' => 'string|max:50',
            'meta_title' => 'sometimes|string|max:255',
            'meta_description' => 'sometimes|string|max:500',
            'status' => 'sometimes|in:draft,published,scheduled',
            'scheduled_at' => 'required_if:status,scheduled|date|after:now',
            'image' => 'sometimes|string|max:500',
            'image_alt' => 'sometimes|string|max:255',
            'tags' => 'sometimes|array',
            'tags.*' => 'string|max:50',
        ];

        $validated = $this->validated($rules, $request->all());

        if ($validated->fails()) {
            return ResponseHandler::error(__('common.errors.validation'), 422, 41, $validated->errors());
        }

        $validatedData = $validated->validated();
        $validatedData['id'] = $id;

        // **qode** Handle image field - can be either uploaded file or URL string
        if ($request->hasFile('image')) {
            // If image is uploaded as file, keep the file object
            $validatedData['image'] = $request->file('image');
        } elseif ($request->has('image') && is_string($request->input('image'))) {
            // If image is provided as URL string (already uploaded), convert to path
            $imageUrl = $request->input('image');
            if (str_contains($imageUrl, '/uploads/')) {
                // Extract path from URL: http://localhost:8000/uploads/blogs/... -> uploads/blogs/...
                $parsedUrl = parse_url($imageUrl);
                if (isset($parsedUrl['path'])) {
                    $validatedData['image_path'] = ltrim($parsedUrl['path'], '/');
                }
            }
        }

        return $this->blogRepository->updateBlog($validatedData, $request->user());
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $validatedData = ['id' => $id];
        return $this->blogRepository->deleteBlog($validatedData, $request->user());
    }

    public function search(Request $request): JsonResponse
    {
        $rules = [
            'query' => 'nullable|string|max:255',
            'tags' => 'sometimes|array',
            'tags.*' => 'string|max:50',
            'author' => 'sometimes|integer|exists:users,id',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'highlight' => 'sometimes|boolean',
        ];

        $validated = $this->validated($rules, $request->all());

        if ($validated->fails()) {
            return ResponseHandler::error(__('common.errors.validation'), 422, 42, $validated->errors());
        }

        $validatedData = $validated->validated();

        // **qode** Validate that either query or tags are provided
        if (empty($validatedData['query']) && empty($validatedData['tags'])) {
            return ResponseHandler::error('Either query or tags must be provided for search.', 422, 42);
        }

        return $this->blogRepository->searchBlogs($validatedData);
    }



    public function getByTag(Request $request, string $tagSlug): JsonResponse
    {
        $rules = [
            'per_page' => 'sometimes|integer|min:1|max:100',
            'order_by' => 'sometimes|in:created_at,published_at,views_count,title',
            'order' => 'sometimes|in:asc,desc',
        ];

        $validated = $this->validated($rules, $request->all());

        if ($validated->fails()) {
            return ResponseHandler::error(__('common.errors.validation'), 422, 43, $validated->errors());
        }

        return $this->blogRepository->searchByTag($tagSlug, $validated->validated());
    }

    public function getByAuthor(Request $request, int $authorId): JsonResponse
    {
        $rules = [
            'per_page' => 'sometimes|integer|min:1|max:100',
            'order_by' => 'sometimes|in:created_at,published_at,views_count,title',
            'order' => 'sometimes|in:asc,desc',
        ];

        $validated = $this->validated($rules, $request->all());

        if ($validated->fails()) {
            return ResponseHandler::error(__('common.errors.validation'), 422, 44, $validated->errors());
        }

        return $this->blogRepository->searchByAuthor($authorId, $validated->validated());
    }

    public function publish(Request $request, $id): JsonResponse
    {
        $validatedData = [
            'id' => $id,
            'status' => 'published'
        ];

        return $this->blogRepository->updateBlog($validatedData, $request->user());
    }

    public function schedule(Request $request, $id): JsonResponse
    {
        $rules = [
            'scheduled_at' => 'required|date|after:now',
        ];

        $validated = $this->validated($rules, $request->all());

        if ($validated->fails()) {
            return ResponseHandler::error(__('common.errors.validation'), 422, 45, $validated->errors());
        }

        $validatedData = $validated->validated();
        $validatedData['id'] = $id;
        $validatedData['status'] = 'scheduled';

        return $this->blogRepository->updateBlog($validatedData, $request->user());
    }

    public function draft(Request $request, $id): JsonResponse
    {
        $validatedData = [
            'id' => $id,
            'status' => 'draft'
        ];

        return $this->blogRepository->updateBlog($validatedData, $request->user());
    }
}
