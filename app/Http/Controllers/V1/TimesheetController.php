<?php

namespace App\Http\Controllers\V1;

use App\Models\Timesheets;
use App\Repositories\V1\TimesheetRepository;
use App\Utilities\ResponseHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller as Controller;
use stdClass;

class TimesheetController extends Controller
{
    protected TimesheetRepository $timesheetRepository;

    /**
     * AuthController constructor.
     *
     * @param TimesheetRepository $timesheetRepository
     * @param Request $request
     */
    public function __construct(TimesheetRepository $timesheetRepository, Request $request)
    {
        parent::__construct($request);
        $this->timesheetRepository = $timesheetRepository;
    }
    /**
     * Display a listing of timesheets.
     *
     * GET /api/timesheets
     */
    public function index(Request $request)
    {
        $rules = [
            // filters should be an array if provided
            'filters' => 'sometimes|array',

            // For the 'task_name' filter, allow either a string or an array with operators.
            'filters.task_name'         => 'sometimes',
            'filters.task_name.like'    => 'sometimes|string',
            'filters.task_name.='       => 'sometimes|string',

            // For the 'date' filter.
            'filters.date'              => 'sometimes|date',
            'filters.date.like'         => 'sometimes|string',  // if using like operator on a date string
            'filters.date.='            => 'sometimes|date',

            // For the 'hours' filter.
            'filters.hours'             => 'sometimes|numeric',
            'filters.hours.gt'          => 'sometimes|numeric',
            'filters.hours.lt'          => 'sometimes|numeric',
            'filters.hours.='            => 'sometimes|numeric',

            // For the 'user_id' filter.
            'filters.user_id'           => 'sometimes|integer',
            'filters.user_id.='          => 'sometimes|integer',

            // For the 'project_id' filter.
            'filters.project_id'        => 'sometimes|integer',
            'filters.project_id.='       => 'sometimes|integer',

            // Ordering rules:
            'order_by'                  => 'sometimes|in:task_name,date,hours,user_id,project_id',
            'order'                     => 'sometimes|in:asc,desc',

            // Pagination rules:
            'rpp'                       => 'sometimes|integer|min:1',
            'page_no'                   => 'sometimes|integer|min:1',
        ];

        $validated = $this->validated($rules, $request->all());
        if ($validated->fails()) {
            return ResponseHandler::error(__('common.errors.validation'), 422, 4002, $validated->errors());
        }
        return $this->timesheetRepository->listTimesheets($request);

    }

    /**
     * Store a newly created timesheet in storage.
     *
     * POST /api/timesheets
     */
    public function store(Request $request)
    {
        $rules = [
            'task_name'  => 'required|string',
            'date'       => 'required|date',
            'hours'      => 'required|numeric',
            'user_id'    => 'required|exists:users,id',
            'project_id' => 'required|exists:projects,id',
        ];

        $validated = $this->validated($rules, $request->all());
        if ($validated->fails()) {
            return ResponseHandler::error(__('common.errors.validation'), 422, 4002, $validated->errors());
        }
        return $this->timesheetRepository->createTimesheet($validated->validated());

    }

    /**
     * Display the specified timesheet.
     *
     * GET /api/timesheets/{id}
     */
    public function show($id, Request $request)
    {
        $request->merge(['id' => $id]);
        $rules = [
            'id'       => 'required|integer|exists:timesheets,id',
        ];

        $validated = $this->validated($rules, $request->all());
        if ($validated->fails()) {
            return ResponseHandler::error(__('common.errors.validation'), 422, 5011, $validated->errors());
        }
        return $this->timesheetRepository->showTimesheet($validated->validated());
    }

    /**
     * Update the specified timesheet in storage.
     *
     * PUT /api/timesheets/{id}
     */
    public function update(Request $request, $id)
    {
        $timesheet = Timesheets::find($id);
        if (!$timesheet) {
            return ResponseHandler::error('common.not_found', 404, 5004);
        }

        $rules = [
            'task_name'  => 'sometimes|required|string',
            'date'       => 'sometimes|required|date',
            'hours'      => 'sometimes|required|numeric',
            'user_id'    => 'sometimes|required|exists:users,id',
            'project_id' => 'sometimes|required|exists:projects,id',
        ];

        $validator = $this->validated($request->all(), $rules);
        if ($validator->fails()) {
            return ResponseHandler::error(__('common.errors.validation'), 422, 5005, $validator->errors());
        }

        $timesheet->update($validator->validated());
        return ResponseHandler::success($timesheet, __('common.success'));
    }

    /**
     * Remove the specified timesheet from storage.
     *
     * DELETE /api/timesheets/{id}
     */
    public function destroy(string $id, Request $request)
    {
        $request->merge(['id' => $id]);
        $rules = [
            'id'       => 'required|integer|exists:timesheets,id',
        ];

        $validated = $this->validated($rules, $request->all());
        if ($validated->fails()) {
            return ResponseHandler::error(__('common.errors.validation'), 422, 3011, $validated->errors());
        }
        return $this->timesheetRepository->deleteTimesheet($validated->validated());
    }
}
