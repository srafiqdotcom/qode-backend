<?php

namespace App\Repositories\V1;

use App\Models\Comment;
use App\Models\Blog;
use App\Services\MarkdownService;
use App\Services\SpamDetectionService;
use App\Services\CacheService;
use App\Services\EmailService;
use App\Utilities\ResponseHandler;
use Illuminate\Http\Request;

class CommentRepository extends BaseRepository
{
    protected string $logChannel;
    protected MarkdownService $markdownService;
    protected SpamDetectionService $spamDetectionService;
    protected CacheService $cacheService;
    protected EmailService $emailService;

    public function __construct(Request $request, Comment $comment, MarkdownService $markdownService, SpamDetectionService $spamDetectionService, CacheService $cacheService, EmailService $emailService)
    {
        parent::__construct($comment);
        $this->logChannel = 'comment_logs';
        $this->markdownService = $markdownService;
        $this->spamDetectionService = $spamDetectionService;
        $this->cacheService = $cacheService;
        $this->emailService = $emailService;
    }

    public function getCommentsByBlog($blogId, $request)
    {
        try {
            $blog = Blog::where('id', $blogId)
                       ->orWhere('uuid', $blogId)
                       ->orWhere('slug', $blogId)
                       ->first();

            if (!$blog) {
                return ResponseHandler::error(__('common.not_found'), 404, 70);
            }

            $cacheKey = $this->buildCommentCacheKey($request->all());
            $cachedComments = $this->cacheService->getComments($blog->id, $cacheKey);

            if ($cachedComments !== null) {
                return ResponseHandler::success($cachedComments, __('common.success'));
            }

            $query = $this->model::with(['user', 'replies.user', 'approvedReplies.user'])
                                ->where('blog_id', $blog->id)
                                ->parentComments();

            $status = $request->input('status', 'approved');
            if ($status === 'approved') {
                $query->approved();
            } elseif ($status === 'pending') {
                $query->pending();
            } elseif ($status === 'rejected') {
                $query->rejected();
            }

            $orderBy = $request->input('order_by', 'created_at');
            $order = $request->input('order', 'desc');
            $query->orderBy($orderBy, $order);

            $perPage = $request->input('per_page', 20);
            $comments = $query->paginate($perPage);

            foreach ($comments as $comment) {
                $comment->processed_content = $this->markdownService->processComment($comment->content);
                if ($comment->replies) {
                    foreach ($comment->replies as $reply) {
                        $reply->processed_content = $this->markdownService->processComment($reply->content);
                    }
                }
            }

            $this->cacheService->setComments($blog->id, $cacheKey, $comments);

            return ResponseHandler::success($comments, __('common.success'));
        } catch (\Exception $e) {
            $this->logData($this->logChannel, $this->prepareExceptionLog($e), 'error');
            return ResponseHandler::error($this->prepareExceptionLog($e), 500, 71);
        }
    }

    public function getCommentById($id)
    {
        try {
            $comment = $this->model::with(['user', 'blog', 'parent', 'replies.user'])
                                  ->where('id', $id)
                                  ->orWhere('uuid', $id)
                                  ->first();

            if (!$comment) {
                return ResponseHandler::error(__('common.not_found'), 404, 72);
            }

            $comment->processed_content = $this->markdownService->processComment($comment->content);

            return ResponseHandler::success($comment, __('common.success'));
        } catch (\Exception $e) {
            $this->logData($this->logChannel, $this->prepareExceptionLog($e), 'error');
            return ResponseHandler::error($this->prepareExceptionLog($e), 500, 73);
        }
    }

    public function createComment(array $validatedRequest)
    {
        try {
            $blog = Blog::where('id', $validatedRequest['blog_id'])
                       ->orWhere('uuid', $validatedRequest['blog_id'])
                       ->orWhere('slug', $validatedRequest['blog_id'])
                       ->first();

            if (!$blog) {
                return ResponseHandler::error('Blog not found.', 404, 74);
            }

            if (!$blog->isPublished()) {
                return ResponseHandler::error('Cannot comment on unpublished blog.', 403, 75);
            }

            $parentComment = null;
            if (isset($validatedRequest['parent_id'])) {
                $parentComment = $this->model::where('id', $validatedRequest['parent_id'])
                                            ->where('blog_id', $blog->id)
                                            ->approved()
                                            ->first();

                if (!$parentComment) {
                    return ResponseHandler::error('Parent comment not found or not approved.', 404, 76);
                }
            }

            $sanitizedContent = $this->markdownService->sanitizeContent($validatedRequest['content']);

            $spamCheck = $this->spamDetectionService->isSpam(
                $sanitizedContent,
                auth()->id(),
                request()->ip()
            );

            if ($spamCheck['is_spam']) {
                return ResponseHandler::error('Comment flagged as spam and rejected.', 422, 115, $spamCheck['reasons']);
            }

            $commentStatus = $this->getDefaultCommentStatus();
            if ($spamCheck['score'] > 25) {
                $commentStatus = 'pending';
            }

            $commentData = [
                'blog_id' => $blog->id,
                'user_id' => auth()->id(),
                'parent_id' => $parentComment ? $parentComment->id : null,
                'content' => $sanitizedContent,
                'status' => $commentStatus,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ];

            $comment = $this->model::create($commentData);
            $comment->load(['user', 'blog']);
            $comment->processed_content = $this->markdownService->processComment($comment->content);

            // Send email notifications
            if ($comment->parent_id) {
                $this->emailService->sendCommentNotification($comment, 'comment_reply');
            } else {
                $this->emailService->sendCommentNotification($comment, 'new_comment');
            }

            return ResponseHandler::success($comment, __('common.success'));
        } catch (\Exception $e) {
            $this->logData($this->logChannel, $this->prepareExceptionLog($e), 'error');
            return ResponseHandler::error($this->prepareExceptionLog($e), 500, 77);
        }
    }

    public function updateComment(array $validatedRequest)
    {
        try {
            $comment = $this->model::where('id', $validatedRequest['id'])
                                  ->orWhere('uuid', $validatedRequest['id'])
                                  ->first();

            if (!$comment) {
                return ResponseHandler::error(__('common.not_found'), 404, 78);
            }

            if ($comment->user_id !== auth()->id()) {
                return ResponseHandler::error('Unauthorized to update this comment.', 403, 79);
            }

            $sanitizedContent = $this->markdownService->sanitizeContent($validatedRequest['content']);

            $comment->update([
                'content' => $sanitizedContent,
                'status' => $this->getDefaultCommentStatus(),
            ]);

            $comment->load(['user', 'blog']);
            $comment->processed_content = $this->markdownService->processComment($comment->content);

            return ResponseHandler::success($comment, __('common.success'));
        } catch (\Exception $e) {
            $this->logData($this->logChannel, $this->prepareExceptionLog($e), 'error');
            return ResponseHandler::error($this->prepareExceptionLog($e), 500, 80);
        }
    }

    public function deleteComment(array $validatedRequest)
    {
        try {
            $comment = $this->model::where('id', $validatedRequest['id'])
                                  ->orWhere('uuid', $validatedRequest['id'])
                                  ->first();

            if (!$comment) {
                return ResponseHandler::error(__('common.not_found'), 404, 81);
            }

            $user = auth()->user();
            $canDelete = $comment->user_id === $user->id || 
                        ($user->isAuthor() && $comment->blog->author_id === $user->id);

            if (!$canDelete) {
                return ResponseHandler::error('Unauthorized to delete this comment.', 403, 82);
            }

            $comment->delete();

            return ResponseHandler::success([], __('common.success'));
        } catch (\Exception $e) {
            $this->logData($this->logChannel, $this->prepareExceptionLog($e), 'error');
            return ResponseHandler::error($this->prepareExceptionLog($e), 500, 83);
        }
    }



    private function getDefaultCommentStatus(): string
    {
        // **qode** Auto-approve all comments since moderation is not required
        return 'approved';
    }

    private function buildCommentCacheKey(array $params): string
    {
        ksort($params);
        return md5(serialize($params));
    }
}
