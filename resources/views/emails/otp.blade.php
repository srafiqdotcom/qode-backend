<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Your OTP Code</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .otp-code {
            background-color: #f8f9fa;
            border: 2px solid #6366f1;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            margin: 20px 0;
        }
        .otp-number {
            font-size: 32px;
            font-weight: bold;
            color: #6366f1;
            letter-spacing: 4px;
            margin: 10px 0;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            font-size: 14px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Login Verification</h1>
    </div>

    <p>Hello {{ $user->name }},</p>

    <p>You have requested to log in to your account. Please use the following OTP code to complete your login:</p>

    <div class="otp-code">
        <p>Your OTP Code:</p>
        <div class="otp-number">{{ $otpCode }}</div>
        <p><strong>This code will expire in {{ $expiryMinutes }} minutes.</strong></p>
    </div>

    <p>If you did not request this login, please ignore this email or contact support if you have concerns.</p>

    <p>For security reasons, please do not share this code with anyone.</p>

    <div class="footer">
        <p>Best regards,<br>{{ config('app.name') }} Team</p>
        <p><small>This is an automated email. Please do not reply to this message.</small></p>
    </div>
</body>
</html>
