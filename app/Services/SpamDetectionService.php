<?php

namespace App\Services;

use App\Models\Comment;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class SpamDetectionService
{
    protected array $suspiciousKeywords;
    protected int $maxUrlsThreshold;
    protected int $duplicateThresholdMinutes;

    public function __construct()
    {
        $this->suspiciousKeywords = config('comments.spam_protection.suspicious_keywords', []);
        $this->maxUrlsThreshold = config('comments.spam_protection.max_urls_threshold', 3);
        $this->duplicateThresholdMinutes = config('comments.spam_protection.duplicate_threshold_minutes', 5);
    }

    public function isSpam(string $content, int $userId, string $ipAddress): array
    {
        $spamScore = 0;
        $reasons = [];

        if (!config('comments.spam_protection.enabled', true)) {
            return ['is_spam' => false, 'score' => 0, 'reasons' => []];
        }

        $keywordScore = $this->checkSuspiciousKeywords($content);
        if ($keywordScore > 0) {
            $spamScore += $keywordScore;
            $reasons[] = 'Contains suspicious keywords';
        }

        $urlScore = $this->checkExcessiveUrls($content);
        if ($urlScore > 0) {
            $spamScore += $urlScore;
            $reasons[] = 'Contains too many URLs';
        }

        $duplicateScore = $this->checkDuplicateContent($content, $userId);
        if ($duplicateScore > 0) {
            $spamScore += $duplicateScore;
            $reasons[] = 'Duplicate or similar content detected';
        }

        $patternScore = $this->checkSpamPatterns($content);
        if ($patternScore > 0) {
            $spamScore += $patternScore;
            $reasons[] = 'Matches known spam patterns';
        }

        $frequencyScore = $this->checkPostingFrequency($userId, $ipAddress);
        if ($frequencyScore > 0) {
            $spamScore += $frequencyScore;
            $reasons[] = 'Posting too frequently';
        }

        $isSpam = $spamScore >= 50;

        if ($isSpam) {
            Log::warning('Spam comment detected', [
                'user_id' => $userId,
                'ip_address' => $ipAddress,
                'spam_score' => $spamScore,
                'reasons' => $reasons,
                'content_length' => strlen($content)
            ]);
        }

        return [
            'is_spam' => $isSpam,
            'score' => $spamScore,
            'reasons' => $reasons,
        ];
    }

    private function checkSuspiciousKeywords(string $content): int
    {
        $content = strtolower($content);
        $score = 0;

        foreach ($this->suspiciousKeywords as $keyword) {
            if (strpos($content, strtolower($keyword)) !== false) {
                $score += 20;
            }
        }

        return min($score, 60);
    }

    private function checkExcessiveUrls(string $content): int
    {
        $urlPattern = '/https?:\/\/[^\s]+/i';
        preg_match_all($urlPattern, $content, $matches);
        $urlCount = count($matches[0]);

        if ($urlCount > $this->maxUrlsThreshold) {
            return min(($urlCount - $this->maxUrlsThreshold) * 15, 45);
        }

        return 0;
    }

    private function checkDuplicateContent(string $content, int $userId): int
    {
        $contentHash = md5(trim(strtolower($content)));
        $cacheKey = "comment_hash:{$userId}:{$contentHash}";

        if (Cache::has($cacheKey)) {
            return 40;
        }

        $recentComments = Comment::where('user_id', $userId)
                                ->where('created_at', '>=', now()->subMinutes($this->duplicateThresholdMinutes))
                                ->pluck('content');

        foreach ($recentComments as $recentContent) {
            $similarity = $this->calculateSimilarity($content, $recentContent);
            if ($similarity > 0.8) {
                return 35;
            }
        }

        Cache::put($cacheKey, true, $this->duplicateThresholdMinutes * 60);

        return 0;
    }

    private function checkSpamPatterns(string $content): int
    {
        $spamPatterns = [
            '/\b(click here|click now)\b/i' => 15,
            '/\b(free money|easy money|make money fast)\b/i' => 20,
            '/\b(winner|congratulations|you have won)\b/i' => 15,
            '/\b(urgent|act now|limited time)\b/i' => 10,
            '/(.)\1{10,}/' => 25, // Repeated characters
            '/[A-Z]{10,}/' => 15, // Excessive caps
            '/\b\d{10,}\b/' => 10, // Long numbers (phone numbers)
        ];

        $score = 0;
        foreach ($spamPatterns as $pattern => $points) {
            if (preg_match($pattern, $content)) {
                $score += $points;
            }
        }

        return min($score, 50);
    }

    private function checkPostingFrequency(int $userId, string $ipAddress): int
    {
        $minTimeBetween = config('comments.spam_protection.min_time_between_comments', 30);
        
        $lastComment = Comment::where('user_id', $userId)
                             ->orderBy('created_at', 'desc')
                             ->first();

        if ($lastComment && $lastComment->created_at->diffInSeconds(now()) < $minTimeBetween) {
            return 30;
        }

        $recentCommentsFromIp = Comment::where('ip_address', $ipAddress)
                                     ->where('created_at', '>=', now()->subMinutes(10))
                                     ->count();

        if ($recentCommentsFromIp > 5) {
            return 25;
        }

        return 0;
    }

    private function calculateSimilarity(string $str1, string $str2): float
    {
        $str1 = strtolower(trim($str1));
        $str2 = strtolower(trim($str2));

        if ($str1 === $str2) {
            return 1.0;
        }

        $len1 = strlen($str1);
        $len2 = strlen($str2);

        if ($len1 === 0 || $len2 === 0) {
            return 0.0;
        }

        $maxLen = max($len1, $len2);
        $distance = levenshtein($str1, $str2);

        return 1 - ($distance / $maxLen);
    }

    public function markAsSpam(int $commentId, array $reasons): void
    {
        Log::info('Comment marked as spam', [
            'comment_id' => $commentId,
            'reasons' => $reasons,
            'timestamp' => now()
        ]);

        Cache::put("spam_comment:{$commentId}", [
            'marked_at' => now(),
            'reasons' => $reasons
        ], 86400);
    }

    public function isUserSuspicious(int $userId): bool
    {
        $recentSpamCount = Cache::get("user_spam_count:{$userId}", 0);
        return $recentSpamCount >= 3;
    }

    public function incrementUserSpamCount(int $userId): void
    {
        $key = "user_spam_count:{$userId}";
        $count = Cache::get($key, 0);
        Cache::put($key, $count + 1, 86400);
    }
}
