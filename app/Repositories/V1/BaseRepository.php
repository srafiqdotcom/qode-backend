<?php

namespace App\Repositories\V1;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Response;

class BaseRepository
{
    protected Request $request;
    protected Model $model;



    /**
     * BaseRepository constructor.
     *
     * @param Model $model
     */
    public function __construct(Model $model)
    {
        $this->request = app('request');
        $this->model = $model;
    }

    /**
     * Retrieve all instances of the model.
     *
     * @return Collection
     */
    public function all(): Collection
    {
        return $this->model->all();
    }

    /**
     * Create a new record in the database.
     *
     * @param array $data
     * @return Model
     */
    public function create(array $data): Model
    {
        return $this->model->create($data);
    }

    /**
     * Update a record in the database.
     *
     * @param array $data
     * @param int $id
     * @return bool
     */
    public function update(array $data, int $id): bool
    {
        $record = $this->model->findOrFail($id);
        return $record->update($data);
    }

    /**
     * Delete a record from the database.
     *
     * @param int $id
     * @return bool|null
     */
    public function delete(int $id): ?bool
    {
        return $this->model->destroy($id);
    }

    /**
     * Retrieve a record by ID.
     *
     * @param int $id
     * @return Model|null
     */
    public function getById(int $id): ?Model
    {
        return $this->model->find($id);
    }

    /**
     * Retrieve records by a column's value.
     *
     * @param string $column
     * @param mixed $value
     * @return Collection
     */
    public function getByColumn(string $column, $value): Collection
    {
        return $this->model->where($column, $value)->get();
    }

    /**
     * Update records based on specific conditions.
     *
     * @param array $where
     * @param array $values
     * @return Model|null
     */
    public function updateByColumn(array $where, array $values): ?Model
    {
        $record = $this->model->where($where)->firstOrFail();
        $record->update($values);
        return $record;
    }

    /**
     * Create or update a record.
     *
     * @param array $where
     * @param array $values
     * @return Model
     */
    public function upsert(array $where, array $values): Model
    {
        return $this->model->updateOrCreate($where, $values);
    }

    /**
     * Delete records by column's value.
     *
     * @param string $column
     * @param mixed $value
     * @return bool|null
     */
    public function deleteByColumn(string $column, $value): ?bool
    {
        return $this->model->where($column, $value)->delete();
    }

    /**
     * Filter data with custom filters, sorting, and pagination.
     *
     * @param array $filters
     * @param array $sortby
     * @param array $limit
     * @return Collection
     */
    public function getFilterData(array $filters = [], array $sortby = [], array $limit = []): Collection
    {
        $query = $this->model->query();

        // Apply filters
        foreach ($filters as $filter) {
            $operator = $filter['op'] ?? '=';
            $logic = $filter['log'] ?? 'AND';

            if ($operator === 'IN') {
                $query->whereIn($filter['col'], $filter['val']);
            } elseif ($operator === 'NOTIN') {
                $query->whereNotIn($filter['col'], $filter['val']);
            } elseif ($logic === 'OR') {
                $query->orWhere($filter['col'], $operator, $filter['val']);
            } else {
                $query->where($filter['col'], $operator, $filter['val']);
            }
        }

        // Apply sorting
        foreach ($sortby as $key => $value) {
            if ($key === 'ORDERBY_FIELD') {
                $query->orderByRaw("FIELD({$value['col']}, '" . implode("', '", $value['vals']) . "') DESC");
            } else {
                $query->orderBy($key, $value);
            }
        }

        // Apply pagination limits
        $page = $limit['page'] ?? 1;
        $perPage = $limit['limit'] ?? 10;
        $query->limit($perPage)->offset(($page - 1) * $perPage);

        return $query->get();
    }

    /**
     * Retrieve records where a column's value is in an array.
     *
     * @param string $column
     * @param array $values
     * @return Collection
     */
    public function getWhereInData(string $column, array $values): Collection
    {
        return $this->model->whereIn($column, $values)->get();
    }

    /**
     * Retrieve records where a column's value is not in an array.
     *
     * @param string $column
     * @param array $values
     * @return Collection
     */
    public function getWhereNotInData(string $column, array $values): Collection
    {
        return $this->model->whereNotIn($column, $values)->get();
    }

    /**
     * Count records based on conditions.
     *
     * @param array $where
     * @return int
     */
    public function getCount(array $where = []): int
    {
        return $this->model->where($where)->count();
    }

    /**
     * Paginate data with optional conditions.
     *
     * @param int $page
     * @param int $limit
     * @param array $where
     * @return Collection
     */
    public function getDataByPagination(int $page, int $limit, array $where = []): Collection
    {
        return $this->model->where($where)
            ->limit($limit)
            ->offset(($page - 1) * $limit)
            ->get();
    }

    /**
     * Success response method.
     */
    public function sendResponse($data, int $code = 200, string $message = 'Success', bool $applyencoding = true)
    {
        if ($applyencoding) {
            $data = $this->ApplyHTMLEntities($data);
        }

        return response()->json([
            'message' => $message,
            'code' => $code,
            'data' => $data
        ], $code);
    }

    /**
     * Error response method.
     */
    public function sendError($errorMsg, int $errorcode = 404, int $httpCode = 200, array $errorData = [])
    {
        $response = [
            'message' => $errorMsg,
            'code' => $errorcode
        ];

        if (!empty($errorData)) {
            $response['data'] = $this->ApplyHTMLEntities($errorData);
        }

        return response()->json($response, $httpCode);
    }

    /**
     * Validation with custom messages.
     */
    public function validated(array $rules, array $request)
    {
        $customMessages = ['required' => 'The :attribute field is required.'];
        return Validator::make($request, $rules, $customMessages);
    }

    public function ApplyHTMLEntities($params)
    {
        if (is_array($params)) {
            return array_map([$this, __METHOD__], $params);
        } elseif (is_string($params)) {
            return htmlspecialchars($params);
        } elseif ($params instanceof Collection) {
            return array_map([$this, __METHOD__], $params->toArray());
        }
        return $params;
    }
    public function logData(string $channel, string $message, string $logLevel = 'info'): void
    {
        if ($logLevel === 'error') {
            Log::channel($channel)->error($message);
        } else {
            Log::channel($channel)->info($message);
        }
    }

    /**
     * Prepare a standardized exception log message.
     *
     * @param \Exception|\Throwable $e
     * @return string
     */
    public function prepareExceptionLog(\Throwable $e): string
    {
        return "Exception: {$e->getMessage()} at line {$e->getLine()} in file {$e->getFile()}";
    }
}
