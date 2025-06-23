<?php

namespace App\Http\Controllers\V1;


use App\Repositories\V1\ProjectRepository;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller as Controller;
use Illuminate\Support\Facades\Validator;
use App\Utilities\ResponseHandler;
use Illuminate\Validation\ValidationException;
use stdClass;

class ProjectController extends Controller
{
    protected ProjectRepository $projectRepository;

    /**
     * AuthController constructor.
     *
     * @param ProjectRepository $projectRepository
     * @param Request $request
     */
    public function __construct(ProjectRepository $projectRepository, Request $request)
    {
        parent::__construct($request);
        $this->projectRepository = $projectRepository;
    }
    /**
     * GET /api/projects
     * List all projects with dynamic attributes.
     */
    public function index(Request $request)
    {

        $rules = [
            'filters'               => 'sometimes|array',

            'filters.name'          => 'sometimes|string',
            'filters.status'        => 'sometimes|string',

            'filters.name.like'     => 'sometimes|string',
            'filters.name.='        => 'sometimes|string',
            'filters.status.like'   => 'sometimes|string',
            'filters.status.='      => 'sometimes|string',

            'order_by'              => 'sometimes|in:name,status',
            'order'                 => 'sometimes|in:asc,desc',

            'rpp'                   => 'sometimes|integer|min:1',
            'page'                  => 'sometimes|integer|min:1',
        ];

        $validated = $this->validated($rules, $request->all());
        if ($validated->fails()) {
            return ResponseHandler::error(__('common.errors.validation'), 422, 2001, $validated->errors());
        }

        return $this->projectRepository->projectListing($request);

    }

    /**
     * POST /api/projects
     * Create a new project (and store any dynamic attributes if provided).
     * @throws ValidationException
     */
    public function store(Request $request)
    {
        $rules = [
            'name'       => 'required|string',
            'status'     => 'sometimes|string',
            'attributes' => 'sometimes|array',
        ];

        $validated = $this->validated($rules, $request->all());
        if ($validated->fails()) {
            return ResponseHandler::error(__('common.errors.validation'), 422, 2001, $validated->errors());
        }

        return $this->projectRepository->createProject($validated->validated());
    }

    /**
     * GET /api/projects/{project}
     * Show a specific project with its dynamic attributes.
     */
    public function show($id, Request $request)
    {
        $request->merge(['id' => $id]);
        $rules = [
            'id'       => 'required|integer|exists:projects,id',
        ];

        $validated = $this->validated($rules, $request->all());
        if ($validated->fails()) {
            return ResponseHandler::error(__('common.errors.validation'), 422, 2001, $validated->errors());
        }
        return $this->projectRepository->showProject($validated->validated());

    }

    /**
     * PUT /api/projects/{project}
     * Update an existing project and its dynamic attributes.
     */
    public function update($id, Request $request)
    {
        // Merge the URL parameter 'id' into the request data.
        $request->merge(['id' => $id]);
        $rules = [
            'id'         => 'required|integer|exists:projects,id',
            'name'       => 'sometimes|required|string',
            'status'     => 'sometimes|string',
            'attributes' => 'sometimes|array',
        ];

        $validated = $this->validated($rules, $request->all());
        if ($validated->fails()) {
            return ResponseHandler::error(__('common.errors.validation'), 422, 2007, $validated->errors());
        }
        return $this->projectRepository->updateProject($validated->validated());
    }

    /**
     * DELETE /api/projects/{project}
     * Delete a project (and optionally its dynamic attributes via cascading).
     */
    public function destroy($id, Request $request)
    {
        $request->merge(['id' => $id]);
        $rules = [
            'id'       => 'required|integer|exists:projects,id',
        ];

        $validated = $this->validated($rules, $request->all());
        if ($validated->fails()) {
            return ResponseHandler::error(__('common.errors.validation'), 422, 2009, $validated->errors());
        }
        return $this->projectRepository->deleteProject($validated->validated());
    }
}
