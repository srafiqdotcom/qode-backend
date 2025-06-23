<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class MarkdownService
{
    protected array $allowedTags;
    protected array $allowedAttributes;

    public function __construct()
    {
        $this->allowedTags = [
            'p', 'br', 'strong', 'em', 'u', 'strike', 'del',
            'blockquote', 'code', 'pre', 'ul', 'ol', 'li',
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
            'a', 'img'
        ];

        $this->allowedAttributes = [
            'a' => ['href', 'title', 'target'],
            'img' => ['src', 'alt', 'title', 'width', 'height'],
            'blockquote' => ['cite'],
        ];
    }

    public function processComment(string $content): string
    {
        try {
            $processed = $this->convertMarkdownToHtml($content);
            $sanitized = $this->sanitizeHtml($processed);
            return $this->addSecurityAttributes($sanitized);
        } catch (\Exception $e) {
            Log::error('Markdown processing failed', [
                'error' => $e->getMessage(),
                'content_length' => strlen($content)
            ]);
            return htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
        }
    }

    public function processBlogContent(string $content): string
    {
        try {
            return $this->convertMarkdownToHtml($content, true);
        } catch (\Exception $e) {
            Log::error('Blog markdown processing failed', [
                'error' => $e->getMessage(),
                'content_length' => strlen($content)
            ]);
            return htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
        }
    }

    public function sanitizeContent(string $content): string
    {
        $content = trim($content);
        
        $content = preg_replace('/\r\n|\r/', "\n", $content);
        
        $content = preg_replace('/\n{3,}/', "\n\n", $content);
        
        $content = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
        
        return $content;
    }

    private function convertMarkdownToHtml(string $markdown, bool $allowAll = false): string
    {
        $html = $markdown;

        $html = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $html);
        $html = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $html);
        $html = preg_replace('/__(.*?)__/', '<strong>$1</strong>', $html);
        $html = preg_replace('/_(.*?)_/', '<em>$1</em>', $html);

        $html = preg_replace('/~~(.*?)~~/', '<del>$1</del>', $html);

        $html = preg_replace('/`([^`]+)`/', '<code>$1</code>', $html);

        if ($allowAll) {
            $html = preg_replace('/^### (.*$)/m', '<h3>$1</h3>', $html);
            $html = preg_replace('/^## (.*$)/m', '<h2>$1</h2>', $html);
            $html = preg_replace('/^# (.*$)/m', '<h1>$1</h1>', $html);
        }

        $html = preg_replace_callback('/\[([^\]]+)\]\(([^)]+)\)/', function ($matches) {
            $text = htmlspecialchars($matches[1], ENT_QUOTES, 'UTF-8');
            $url = $this->sanitizeUrl($matches[2]);
            return "<a href=\"{$url}\" target=\"_blank\" rel=\"noopener noreferrer\">{$text}</a>";
        }, $html);

        $html = preg_replace('/^> (.*)$/m', '<blockquote>$1</blockquote>', $html);

        $html = preg_replace('/\n\n+/', '</p><p>', $html);
        $html = '<p>' . $html . '</p>';

        $html = preg_replace('/<p><\/p>/', '', $html);
        $html = preg_replace('/<p>(<blockquote>.*?<\/blockquote>)<\/p>/', '$1', $html);
        $html = preg_replace('/<p>(<h[1-6]>.*?<\/h[1-6]>)<\/p>/', '$1', $html);

        return $html;
    }

    private function sanitizeHtml(string $html): string
    {
        $config = \HTMLPurifier_Config::createDefault();
        
        $config->set('HTML.Allowed', implode(',', $this->allowedTags));
        
        foreach ($this->allowedAttributes as $tag => $attributes) {
            $config->set("HTML.AllowedAttributes.{$tag}", implode(',', $attributes));
        }
        
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true]);
        $config->set('Attr.AllowedFrameTargets', ['_blank']);
        $config->set('HTML.Nofollow', true);
        $config->set('HTML.TargetBlank', true);
        
        $purifier = new \HTMLPurifier($config);
        return $purifier->purify($html);
    }

    private function addSecurityAttributes(string $html): string
    {
        $html = preg_replace('/<a\s+([^>]*?)href="([^"]*)"([^>]*?)>/', 
                           '<a $1href="$2"$3 rel="noopener noreferrer nofollow">', $html);
        
        return $html;
    }

    private function sanitizeUrl(string $url): string
    {
        $url = trim($url);
        
        if (!preg_match('/^https?:\/\//', $url) && !preg_match('/^mailto:/', $url)) {
            if (filter_var($url, FILTER_VALIDATE_EMAIL)) {
                $url = 'mailto:' . $url;
            } else {
                $url = 'https://' . ltrim($url, '/');
            }
        }
        
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return '#';
        }
        
        return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    }

    public function extractPlainText(string $content): string
    {
        $html = $this->convertMarkdownToHtml($content);
        return strip_tags($html);
    }

    public function truncateContent(string $content, int $length = 150): string
    {
        $plainText = $this->extractPlainText($content);
        
        if (strlen($plainText) <= $length) {
            return $plainText;
        }
        
        $truncated = substr($plainText, 0, $length);
        $lastSpace = strrpos($truncated, ' ');
        
        if ($lastSpace !== false) {
            $truncated = substr($truncated, 0, $lastSpace);
        }
        
        return $truncated . '...';
    }

    public function validateMarkdown(string $content): array
    {
        $errors = [];
        
        if (strlen($content) > 5000) {
            $errors[] = 'Content is too long (maximum 5000 characters)';
        }
        
        if (preg_match_all('/\[([^\]]+)\]\(([^)]+)\)/', $content, $matches)) {
            foreach ($matches[2] as $url) {
                $sanitizedUrl = $this->sanitizeUrl($url);
                if ($sanitizedUrl === '#') {
                    $errors[] = "Invalid URL found: {$url}";
                }
            }
        }
        
        $suspiciousPatterns = [
            '/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi',
            '/javascript:/i',
            '/on\w+\s*=/i',
            '/<iframe\b/i',
            '/<object\b/i',
            '/<embed\b/i',
        ];
        
        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                $errors[] = 'Content contains potentially unsafe elements';
                break;
            }
        }
        
        return $errors;
    }
}
