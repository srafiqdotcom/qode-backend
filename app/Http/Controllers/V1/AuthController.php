<?php
namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller as Controller;
use App\Repositories\V1\AuthRepository;
use App\Utilities\ResponseHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    protected AuthRepository $authRepository;

    /**
     * AuthController constructor.
     *
     * @param AuthRepository $authRepository
     */
    public function __construct(AuthRepository $authRepository, Request $request)
    {
        parent::__construct($request);
        $this->authRepository = $authRepository;

    }

    /**
     * Handle user registration.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws ValidationException
     */
    public function register(Request $request): JsonResponse
    {
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'role' => 'sometimes|in:author,reader',
        ];

        $validated = $this->validated($rules, $request->all());

        if ($validated->fails()) {
           return ResponseHandler::error(__('common.errors.validation'), 422, 12, $validated->errors());
        }

        return $this->authRepository->registerUser($validated->validated());
    }

    public function requestOtp(Request $request): JsonResponse
    {
        $rules = [
            'email' => 'required|email|exists:users,email',
        ];

        $validated = $this->validated($rules, $request->all());

        if ($validated->fails()) {
            return ResponseHandler::error(__('common.errors.validation'), 422, 12, $validated->errors());
        }

        return $this->authRepository->requestOtp($validated->validated());
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $rules = [
            'email' => 'required|email|exists:users,email',
            'otp_code' => 'required|string|size:6',
            'purpose' => 'sometimes|in:login,registration',
        ];

        $validated = $this->validated($rules, $request->all());

        if ($validated->fails()) {
            return ResponseHandler::error(__('common.errors.validation'), 422, 13, $validated->errors());
        }

        return $this->authRepository->verifyOtp($validated->validated());
    }

    public function logout(Request $request): JsonResponse
    {
        return $this->authRepository->logoutUser();
    }
}
