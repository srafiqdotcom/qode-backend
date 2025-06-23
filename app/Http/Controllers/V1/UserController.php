<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller as Controller;
use App\Models\User;
use App\Utilities\ResponseHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Repositories\V1\UserRepository;
use stdClass;

class UserController extends Controller
{
    protected UserRepository $userRepository;

    /**
     * AuthController constructor.
     *
     * @param UserRepository $userRepository
     * @param Request $request
     */
    public function __construct(UserRepository $userRepository, Request $request)
    {
        parent::__construct($request);
        $this->userRepository = $userRepository;
    }
    /**
     * Display a listing of the resource.
     * GET /api/users
     */
    public function index(Request $request)
    {
        $rules = [
            'filters'                => 'sometimes|array',

            'filters.name'           => 'sometimes',
            'filters.name.like'      => 'sometimes|string',
            'filters.name.='         => 'sometimes|string',

            'filters.email'          => 'sometimes|string',
            'filters.email.like'     => 'sometimes|string',
            'filters.email.='        => 'sometimes|string',

            'order_by'               => 'sometimes|in:first_name,last_name,email,id',
            'order'                  => 'sometimes|in:asc,desc',

            'rpp'                    => 'sometimes|integer|min:1',
            'page'                => 'sometimes|integer|min:1',
        ];

        $validated = $this->validated($rules, $request->all());
        if ($validated->fails()) {
            return ResponseHandler::error(__('common.errors.validation'), 422, 2001, $validated->errors());
        }
        return $this->userRepository->userListing($request);
    }

    /**
     * Store a newly created resource in storage.
     * POST /api/users
     */
    public function store(Request $request)
    {
        $rules = [
            'first_name' => 'sometimes|required|regex:/^\\S+$/',
            'last_name'  => 'sometimes|required|regex:/^\\S+$/',
            'email'      => 'required|email|unique:users,email',
            'password'   => 'required|min:6',
        ];

        $validated = $this->validated($rules, $request->all());
        if ($validated->fails()) {
            return ResponseHandler::error(__('common.errors.validation'), 422, 2001, $validated->errors());
        }
        $validatedData = $validated->validated();
        // Hash the password before db interpretation
        $validatedData['password'] = Hash::make($validatedData['password']);

        return $this->userRepository->createUser($validatedData);
    }

    /**
     * Display the specified resource.
     * GET /api/users/{id}
     */
    public function show($id, Request $request)
    {

        $request->merge(['id' => $id]);
        $rules = [
            'id'       => 'required|integer|exists:users,id',
        ];

        $validated = $this->validated($rules, $request->all());
        if ($validated->fails()) {
            return ResponseHandler::error(__('common.errors.validation'), 422, 3011, $validated->errors());
        }
        return $this->userRepository->showUser($validated->validated());
    }

    /**
     * Update the specified resource in storage.
     * PUT /api/users/{id}
     */
    public function update(Request $request, string $id)
    {
        $request->merge(['id' => $id]);

        $rules = [
            'id' => 'required|integer|exists:users,id',
            'first_name' => 'sometimes|required|regex:/^\\S+$/',
            'last_name'  => 'sometimes|required|regex:/^\\S+$/',
            'email'      => 'sometimes|required|email|unique:users,email,' . $request->input('id'),
            'password'   => 'sometimes|required|min:6',
        ];

        $validated = $this->validated($rules, $request->all());
        if ($validated->fails()) {
            return ResponseHandler::error(__('common.errors.validation'), 422, 3001, $validated->errors());
        }

        $validatedData = $validated->validated();
        // Hash the password before db interpretation
        if (isset($validatedData['password'])) {
            $validatedData['password'] = Hash::make($validatedData['password']);
        }

        return $this->userRepository->updateUser($validatedData);
    }

    /**
     * Remove the specified resource from storage.
     * DELETE /api/users/{id}
     */
    public function destroy(string $id, Request $request)
    {
        $request->merge(['id' => $id]);
        $rules = [
            'id'       => 'required|integer|exists:users,id',
        ];

        $validated = $this->validated($rules, $request->all());
        if ($validated->fails()) {
            return ResponseHandler::error(__('common.errors.validation'), 422, 3011, $validated->errors());
        }
        return $this->userRepository->deleteUser($validated->validated());

    }
}
