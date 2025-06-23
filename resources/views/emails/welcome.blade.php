<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to {{ $appName }}</title>
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
            font-size: 28px;
            font-weight: bold;
            color: #2563eb;
            margin-bottom: 10px;
        }
        .welcome-banner {
            background: linear-gradient(135deg, #2563eb, #3b82f6);
            color: white;
            padding: 30px;
            border-radius: 10px;
            text-align: center;
            margin: 20px 0;
        }
        .feature-list {
            background-color: #f8fafc;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .feature-item {
            display: flex;
            align-items: center;
            margin: 15px 0;
        }
        .feature-icon {
            width: 20px;
            height: 20px;
            background-color: #10b981;
            border-radius: 50%;
            margin-right: 15px;
            flex-shrink: 0;
        }
        .button {
            display: inline-block;
            padding: 12px 24px;
            background-color: #2563eb;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 5px;
            text-align: center;
        }
        .button-secondary {
            background-color: #6b7280;
        }
        .cta-section {
            text-align: center;
            margin: 30px 0;
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
        </div>

        <div class="welcome-banner">
            <h1 style="margin: 0; font-size: 24px;">Welcome to {{ $appName }}!</h1>
            <p style="margin: 10px 0 0 0; opacity: 0.9;">We're excited to have you join our community</p>
        </div>

        <p>Hello {{ $user->name }},</p>

        <p>Welcome to {{ $appName }}! We're thrilled that you've decided to join our community of readers and writers. Your account has been successfully created and you're all set to start exploring.</p>

        <div class="feature-list">
            <h3 style="margin-top: 0; color: #1f2937;">What you can do with your account:</h3>
            
            <div class="feature-item">
                <div class="feature-icon"></div>
                <div>
                    <strong>Read Amazing Content:</strong> Discover insightful blog posts from talented authors
                </div>
            </div>
            
            <div class="feature-item">
                <div class="feature-icon"></div>
                <div>
                    <strong>Engage with Comments:</strong> Share your thoughts and join discussions
                </div>
            </div>
            
            @if($user->role === 'author')
            <div class="feature-item">
                <div class="feature-icon"></div>
                <div>
                    <strong>Publish Your Stories:</strong> Share your knowledge and experiences with the world
                </div>
            </div>
            
            <div class="feature-item">
                <div class="feature-icon"></div>
                <div>
                    <strong>Manage Your Content:</strong> Edit, schedule, and organize your blog posts
                </div>
            </div>
            @endif
            
            <div class="feature-item">
                <div class="feature-icon"></div>
                <div>
                    <strong>Personalized Experience:</strong> Get notifications and customize your preferences
                </div>
            </div>
        </div>

        <div class="cta-section">
            <h3>Ready to get started?</h3>
            <p>Explore our latest content and become part of the conversation!</p>
            
            <a href="{{ $blogUrl }}" class="button">Browse Blog Posts</a>
            @if($user->role === 'author')
                <a href="{{ $appUrl }}/dashboard" class="button button-secondary">Go to Dashboard</a>
            @endif
        </div>

        <p>If you have any questions or need help getting started, don't hesitate to reach out to our support team. We're here to help!</p>

        <p>Once again, welcome to {{ $appName }}. We can't wait to see what you'll discover and contribute to our community.</p>

        <div class="footer">
            <p>Happy reading and writing!</p>
            <p><strong>The {{ $appName }} Team</strong></p>
            <p>© {{ date('Y') }} {{ $appName }}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
