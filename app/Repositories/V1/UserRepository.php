<?php

namespace App\Repositories\V1;

use App\Models\User;
use App\Services\EmailService;
use App\Utilities\ResponseHandler;
use Illuminate\Http\Request;
use App\Utilities\FilterHelper;
use Illuminate\Support\Facades\Hash;

class UserRepository extends BaseRepository
{
    protected string $logChannel;
    protected EmailService $emailService;

    public function __construct(Request $request, User $users, EmailService $emailService)
    {
        parent::__construct($users);
        $this->logChannel = 'user_logs';
        $this->emailService = $emailService;
    }

    public function userListing($request)
    {
        try {
            $query = $this->model::query();

            $allowedColumns = ['first_name','last_name', 'email'];
            $allowedOperators = ['=', 'like'];

            // Get filters from request. Example:
            // filters[name][like]=%sha%
            // filters[email]=testmail.com
            $filters = $request->input('filters', []);

            // Process filters:
            foreach ($filters as $field => $criteria) {
                if ($field === 'name') {
                    // Special case: if filtering by "name", search both first_name and last_name.
                    if (is_array($criteria)) {
                        foreach ($criteria as $operator => $value) {
                            if (in_array(strtolower($operator), $allowedOperators)) {
                                $op = (strtolower($operator) === 'like') ? 'LIKE' : '=';
                                $query->where(function ($q) use ($op, $value) {
                                    $q->where('first_name', $op, $value)
                                        ->orWhere('last_name', $op, $value);
                                });
                            }
                        }
                    } else {
                        // Default: search for equality.
                        $query->where(function ($q) use ($criteria) {
                            $q->where('first_name', '=', $criteria)
                                ->orWhere('last_name', '=', $criteria);
                        });
                    }
                } elseif (in_array($field, $allowedColumns)) {
                    // For allowed columns (email, first_name, last_name), apply filtering.
                    if (is_array($criteria)) {
                        foreach ($criteria as $operator => $value) {
                            if (in_array(strtolower($operator), $allowedOperators)) {
                                $op = (strtolower($operator) === 'like') ? 'LIKE' : '=';
                                $query->where($field, $op, $value);
                            }
                        }
                    } else {
                        $query->where($field, '=', $criteria);
                    }
                }
                // You can add additional fields/logic here if needed.
            }
            //adding id for ordering
            $allowedColumns[] = "id";
            $orderBy = $request->input('order_by');
            $order = $request->input('order', 'asc'); // default order is ascending

            if ($orderBy && in_array($orderBy, $allowedColumns)) {
                $query->orderBy($orderBy, $order);
            }

            // Pagination: Use 'rpp' (records per page) with default of 10, and 'page_no' (default 1).
            $rpp = $request->input('rpp', 10);


            $users = $query->paginate($rpp);



            return ResponseHandler::success($users, __('common.success'));
        } catch (\Exception $e) {
            $this->logData($this->logChannel, $this->prepareExceptionLog($e), 'error');
            return ResponseHandler::error($this->prepareExceptionLog($e), 500, 24);
        }
    }

    public function createUser(array $validatedRequest)
    {
        try {
            // Create the project
            $user = $this->model::create([
                'name' => $validatedRequest['first_name'] . ' ' . $validatedRequest['last_name'],
                'email' => $validatedRequest['email'],
                'password' => $validatedRequest['password'],
            ]);

            $this->emailService->sendWelcomeEmail($user);

            return ResponseHandler::success($user, __('common.success'));

        } catch (\Exception $e) {
            $this->logData($this->logChannel, $this->prepareExceptionLog($e), 'error');
            return ResponseHandler::error($this->prepareExceptionLog($e), 500, 26);
        }
    }

    public function updateUser(array $validatedRequest)
    {
        try {
            $user = $this->model->find($validatedRequest['id']);
            if (!$user) {
                return ResponseHandler::error(__('common.not_found'), 404, 3005);
            }
            if (isset($validatedRequest['first_name']) || isset($validatedRequest['last_name'])) {

                // 1. Retrieve the existing 'name' from DB
                $existingName = $user->name;

                // 2. Split it into first/last name
                //    (We assume your name is stored as exactly "first_name last_name" with a single space.)
                $nameParts = explode(' ', $existingName, 2);

                // To avoid indexing issues, let's define them carefully:
                $firstNameFromDb = $nameParts[0] ?? '';
                $lastNameFromDb = $nameParts[1] ?? '';

                // 3. Override if user provided either part
                $newFirstName = $validatedRequest['first_name'] ?? $firstNameFromDb;
                $newLastName = $validatedRequest['last_name'] ?? $lastNameFromDb;

                // 4. Combine them back
                $validatedRequest['name'] = trim($newFirstName . ' ' . $newLastName);
                unset($validatedRequest['first_name']);
                unset($validatedRequest['last_name']);
            }
            // update the the user
            $this->model->update($validatedRequest);
            unset($validatedRequest['password']);
            return ResponseHandler::success($validatedRequest, __('common.success'));

        } catch (\Exception $e) {
            $this->logData($this->logChannel, $this->prepareExceptionLog($e), 'error');
            return ResponseHandler::error($this->prepareExceptionLog($e), 500, 26);
        }
    }

    public function showUser(array $validatedRequest)
    {
        try {

            $user = $this->model::find($validatedRequest['id']);

            if (!$user) {
                return ResponseHandler::error(__('common.not_found'), 404, 3005);
            }

            return ResponseHandler::success($user, __('common.success'));

        } catch (\Exception $e) {
            $this->logData($this->logChannel, $this->prepareExceptionLog($e), 'error');
            return ResponseHandler::error($this->prepareExceptionLog($e), 500, 26);
        }
    }


    public function deleteUser(array $validatedRequest)
    {
        try {
            $user = $this->model::find($validatedRequest['id']);
            if (!$user) {
                return ResponseHandler::error(__('common.not_found'), 404, 3015);
            }
            $user->delete();
            return ResponseHandler::success([], __('common.success'));
        } catch (\Exception $e) {
            $this->logData($this->logChannel, $this->prepareExceptionLog($e), 'error');
            return ResponseHandler::error($this->prepareExceptionLog($e), 500, 26);
        }
    }
}
