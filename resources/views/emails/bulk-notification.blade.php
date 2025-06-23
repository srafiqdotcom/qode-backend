<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject ?? 'Notification' }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container {
            background-color: #ffffff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo {
            font-size: 24px;
            font-weight: bold;
            color: #2563eb;
            margin-bottom: 10px;
        }
        .message-content {
            background-color: #f8fafc;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #2563eb;
        }
        .button {
            display: inline-block;
            padding: 12px 24px;
            background-color: #2563eb;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 15px 0;
        }
        .data-section {
            background-color: #f9fafb;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
            border: 1px solid #e5e7eb;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            font-size: 12px;
            color: #666;
            border-top: 1px solid #e5e7eb;
            padding-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">{{ $appName }}</div>
            <h1>{{ $subject ?? 'Important Notification' }}</h1>
        </div>

        <p>Hello {{ $user->name }},</p>

        <div class="message-content">
            {!! nl2br(e($message)) !!}
        </div>

        @if(!empty($data))
            <div class="data-section">
                <h3 style="margin-top: 0; color: #1f2937;">Additional Information:</h3>
                
                @foreach($data as $key => $value)
                    <p><strong>{{ ucfirst(str_replace('_', ' ', $key)) }}:</strong> 
                    @if(is_array($value))
                        {{ implode(', ', $value) }}
                    @else
                        {{ $value }}
                    @endif
                    </p>
                @endforeach
            </div>
        @endif

        @if(isset($data['action_url']) && $data['action_url'])
            <div style="text-align: center; margin: 25px 0;">
                <a href="{{ $data['action_url'] }}" class="button">
                    {{ $data['action_text'] ?? 'Take Action' }}
                </a>
            </div>
        @endif

        <p>Thank you for being a valued member of our community.</p>

        <div class="footer">
            <p>Best regards,</p>
            <p><strong>The {{ $appName }} Team</strong></p>
            <p>© {{ date('Y') }} {{ $appName }}. All rights reserved.</p>
            
            @if(isset($data['unsubscribe_url']) && $data['unsubscribe_url'])
                <p style="margin-top: 15px; font-size: 11px; color: #9ca3af;">
                    Don't want to receive these notifications? 
                    <a href="{{ $data['unsubscribe_url'] }}" style="color: #9ca3af;">Unsubscribe here</a>
                </p>
            @endif
        </div>
    </div>
</body>
</html>
