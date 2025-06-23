<?php

namespace App\Repositories\V1;

use App\Models\Blog;
use App\Models\Tag;
use App\Services\FileUploadService;
use App\Services\CacheService;
use App\Services\SearchService;
use App\Services\EmailService;
use App\Utilities\ResponseHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BlogRepository extends BaseRepository
{
    protected string $logChannel;
    protected FileUploadService $fileUploadService;
    protected CacheService $cacheService;
    protected SearchService $searchService;
    protected EmailService $emailService;

    public function __construct(Request $request, Blog $blog, FileUploadService $fileUploadService, CacheService $cacheService, SearchService $searchService, EmailService $emailService)
    {
        parent::__construct($blog);
        $this->logChannel = 'blog_logs';
        $this->fileUploadService = $fileUploadService;
        $this->cacheService = $cacheService;
        $this->searchService = $searchService;
        $this->emailService = $emailService;
    }

    public function blogListing($request)
    {
        try {
            $cacheKey = $this->buildCacheKey($request->all());

            $cachedBlogs = $this->cacheService->getBlogList($cacheKey);
            if ($cachedBlogs !== null) {
                return ResponseHandler::success($cachedBlogs, __('common.success'));
            }

            // **qode** Optimized query - only load essential data for listing + limit scope for performance
            $query = $this->model::with(['author:id,name,email', 'tags:id,name,slug'])
                                 ->select('id', 'uuid', 'title', 'slug', 'excerpt', 'image_path', 'image_alt',
                                         'status', 'published_at', 'author_id', 'views_count', 'comments_count', 'created_at')
                                 ->where('created_at', '>=', now()->subMonths(6)); // **qode** Only show recent blogs for performance

            $allowedColumns = ['title', 'status', 'author_id'];
            $allowedOperators = ['=', 'like'];

            $filters = $request->input('filters', []);

            foreach ($filters as $column => $value) {
                if (in_array($column, $allowedColumns)) {
                    if (is_array($value)) {
                        foreach ($value as $operator => $filterValue) {
                            if (in_array($operator, $allowedOperators)) {
                                if ($operator === 'like') {
                                    $query->where($column, 'like', $filterValue);
                                } else {
                                    $query->where($column, $operator, $filterValue);
                                }
                            }
                        }
                    } else {
                        $query->where($column, $value);
                    }
                }
            }

            if ($request->has('tag')) {
                $query->withTag($request->input('tag'));
            }

            if ($request->has('author')) {
                $query->byAuthor($request->input('author'));
            }

            if ($request->has('status')) {
                $status = $request->input('status');
                if ($status === 'published') {
                    $query->published();
                } elseif ($status === 'draft') {
                    $query->draft();
                } elseif ($status === 'scheduled') {
                    $query->scheduled();
                }
            } else {
                $query->published();
            }

            $orderBy = $request->input('order_by', 'created_at');
            $order = $request->input('order', 'desc');
            $query->orderBy($orderBy, $order);

            // **qode** Reduced page size for better performance with large dataset
            $perPage = min($request->input('per_page', 10), 50); // Max 50 items per page
            $blogs = $query->paginate($perPage);

            $this->cacheService->setBlogList($cacheKey, $blogs, $this->getCacheTags($request));

            return ResponseHandler::success($blogs, __('common.success'));
        } catch (\Exception $e) {
            $this->logData($this->logChannel, $this->prepareExceptionLog($e), 'error');
            return ResponseHandler::error($this->prepareExceptionLog($e), 500, 30);
        }
    }

    public function getBlogById($id)
    {
        try {
            $blogId = $this->resolveBlogId($id);

            if ($blogId) {
                $cachedBlog = $this->cacheService->getBlog($blogId);
                if ($cachedBlog !== null) {
                    if ($cachedBlog->isPublished()) {
                        $cachedBlog->incrementViews();
                    }
                    return ResponseHandler::success($cachedBlog, __('common.success'));
                }
            }

            $blog = $this->model::with(['author', 'tags', 'approvedComments.user'])
                               ->where('id', $id)
                               ->orWhere('uuid', $id)
                               ->orWhere('slug', $id)
                               ->first();

            if (!$blog) {
                return ResponseHandler::error(__('common.not_found'), 404, 31);
            }

            $this->cacheService->setBlog($blog->id, $blog);

            if ($blog->isPublished()) {
                $blog->incrementViews();
            }

            return ResponseHandler::success($blog, __('common.success'));
        } catch (\Exception $e) {
            $this->logData($this->logChannel, $this->prepareExceptionLog($e), 'error');
            return ResponseHandler::error($this->prepareExceptionLog($e), 500, 32);
        }
    }

    public function createBlog(array $validatedRequest, $authenticatedUser = null)
    {
        try {
            // **qode** Get user from parameter or auth context
            $user = $authenticatedUser ?: auth('api')->user();
            if (!$user) {
                return ResponseHandler::error('Authentication required.', 401, 32);
            }

            $blogData = [
                'title' => $validatedRequest['title'],
                'excerpt' => $validatedRequest['excerpt'],
                'description' => $validatedRequest['description'],
                'keywords' => $validatedRequest['keywords'] ?? [],
                'meta_title' => $validatedRequest['meta_title'] ?? $validatedRequest['title'],
                'meta_description' => $validatedRequest['meta_description'] ?? $validatedRequest['excerpt'],
                'status' => $validatedRequest['status'] ?? 'draft',
                'author_id' => $user->id,
            ];

            if (isset($validatedRequest['scheduled_at']) && $validatedRequest['status'] === 'scheduled') {
                $blogData['scheduled_at'] = $validatedRequest['scheduled_at'];
            }

            if ($validatedRequest['status'] === 'published') {
                $blogData['published_at'] = now();
            }

            // **qode** Handle image - can be uploaded file or existing path
            if (isset($validatedRequest['image']) && is_object($validatedRequest['image'])) {
                // If image is an uploaded file object
                $imageData = $this->fileUploadService->uploadImage(
                    $validatedRequest['image'],
                    'blogs',
                    ['width' => 1200, 'height' => 630]
                );

                if ($imageData) {
                    $blogData['image_path'] = $imageData['path'];
                    $blogData['image_alt'] = $validatedRequest['image_alt'] ?? $validatedRequest['title'];
                }
            } elseif (isset($validatedRequest['image_path'])) {
                // If image path is provided directly (already uploaded)
                $blogData['image_path'] = $validatedRequest['image_path'];
                $blogData['image_alt'] = $validatedRequest['image_alt'] ?? $validatedRequest['title'];
            }

            $blog = $this->model::create($blogData);

            if (isset($validatedRequest['tags']) && is_array($validatedRequest['tags'])) {
                $this->attachTags($blog, $validatedRequest['tags']);
            }

            $blog->load(['author', 'tags']);

            // **qode** Send email notification if blog is published
            if ($blog->isPublished()) {
                $this->emailService->sendBlogPublishedNotification($blog);
            }

            return ResponseHandler::success($blog, __('common.success'));
        } catch (\Exception $e) {
            $this->logData($this->logChannel, $this->prepareExceptionLog($e), 'error');
            return ResponseHandler::error($this->prepareExceptionLog($e), 500, 33);
        }
    }

    public function updateBlog(array $validatedRequest, $authenticatedUser = null)
    {
        try {
            $blog = $this->model::where('id', $validatedRequest['id'])
                               ->orWhere('uuid', $validatedRequest['id'])
                               ->first();

            if (!$blog) {
                return ResponseHandler::error(__('common.not_found'), 404, 34);
            }

            // **qode** Get user from parameter or auth context
            $user = $authenticatedUser ?: auth('api')->user();
            if (!$user) {
                return ResponseHandler::error('Authentication required.', 401, 35);
            }

            if ($blog->author_id !== $user->id && !$user->isAuthor()) {
                return ResponseHandler::error('Unauthorized to update this blog.', 403, 35);
            }

            $updateData = array_intersect_key($validatedRequest, array_flip([
                'title', 'excerpt', 'description', 'keywords', 'meta_title', 'meta_description', 'status', 'scheduled_at'
            ]));

            $wasPublished = $blog->isPublished();

            if (isset($validatedRequest['status'])) {
                if ($validatedRequest['status'] === 'published' && !$blog->isPublished()) {
                    $updateData['published_at'] = now();
                } elseif ($validatedRequest['status'] === 'scheduled') {
                    $updateData['scheduled_at'] = $validatedRequest['scheduled_at'] ?? null;
                }
            }

            // **qode** Handle image - can be uploaded file or existing path
            if (isset($validatedRequest['image']) && is_object($validatedRequest['image'])) {
                // If image is an uploaded file object, delete old and upload new
                if ($blog->image_path) {
                    $this->fileUploadService->deleteFile($blog->image_path);
                }

                $imageData = $this->fileUploadService->uploadImage(
                    $validatedRequest['image'],
                    'blogs',
                    ['width' => 1200, 'height' => 630]
                );

                if ($imageData) {
                    $updateData['image_path'] = $imageData['path'];
                    $updateData['image_alt'] = $validatedRequest['image_alt'] ?? $blog->title;
                }
            } elseif (isset($validatedRequest['image_path'])) {
                // If image path is provided directly (already uploaded), just update the path
                $updateData['image_path'] = $validatedRequest['image_path'];
                $updateData['image_alt'] = $validatedRequest['image_alt'] ?? $blog->title;
            }

            $blog->update($updateData);

            if (isset($validatedRequest['tags']) && is_array($validatedRequest['tags'])) {
                $blog->tags()->detach();
                $this->attachTags($blog, $validatedRequest['tags']);
            }

            $blog->load(['author', 'tags']);


            if (!$wasPublished && $blog->isPublished()) {
                $this->emailService->sendBlogPublishedNotification($blog);
            }

            return ResponseHandler::success($blog, __('common.success'));
        } catch (\Exception $e) {
            $this->logData($this->logChannel, $this->prepareExceptionLog($e), 'error');


            return ResponseHandler::error($this->prepareExceptionLog($e), 500, 36);
        }
    }

    public function deleteBlog(array $validatedRequest, $authenticatedUser = null)
    {
        try {
            $blog = $this->model::where('id', $validatedRequest['id'])
                               ->orWhere('uuid', $validatedRequest['id'])
                               ->first();

            if (!$blog) {
                return ResponseHandler::error(__('common.not_found'), 404, 37);
            }

            // **qode** Get user from parameter or auth context
            $user = $authenticatedUser ?: auth('api')->user();
            if (!$user) {
                return ResponseHandler::error('Authentication required.', 401, 38);
            }

            if ($blog->author_id !== $user->id && !$user->isAuthor()) {
                return ResponseHandler::error('Unauthorized to delete this blog.', 403, 38);
            }

            if ($blog->image_path) {
                $this->fileUploadService->deleteFile($blog->image_path);
            }

            $blog->delete();

            return ResponseHandler::success([], __('common.success'));
        } catch (\Exception $e) {
            $this->logData($this->logChannel, $this->prepareExceptionLog($e), 'error');
            return ResponseHandler::error($this->prepareExceptionLog($e), 500, 39);
        }
    }

    public function searchBlogs(array $validatedRequest)
    {
        try {
            $searchQuery = $validatedRequest['query'] = !empty($validatedRequest['query']) ? $validatedRequest['query'] : '';
            $filters = [];

            if (isset($validatedRequest['tags'])) {
                $filters['tags'] = $validatedRequest['tags'];
            }

            if (isset($validatedRequest['author'])) {
                $filters['author'] = $validatedRequest['author'];
            }

            $cacheKey = $this->buildSearchCacheKey($searchQuery, $filters);
            $cachedResults = $this->cacheService->getSearchResults($searchQuery, $filters);

            if ($cachedResults !== null) {
                return ResponseHandler::success($cachedResults, __('common.success'));
            }

            $searchResults = $this->searchService->search(
                $searchQuery,
                $filters,
                $validatedRequest['per_page'] ?? 50
            );

            if (empty($searchResults)) {
                $fallbackResults = $this->fallbackSearch($validatedRequest);
                $this->cacheService->setSearchResults($searchQuery, $filters, $fallbackResults);
                return ResponseHandler::success($fallbackResults, __('common.success'));
            }

            $this->cacheService->setSearchResults($searchQuery, $filters, $searchResults);

            return ResponseHandler::success($searchResults, __('common.success'));
        } catch (\Exception $e) {
            $this->logData($this->logChannel, $this->prepareExceptionLog($e), 'error');
            return ResponseHandler::error($this->prepareExceptionLog($e), 500, 46);
        }
    }



    public function searchByTag(string $tagSlug, array $params = [])
    {
        try {
            $cacheKey = 'tag_search_' . $tagSlug . '_' . md5(serialize($params));
            $cachedResults = $this->cacheService->getBlogList($cacheKey, ['tag_' . $tagSlug]);

            if ($cachedResults !== null) {
                return ResponseHandler::success($cachedResults, __('common.success'));
            }

            $searchResults = $this->searchService->searchByTag($tagSlug, $params['per_page'] ?? 50);

            if (empty($searchResults)) {
                $fallbackResults = $this->fallbackTagSearch($tagSlug, $params);
                $this->cacheService->setBlogList($cacheKey, $fallbackResults, ['tag_' . $tagSlug]);
                return ResponseHandler::success($fallbackResults, __('common.success'));
            }

            $this->cacheService->setBlogList($cacheKey, $searchResults, ['tag_' . $tagSlug]);
            return ResponseHandler::success($searchResults, __('common.success'));
        } catch (\Exception $e) {
            $this->logData($this->logChannel, $this->prepareExceptionLog($e), 'error');
            return ResponseHandler::error($this->prepareExceptionLog($e), 500, 48);
        }
    }

    public function searchByAuthor(int $authorId, array $params = [])
    {
        try {
            $cacheKey = 'author_search_' . $authorId . '_' . md5(serialize($params));
            $cachedResults = $this->cacheService->getBlogList($cacheKey, ['author_' . $authorId]);

            if ($cachedResults !== null) {
                return ResponseHandler::success($cachedResults, __('common.success'));
            }

            $searchResults = $this->searchService->searchByAuthor($authorId, $params['per_page'] ?? 50);

            if (empty($searchResults)) {
                $fallbackResults = $this->fallbackAuthorSearch($authorId, $params);
                $this->cacheService->setBlogList($cacheKey, $fallbackResults, ['author_' . $authorId]);
                return ResponseHandler::success($fallbackResults, __('common.success'));
            }

            $this->cacheService->setBlogList($cacheKey, $searchResults, ['author_' . $authorId]);
            return ResponseHandler::success($searchResults, __('common.success'));
        } catch (\Exception $e) {
            $this->logData($this->logChannel, $this->prepareExceptionLog($e), 'error');
            return ResponseHandler::error($this->prepareExceptionLog($e), 500, 49);
        }
    }

    private function attachTags(Blog $blog, array $tagNames): void
    {
        $tagIds = [];

        foreach ($tagNames as $tagName) {
            $tag = Tag::firstOrCreate(
                ['name' => trim($tagName)],
                ['slug' => Str::slug(trim($tagName))]
            );
            $tagIds[] = $tag->id;
        }

        $blog->tags()->attach($tagIds);

        foreach ($tagIds as $tagId) {
            $tag = Tag::find($tagId);
            $tag->updateBlogsCount();
        }
    }

    private function buildCacheKey(array $params): string
    {
        // **qode** Normalize parameters to match cache warming defaults
        $normalizedParams = [
            'per_page' => $params['per_page'] ?? 10,
            'page' => $params['page'] ?? 1,
            'status' => $params['status'] ?? 'published',
            'order_by' => $params['order_by'] ?? 'created_at',
            'order' => $params['order'] ?? 'desc',
        ];

        // Add other parameters if they exist
        foreach (['tag', 'author', 'filters'] as $key) {
            if (isset($params[$key])) {
                $normalizedParams[$key] = $params[$key];
            }
        }

        ksort($normalizedParams);
        return md5(serialize($normalizedParams));
    }

    private function getCacheTags($request): array
    {
        $tags = [];

        if ($request->has('tag')) {
            $tags[] = 'tag_' . $request->input('tag');
        }

        if ($request->has('author')) {
            $tags[] = 'author_' . $request->input('author');
        }

        if ($request->has('status')) {
            $tags[] = 'status_' . $request->input('status');
        }

        return $tags;
    }

    private function resolveBlogId($identifier): ?int
    {
        if (is_numeric($identifier)) {
            return (int) $identifier;
        }

        $blog = $this->model::where('uuid', $identifier)
                           ->orWhere('slug', $identifier)
                           ->first(['id']);

        return $blog ? $blog->id : null;
    }

    private function buildSearchCacheKey(string $query, array $filters): string
    {
        return md5($query . serialize($filters));
    }

    private function fallbackSearch(array $validatedRequest): array
    {
        // **qode** Optimized search query - minimal data loading
        $query = $this->model::with(['author:id,name', 'tags:id,name,slug'])
                            ->select('id', 'uuid', 'title', 'slug', 'excerpt', 'image_path',
                                    'status', 'published_at', 'author_id', 'views_count', 'comments_count')
                            ->published();

        $searchQuery = $validatedRequest['query'] ?? '';

        // **qode** Only apply text search if query is not empty
        if (!empty($searchQuery)) {
            $query->where(function ($q) use ($searchQuery) {
                $q->where('title', 'like', "%{$searchQuery}%")
                  ->orWhere('excerpt', 'like', "%{$searchQuery}%")
                  ->orWhere('description', 'like', "%{$searchQuery}%")
                  ->orWhereJsonContains('keywords', $searchQuery);
            });
        }

        if (isset($validatedRequest['tags']) && !empty($validatedRequest['tags'])) {
            $query->whereHas('tags', function ($q) use ($validatedRequest) {
                $q->whereIn('name', $validatedRequest['tags'])
                  ->orWhereIn('slug', $validatedRequest['tags']);
            });
        }

        if (isset($validatedRequest['author'])) {
            $query->where('author_id', $validatedRequest['author']);
        }

        // **qode** Order by relevance if query exists, otherwise by date
        if (!empty($searchQuery)) {
            $query->orderByRaw("
                CASE
                    WHEN title LIKE ? THEN 1
                    WHEN excerpt LIKE ? THEN 2
                    WHEN description LIKE ? THEN 3
                    ELSE 4
                END, published_at DESC
            ", ["%{$searchQuery}%", "%{$searchQuery}%", "%{$searchQuery}%"]);
        } else {
            $query->orderBy('published_at', 'desc');
        }

        $perPage = $validatedRequest['per_page'] ?? 15;
        return $query->paginate($perPage)->toArray();
    }

    private function fallbackTagSearch(string $tagSlug, array $params): array
    {
        $query = $this->model::with(['author', 'tags'])
                            ->published()
                            ->whereHas('tags', function ($q) use ($tagSlug) {
                                $q->where('slug', $tagSlug);
                            });

        $orderBy = $params['order_by'] ?? 'published_at';
        $order = $params['order'] ?? 'desc';
        $query->orderBy($orderBy, $order);

        $perPage = $params['per_page'] ?? 15;
        return $query->paginate($perPage)->toArray();
    }

    private function fallbackAuthorSearch(int $authorId, array $params): array
    {
        $query = $this->model::with(['author', 'tags'])
                            ->published()
                            ->where('author_id', $authorId);

        $orderBy = $params['order_by'] ?? 'published_at';
        $order = $params['order'] ?? 'desc';
        $query->orderBy($orderBy, $order);

        $perPage = $params['per_page'] ?? 15;
        return $query->paginate($perPage)->toArray();
    }


}
