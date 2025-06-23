<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comment Notification</title>
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
        .comment-preview {
            background-color: #f8fafc;
            border-left: 4px solid #10b981;
            padding: 20px;
            margin: 20px 0;
            border-radius: 0 8px 8px 0;
        }
        .comment-content {
            background-color: #ffffff;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
            border: 1px solid #e5e7eb;
        }
        .comment-meta {
            font-size: 14px;
            color: #6b7280;
            margin-bottom: 10px;
        }
        .blog-title {
            font-size: 18px;
            font-weight: bold;
            color: #1f2937;
            margin: 15px 0 10px 0;
        }
        .button {
            display: inline-block;
            padding: 12px 24px;
            background-color: #10b981;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 15px 0;
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
            <h1>
                @if($type === 'new_comment')
                    New Comment on Your Blog
                @elseif($type === 'comment_reply')
                    Someone Replied to Your Comment
                @elseif($type === 'comment_approved')
                    Your Comment Has Been Approved
                @else
                    Comment Notification
                @endif
            </h1>
        </div>

        <p>Hello {{ $recipient->name }},</p>

        @if($type === 'new_comment')
            <p>You have received a new comment on your blog post:</p>
        @elseif($type === 'comment_reply')
            <p>Someone has replied to your comment:</p>
        @elseif($type === 'comment_approved')
            <p>Great news! Your comment has been approved and is now visible to other readers:</p>
        @endif

        <div class="comment-preview">
            <div class="blog-title">{{ $comment->blog->title }}</div>
            
            <div class="comment-meta">
                @if($type === 'comment_approved')
                    Your comment • Posted {{ $comment->created_at->format('M j, Y \a\t g:i A') }}
                @else
                    By {{ $comment->user->name }} • Posted {{ $comment->created_at->format('M j, Y \a\t g:i A') }}
                @endif
            </div>

            <div class="comment-content">
                {{ Str::limit(strip_tags($comment->content), 200) }}
            </div>

            @if($type === 'comment_reply' && $comment->parent)
                <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #e5e7eb;">
                    <div style="font-size: 14px; color: #6b7280; margin-bottom: 10px;">
                        In reply to your comment:
                    </div>
                    <div style="background-color: #f3f4f6; padding: 10px; border-radius: 5px; font-style: italic;">
                        {{ Str::limit(strip_tags($comment->parent->content), 100) }}
                    </div>
                </div>
            @endif

            <a href="{{ $commentUrl }}" class="button">
                @if($type === 'comment_approved')
                    View Your Comment
                @else
                    View Comment
                @endif
            </a>
        </div>

        @if($type === 'new_comment')
            <p>You can reply to this comment or moderate it from your dashboard.</p>
        @elseif($type === 'comment_reply')
            <p>You can reply back or view the full conversation by clicking the link above.</p>
        @elseif($type === 'comment_approved')
            <p>Thank you for contributing to the discussion! Your comment is now live.</p>
        @endif

        <div class="footer">
            <p>© {{ date('Y') }} {{ $appName }}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
