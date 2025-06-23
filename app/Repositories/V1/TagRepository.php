<?php

namespace App\Repositories\V1;

use App\Models\Tag;
use App\Utilities\ResponseHandler;
use Illuminate\Http\Request;

class TagRepository extends BaseRepository
{
    protected string $logChannel;

    public function __construct(Request $request, Tag $tag)
    {
        parent::__construct($tag);
        $this->logChannel = 'tag_logs';
    }

    public function tagListing($request)
    {
        try {
            $query = $this->model::query();

            if ($request->has('popular')) {
                $query->popular($request->input('popular', 10));
            }

            if ($request->has('search')) {
                $searchTerm = $request->input('search');
                $query->where('name', 'like', "%{$searchTerm}%");
            }

            $orderBy = $request->input('order_by', 'blogs_count');
            $order = $request->input('order', 'desc');
            $query->orderBy($orderBy, $order);

            $perPage = $request->input('per_page', 20);
            $tags = $query->paginate($perPage);

            return ResponseHandler::success($tags, __('common.success'));
        } catch (\Exception $e) {
            $this->logData($this->logChannel, $this->prepareExceptionLog($e), 'error');
            return ResponseHandler::error($this->prepareExceptionLog($e), 500, 50);
        }
    }

    public function getTagById($id)
    {
        try {
            $tag = $this->model::with(['publishedBlogs' => function ($query) {
                                $query->with(['author'])->latest('published_at');
                            }])
                              ->where('id', $id)
                              ->orWhere('uuid', $id)
                              ->orWhere('slug', $id)
                              ->first();

            if (!$tag) {
                return ResponseHandler::error(__('common.not_found'), 404, 51);
            }

            return ResponseHandler::success($tag, __('common.success'));
        } catch (\Exception $e) {
            $this->logData($this->logChannel, $this->prepareExceptionLog($e), 'error');
            return ResponseHandler::error($this->prepareExceptionLog($e), 500, 52);
        }
    }

    public function createTag(array $validatedRequest)
    {
        try {
            $tag = $this->model::create($validatedRequest);

            return ResponseHandler::success($tag, __('common.success'));
        } catch (\Exception $e) {
            $this->logData($this->logChannel, $this->prepareExceptionLog($e), 'error');
            return ResponseHandler::error($this->prepareExceptionLog($e), 500, 53);
        }
    }

    public function updateTag(array $validatedRequest)
    {
        try {
            $tag = $this->model::where('id', $validatedRequest['id'])
                              ->orWhere('uuid', $validatedRequest['id'])
                              ->first();

            if (!$tag) {
                return ResponseHandler::error(__('common.not_found'), 404, 54);
            }

            $updateData = array_intersect_key($validatedRequest, array_flip([
                'name', 'description', 'color'
            ]));

            $tag->update($updateData);

            return ResponseHandler::success($tag, __('common.success'));
        } catch (\Exception $e) {
            $this->logData($this->logChannel, $this->prepareExceptionLog($e), 'error');
            return ResponseHandler::error($this->prepareExceptionLog($e), 500, 55);
        }
    }

    public function deleteTag(array $validatedRequest)
    {
        try {
            $tag = $this->model::where('id', $validatedRequest['id'])
                              ->orWhere('uuid', $validatedRequest['id'])
                              ->first();

            if (!$tag) {
                return ResponseHandler::error(__('common.not_found'), 404, 56);
            }

            $tag->blogs()->detach();
            $tag->delete();

            return ResponseHandler::success([], __('common.success'));
        } catch (\Exception $e) {
            $this->logData($this->logChannel, $this->prepareExceptionLog($e), 'error');
            return ResponseHandler::error($this->prepareExceptionLog($e), 500, 57);
        }
    }


}
