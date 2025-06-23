<?php

namespace App\Services;

use App\Models\Blog;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class SearchService
{
    protected string $indexPrefix;
    protected array $searchableFields;
    protected int $defaultLimit;

    public function __construct()
    {
        $this->indexPrefix = config('app.name', 'blog') . ':search:';
        $this->searchableFields = ['title', 'excerpt', 'description', 'keywords'];
        $this->defaultLimit = 50;
    }

    public function indexBlog(Blog $blog): bool
    {
        try {
            if (!$blog->isPublished()) {
                return $this->removeBlogFromIndex($blog->id);
            }

            $searchData = $this->prepareBlogSearchData($blog);
            
            $blogKey = $this->indexPrefix . 'blogs:' . $blog->id;
            Redis::hmset($blogKey, $searchData);
            Redis::expire($blogKey, config('cache.ttl.search', 3600));

            $this->indexBlogTerms($blog, $searchData);
            $this->indexBlogTags($blog);
            $this->indexBlogAuthor($blog);

            Log::info('Blog indexed for search', ['blog_id' => $blog->id]);
            return true;
        } catch (\Exception $e) {
            Log::error('Blog search indexing failed', [
                'blog_id' => $blog->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function removeBlogFromIndex(int $blogId): bool
    {
        try {
            $blogKey = $this->indexPrefix . 'blogs:' . $blogId;
            
            $searchData = Redis::hgetall($blogKey);
            if (!empty($searchData)) {
                $this->removeTermsFromIndex($blogId, $searchData);
                $this->removeTagsFromIndex($blogId);
                $this->removeAuthorFromIndex($blogId);
            }

            Redis::del($blogKey);

            Log::info('Blog removed from search index', ['blog_id' => $blogId]);
            return true;
        } catch (\Exception $e) {
            Log::error('Blog search removal failed', [
                'blog_id' => $blogId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function search(string $query, array $filters = [], int $limit = null): array
    {
        try {
            $limit = $limit ?? $this->defaultLimit;
            $query = $this->sanitizeQuery($query);
            
            if (empty($query)) {
                return $this->getRecentBlogs($limit);
            }

            $blogIds = $this->searchByTerms($query, $filters, $limit * 3);
            
            if (empty($blogIds)) {
                return [];
            }

            $blogs = $this->getBlogsFromIndex($blogIds, $limit);
            $results = $this->formatSearchResults($blogs, $query);

            Log::info('Search performed', [
                'query' => $query,
                'filters' => $filters,
                'results_count' => count($results)
            ]);

            return $results;
        } catch (\Exception $e) {
            Log::error('Search failed', [
                'query' => $query,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    public function searchByTag(string $tagSlug, int $limit = null): array
    {
        try {
            $limit = $limit ?? $this->defaultLimit;
            $tagKey = $this->indexPrefix . 'tags:' . $tagSlug;
            
            $blogIds = Redis::zrevrange($tagKey, 0, $limit - 1);
            
            if (empty($blogIds)) {
                return [];
            }

            return $this->getBlogsFromIndex($blogIds, $limit);
        } catch (\Exception $e) {
            Log::error('Tag search failed', [
                'tag_slug' => $tagSlug,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    public function searchByAuthor(int $authorId, int $limit = null): array
    {
        try {
            $limit = $limit ?? $this->defaultLimit;
            $authorKey = $this->indexPrefix . 'authors:' . $authorId;
            
            $blogIds = Redis::zrevrange($authorKey, 0, $limit - 1);
            
            if (empty($blogIds)) {
                return [];
            }

            return $this->getBlogsFromIndex($blogIds, $limit);
        } catch (\Exception $e) {
            Log::error('Author search failed', [
                'author_id' => $authorId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    public function getSearchSuggestions(string $query, int $limit = 10): array
    {
        try {
            $query = $this->sanitizeQuery($query);
            $suggestions = [];

            if (strlen($query) < 2) {
                return [];
            }

            $termKey = $this->indexPrefix . 'suggestions:' . strtolower($query[0]);
            $allSuggestions = Redis::zrevrange($termKey, 0, -1, 'WITHSCORES');

            foreach ($allSuggestions as $suggestion => $score) {
                if (stripos($suggestion, $query) === 0) {
                    $suggestions[] = [
                        'term' => $suggestion,
                        'score' => $score
                    ];
                }
                
                if (count($suggestions) >= $limit) {
                    break;
                }
            }

            return $suggestions;
        } catch (\Exception $e) {
            Log::error('Search suggestions failed', [
                'query' => $query,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    public function highlightSearchTerms(string $content, string $query): string
    {
        $terms = $this->extractSearchTerms($query);
        
        foreach ($terms as $term) {
            if (strlen($term) < 2) continue;
            
            $pattern = '/\b(' . preg_quote($term, '/') . ')\b/i';
            $content = preg_replace($pattern, '<mark class="search-highlight">$1</mark>', $content);
        }

        return $content;
    }

    public function rebuildIndex(): bool
    {
        try {
            $this->clearSearchIndex();
            
            $blogs = Blog::with(['author', 'tags'])
                        ->published()
                        ->chunk(100, function ($blogs) {
                            foreach ($blogs as $blog) {
                                $this->indexBlog($blog);
                            }
                        });

            Log::info('Search index rebuilt successfully');
            return true;
        } catch (\Exception $e) {
            Log::error('Search index rebuild failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function clearSearchIndex(): bool
    {
        try {
            $pattern = $this->indexPrefix . '*';
            $keys = Redis::keys($pattern);
            
            if (!empty($keys)) {
                Redis::del($keys);
            }

            Log::info('Search index cleared');
            return true;
        } catch (\Exception $e) {
            Log::error('Search index clear failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    private function prepareBlogSearchData(Blog $blog): array
    {
        $blog->load(['author', 'tags']);
        
        return [
            'id' => $blog->id,
            'title' => $blog->title,
            'excerpt' => $blog->excerpt,
            'description' => strip_tags($blog->description),
            'keywords' => is_array($blog->keywords) ? implode(' ', $blog->keywords) : '',
            'author_name' => $blog->author->name,
            'author_id' => $blog->author_id,
            'tags' => $blog->tags->pluck('name')->implode(' '),
            'tag_slugs' => $blog->tags->pluck('slug')->implode(','),
            'published_at' => $blog->published_at->timestamp,
            'views_count' => $blog->views_count,
            'comments_count' => $blog->comments_count,
            'slug' => $blog->slug,
            'uuid' => $blog->uuid,
        ];
    }

    private function indexBlogTerms(Blog $blog, array $searchData): void
    {
        $allText = implode(' ', [
            $searchData['title'],
            $searchData['excerpt'],
            $searchData['description'],
            $searchData['keywords']
        ]);

        $terms = $this->extractSearchTerms($allText);
        $score = $this->calculateBlogScore($blog, $searchData);

        foreach ($terms as $term) {
            if (strlen($term) < 2) continue;
            
            $termKey = $this->indexPrefix . 'terms:' . strtolower($term);
            Redis::zadd($termKey, $score, $blog->id);
            Redis::expire($termKey, config('cache.ttl.search', 3600));

            $suggestionKey = $this->indexPrefix . 'suggestions:' . strtolower($term[0]);
            Redis::zincrby($suggestionKey, 1, $term);
            Redis::expire($suggestionKey, config('cache.ttl.search', 3600));
        }
    }

    private function indexBlogTags(Blog $blog): void
    {
        foreach ($blog->tags as $tag) {
            $tagKey = $this->indexPrefix . 'tags:' . $tag->slug;
            $score = $blog->published_at->timestamp;
            Redis::zadd($tagKey, $score, $blog->id);
            Redis::expire($tagKey, config('cache.ttl.search', 3600));
        }
    }

    private function indexBlogAuthor(Blog $blog): void
    {
        $authorKey = $this->indexPrefix . 'authors:' . $blog->author_id;
        $score = $blog->published_at->timestamp;
        Redis::zadd($authorKey, $score, $blog->id);
        Redis::expire($authorKey, config('cache.ttl.search', 3600));
    }

    private function searchByTerms(string $query, array $filters, int $limit): array
    {
        $terms = $this->extractSearchTerms($query);
        $blogScores = [];

        foreach ($terms as $term) {
            $termKey = $this->indexPrefix . 'terms:' . strtolower($term);
            $termResults = Redis::zrevrange($termKey, 0, $limit - 1, 'WITHSCORES');
            
            foreach ($termResults as $blogId => $score) {
                $blogScores[$blogId] = ($blogScores[$blogId] ?? 0) + $score;
            }
        }

        arsort($blogScores);
        return array_keys(array_slice($blogScores, 0, $limit, true));
    }

    private function getBlogsFromIndex(array $blogIds, int $limit): array
    {
        $blogs = [];
        $count = 0;

        foreach ($blogIds as $blogId) {
            if ($count >= $limit) break;
            
            $blogKey = $this->indexPrefix . 'blogs:' . $blogId;
            $blogData = Redis::hgetall($blogKey);
            
            if (!empty($blogData)) {
                $blogs[] = $blogData;
                $count++;
            }
        }

        return $blogs;
    }

    private function formatSearchResults(array $blogs, string $query): array
    {
        return array_map(function ($blog) use ($query) {
            return [
                'id' => (int) $blog['id'],
                'uuid' => $blog['uuid'],
                'slug' => $blog['slug'],
                'title' => $blog['title'],
                'highlighted_title' => $this->highlightSearchTerms($blog['title'], $query),
                'excerpt' => $blog['excerpt'],
                'highlighted_excerpt' => $this->highlightSearchTerms($blog['excerpt'], $query),
                'author_name' => $blog['author_name'],
                'author_id' => (int) $blog['author_id'],
                'tags' => explode(' ', $blog['tags']),
                'published_at' => date('Y-m-d H:i:s', $blog['published_at']),
                'views_count' => (int) $blog['views_count'],
                'comments_count' => (int) $blog['comments_count'],
            ];
        }, $blogs);
    }

    private function extractSearchTerms(string $text): array
    {
        $text = strtolower(strip_tags($text));
        $text = preg_replace('/[^\w\s]/', ' ', $text);
        $terms = array_filter(explode(' ', $text), function ($term) {
            return strlen(trim($term)) >= 2;
        });
        
        return array_unique($terms);
    }

    private function calculateBlogScore(Blog $blog, array $searchData): float
    {
        $baseScore = $searchData['published_at'];
        $viewsBonus = min($searchData['views_count'] * 0.1, 100);
        $commentsBonus = min($searchData['comments_count'] * 0.5, 50);
        
        return $baseScore + $viewsBonus + $commentsBonus;
    }

    private function sanitizeQuery(string $query): string
    {
        return trim(preg_replace('/[^\w\s]/', ' ', $query));
    }

    private function getRecentBlogs(int $limit): array
    {
        $recentKey = $this->indexPrefix . 'recent';
        $blogIds = Redis::zrevrange($recentKey, 0, $limit - 1);
        
        return $this->getBlogsFromIndex($blogIds, $limit);
    }

    private function removeTermsFromIndex(int $blogId, array $searchData): void
    {
        $allText = implode(' ', [
            $searchData['title'] ?? '',
            $searchData['excerpt'] ?? '',
            $searchData['description'] ?? '',
            $searchData['keywords'] ?? ''
        ]);

        $terms = $this->extractSearchTerms($allText);

        foreach ($terms as $term) {
            $termKey = $this->indexPrefix . 'terms:' . strtolower($term);
            Redis::zrem($termKey, $blogId);
        }
    }

    private function removeTagsFromIndex(int $blogId): void
    {
        $blogKey = $this->indexPrefix . 'blogs:' . $blogId;
        $tagSlugs = Redis::hget($blogKey, 'tag_slugs');
        
        if ($tagSlugs) {
            $slugs = explode(',', $tagSlugs);
            foreach ($slugs as $slug) {
                $tagKey = $this->indexPrefix . 'tags:' . $slug;
                Redis::zrem($tagKey, $blogId);
            }
        }
    }

    private function removeAuthorFromIndex(int $blogId): void
    {
        $blogKey = $this->indexPrefix . 'blogs:' . $blogId;
        $authorId = Redis::hget($blogKey, 'author_id');
        
        if ($authorId) {
            $authorKey = $this->indexPrefix . 'authors:' . $authorId;
            Redis::zrem($authorKey, $blogId);
        }
    }
}
