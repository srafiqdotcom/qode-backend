<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;
    public string $otpCode;
    public string $purpose;

    public function __construct(User $user, string $otpCode, string $purpose = 'login')
    {
        $this->user = $user;
        $this->otpCode = $otpCode;
        $this->purpose = $purpose;
    }

    public function envelope(): Envelope
    {
        $subject = match($this->purpose) {
            'login' => 'Your Login OTP Code',
            'registration' => 'Verify Your Email Address',
            'password_reset' => 'Password Reset OTP',
            default => 'Your OTP Code'
        };

        return new Envelope(
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.otp',
            with: [
                'user' => $this->user,
                'otpCode' => $this->otpCode,
                'purpose' => $this->purpose,
                'expiryMinutes' => config('otp.expiry_minutes', 10),
                'appName' => config('app.name'),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
