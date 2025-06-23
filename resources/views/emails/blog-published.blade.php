<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Blog Post Published</title>
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
        .blog-preview {
            background-color: #f8fafc;
            border-left: 4px solid #2563eb;
            padding: 20px;
            margin: 20px 0;
            border-radius: 0 8px 8px 0;
        }
        .blog-title {
            font-size: 20px;
            font-weight: bold;
            color: #1f2937;
            margin-bottom: 10px;
        }
        .blog-excerpt {
            color: #6b7280;
            margin-bottom: 15px;
        }
        .blog-meta {
            font-size: 14px;
            color: #9ca3af;
            margin-bottom: 15px;
        }
        .button {
            display: inline-block;
            padding: 12px 24px;
            background-color: #2563eb;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 0;
        }
        .tags {
            margin: 10px 0;
        }
        .tag {
            display: inline-block;
            background-color: #e5e7eb;
            color: #374151;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            margin-right: 5px;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            font-size: 12px;
            color: #666;
            border-top: 1px solid #e5e7eb;
            padding-top: 20px;
        }
        .unsubscribe {
            margin-top: 20px;
            font-size: 11px;
            color: #9ca3af;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">{{ $appName }}</div>
            <h1>Your Blog Post Has Been Published!</h1>
        </div>

        <p>Hello {{ $subscriber->name }},</p>

        <p>Great news! Your blog post has been successfully published:</p>

        <div class="blog-preview">
            <div class="blog-title">{{ $blog->title }}</div>
            
            <div class="blog-meta">
                By {{ $blog->author->name }} • Published {{ $blog->published_at->format('M j, Y') }}
            </div>

            <div class="blog-excerpt">
                {{ $blog->excerpt }}
            </div>

            @if($blog->tags->count() > 0)
                <div class="tags">
                    @foreach($blog->tags as $tag)
                        <span class="tag">{{ $tag->name }}</span>
                    @endforeach
                </div>
            @endif

            <a href="{{ $blogUrl }}" class="button">Read Full Post</a>
        </div>

        <p>Your blog post is now live and ready for readers to discover and engage with!</p>

        <div class="footer">
            <p>Thank you for creating great content!</p>
            <p>© {{ date('Y') }} {{ $appName }}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
