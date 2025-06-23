<?php

namespace App\Repositories\V1;

use App\Models\User;
use App\Models\Otp;
use App\Services\OtpService;
use App\Utilities\ResponseHandler;
use Illuminate\Http\Request;


class AuthRepository extends BaseRepository
{
    protected string $logChannel;
    protected OtpService $otpService;

    public function __construct(Request $request, User $user, OtpService $otpService)
    {
        parent::__construct($user);
        $this->logChannel = 'auth_logs';
        $this->otpService = $otpService;
    }

    public function registerUser(array $validatedRequest)
    {
        try {
            $user = $this->model::create([
                'name' => $validatedRequest['name'],
                'email' => $validatedRequest['email'],
                'role' => $validatedRequest['role'] ?? 'reader',
            ]);

            $dataToReturn['user'] = $user;
            $dataToReturn['message'] = 'Registration successful. Please check your email for OTP to complete login.';

            $this->otpService->generateAndSendOtp($user, 'registration');

            return ResponseHandler::success($dataToReturn, __('common.success'));
        } catch (\Exception $e) {
            $this->logData($this->logChannel, $this->prepareExceptionLog($e), 'error');
            return ResponseHandler::error($this->prepareExceptionLog($e), 500, 14);
        }
    }

    public function requestOtp(array $validatedRequest)
    {
        try {
            $user = $this->model::where('email', $validatedRequest['email'])->first();

            if (!$user) {
                return ResponseHandler::error('User not found with this email address.', 404, 15);
            }

            $otpSent = $this->otpService->generateAndSendOtp($user, 'login');

            if (!$otpSent) {
                return ResponseHandler::error('Failed to send OTP. Please try again.', 500, 16);
            }

            $dataToReturn = [
                'message' => 'OTP sent successfully to your email address.',
                'email' => $user->email,
            ];

            return ResponseHandler::success($dataToReturn, __('common.success'));
        } catch (\Exception $e) {
            $this->logData($this->logChannel, $this->prepareExceptionLog($e), 'error');
            return ResponseHandler::error($this->prepareExceptionLog($e), 500, 17);
        }
    }

    public function verifyOtp(array $validatedRequest)
    {
        try {
            $user = $this->model::where('email', $validatedRequest['email'])->first();

            if (!$user) {
                return ResponseHandler::error('User not found with this email address.', 404, 18);
            }

            $purpose = $validatedRequest['purpose'] ?? 'login';
            $isValidOtp = $this->otpService->verifyOtp($user, $validatedRequest['otp_code'], $purpose);

            if (!$isValidOtp) {
                return ResponseHandler::error('Invalid or expired OTP code.', 401, 19);
            }

            $dataToReturn['token'] = $user->createToken('authToken')->accessToken;
            $dataToReturn['user'] = $user;
            $dataToReturn['message'] = 'Login successful.';

            return ResponseHandler::success($dataToReturn, __('common.success'));
        } catch (\Exception $e) {
            $this->logData($this->logChannel, $this->prepareExceptionLog($e), 'error');
            return ResponseHandler::error($this->prepareExceptionLog($e), 500, 20);
        }
    }

    public function logoutUser()
    {
        try {
            auth()->guard('api')->user()->token()->revoke();

            return ResponseHandler::success([], __('common.success'));
        } catch (\Exception $e) {
            $this->logData($this->logChannel, $this->prepareExceptionLog($e), 'error');
            return ResponseHandler::error($this->prepareExceptionLog($e), 500, 21);
        }
    }

}
