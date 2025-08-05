<?php

namespace App\Services;

use App\Models\CrawlerSearch;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;

class BazosCrawlerService
{
    private const USER_AGENTS = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    ];

    private const RATE_LIMIT_DELAY = 2; // seconds between requests
    private const MAX_RETRIES = 3;
    private const TIMEOUT = 30;

    /**
     * Crawl search and return results
     */
    public function crawlSearch(CrawlerSearch $search): array
    {
        $url = $search->getBazosUrl();
        
        \Log::info("Crawling URL: {$url}");
        
        $html = $this->fetchPage($url);
        
        if (!$html) {
            throw new \Exception("Failed to fetch search page");
        }
        
        return $this->parseSearchResults($html, $search);
    }

    /**
     * Fetch page with retry logic and rate limiting
     */
    private function fetchPage(string $url, int $attempt = 1): ?string
    {
        if ($attempt > 1) {
            sleep(self::RATE_LIMIT_DELAY * $attempt);
        } else {
            sleep(self::RATE_LIMIT_DELAY);
        }

        try {
            $response = Http::withHeaders([
                'User-Agent' => $this->getRandomUserAgent(),
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language' => 'sk,cs;q=0.8,en;q=0.6',
                'Accept-Encoding' => 'gzip, deflate, br',
                'Connection' => 'keep-alive',
                'Upgrade-Insecure-Requests' => '1',
            ])
            ->timeout(self::TIMEOUT)
            ->get($url);

            if ($response->successful()) {
                return $response->body();
            }

            if ($response->status() === 429 && $attempt <= self::MAX_RETRIES) {
                \Log::warning("Rate limited, retrying attempt {$attempt}");
                return $this->fetchPage($url, $attempt + 1);
            }

            \Log::error("HTTP error {$response->status()} for URL: {$url}");
            return null;

        } catch (\Exception $e) {
            \Log::error("Request failed for URL {$url}: " . $e->getMessage());
            
            if ($attempt <= self::MAX_RETRIES) {
                return $this->fetchPage($url, $attempt + 1);
            }
            
            return null;
        }
    }

    /**
     * Parse search results from HTML
     */
    private function parseSearchResults(string $html, CrawlerSearch $search): array
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        $xpath = new \DOMXPath($dom);

        $results = [];
        $cutoffDate = now()->subDays($search->max_age_days);

        // Find ad containers
        $adNodes = $xpath->query("//div[contains(@class, 'inzeraty') and contains(@class, 'inzeratyflex')]");

        foreach ($adNodes as $adNode) {
            try {
                $result = $this->parseAdNode($adNode, $xpath);
                
                if (!$result) {
                    continue;
                }

                // Check age filter
                if ($result['published_at'] && $result['published_at']->lt($cutoffDate)) {
                    continue;
                }

                // Check price filters
                if ($search->price_min && $result['price'] < $search->price_min) {
                    continue;
                }
                
                if ($search->price_max && $result['price'] > $search->price_max) {
                    continue;
                }

                $results[] = $result;

            } catch (\Exception $e) {
                \Log::warning("Failed to parse ad node: " . $e->getMessage());
                continue;
            }
        }

        \Log::info("Parsed {count} results from search", ['count' => count($results)]);
        
        return $results;
    }

    /**
     * Parse individual ad node
     */
    private function parseAdNode(\DOMNode $adNode, \DOMXPath $xpath): ?array
    {
        // Get title and URL
        $titleNode = $xpath->query(".//div[contains(@class, 'inzeratynadpis')]//h2[contains(@class, 'nadpis')]//a", $adNode)->item(0);
        if (!$titleNode) {
            return null;
        }

        $title = trim($titleNode->textContent);
        $relativeUrl = $titleNode->getAttribute('href');
        $bazosUrl = $this->resolveUrl($relativeUrl);
        $bazosId = $this->extractBazosId($bazosUrl);

        if (!$bazosId) {
            return null;
        }

        // Get price
        $priceNode = $xpath->query(".//div[contains(@class, 'inzeratycena')]", $adNode)->item(0);
        $priceText = $priceNode ? trim($priceNode->textContent) : '';
        $price = $this->parsePrice($priceText);

        // Get date
        $dateNode = $xpath->query(".//div[contains(@class, 'inzeratynadpis')]//span[contains(@class, 'velikost10')]", $adNode)->item(0);
        $dateText = $dateNode ? trim($dateNode->textContent) : '';
        $publishedAt = $this->parseDate($dateText);

        // Get location
        $locationNode = $xpath->query(".//div[contains(@class, 'inzeratylok')]", $adNode)->item(0);
        $location = $locationNode ? trim($locationNode->textContent) : '';

        // Get view count
        $viewNode = $xpath->query(".//div[contains(@class, 'inzeratyview')]", $adNode)->item(0);
        $viewText = $viewNode ? trim($viewNode->textContent) : '';
        $viewCount = $this->parseViewCount($viewText);

        // Get detailed info
        $detailInfo = $this->fetchItemDetails($bazosUrl);

        return [
            'bazos_id' => $bazosId,
            'title' => $title,
            'description' => $detailInfo['description'] ?? '',
            'price' => $price,
            'currency' => '€',
            'location' => $location,
            'seller_name' => $detailInfo['seller_name'] ?? null,
            'seller_phone' => $detailInfo['seller_phone'] ?? null,
            'seller_email' => $detailInfo['seller_email'] ?? null,
            'bazos_url' => $bazosUrl,
            'published_at' => $publishedAt,
            'view_count' => $viewCount,
            'is_available' => true,
            'images' => $detailInfo['images'] ?? [],
            'metadata' => [
                'crawled_at' => now()->toISOString(),
                'source_url' => $bazosUrl,
            ],
        ];
    }

    /**
     * Fetch detailed item information
     */
    private function fetchItemDetails(string $url): array
    {
        $html = $this->fetchPage($url);
        
        if (!$html) {
            return [];
        }

        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        $xpath = new \DOMXPath($dom);

        $details = [];

        // Get description
        $descNode = $xpath->query("//div[contains(@class, 'popisdetail')]")->item(0);
        if ($descNode) {
            $details['description'] = trim($descNode->textContent);
        }

        // Get images
        $imageNodes = $xpath->query("//img[contains(@src, 'bazos.sk/img/') and not(contains(@src, 't.jpg'))]");
        $images = [];
        
        foreach ($imageNodes as $imgNode) {
            $imgUrl = $imgNode->getAttribute('src');
            if ($imgUrl && !str_ends_with($imgUrl, 't.jpg')) { // Skip thumbnails
                $images[] = $this->resolveUrl($imgUrl);
            }
        }
        
        $details['images'] = array_unique($images);

        // Check availability
        $pageText = strtolower($html);
        $unavailableIndicators = [
            'inzerát bol vymazaný',
            'inzerát už nie je dostupný',
            'inzerát bol stiahnutý',
            'inzerát neexistuje'
        ];
        
        $details['is_available'] = !str_contains($pageText, implode('|', $unavailableIndicators));

        return $details;
    }

    /**
     * Parse price from text
     */
    private function parsePrice(string $priceText): int
    {
        // Remove everything except digits
        $numericOnly = preg_replace('/[^\d]/', '', $priceText);
        
        return $numericOnly ? (int) $numericOnly : 0;
    }

    /**
     * Parse date from Slovak text
     */
    private function parseDate(string $dateText): ?Carbon
    {
        $today = now();
        
        if (str_contains($dateText, 'Dnes')) {
            return $today;
        }
        
        if (str_contains($dateText, 'Včera')) {
            return $today->subDay();
        }
        
        // Parse DD.MM format
        if (preg_match('/(\d{1,2})\.\s*(\d{1,2})\./', $dateText, $matches)) {
            $day = (int) $matches[1];
            $month = (int) $matches[2];
            $year = $today->year;
            
            try {
                $date = Carbon::create($year, $month, $day);
                
                // If date is in future, assume previous year
                if ($date->gt($today)) {
                    $date->subYear();
                }
                
                return $date;
            } catch (\Exception $e) {
                \Log::warning("Failed to parse date: {$dateText}");
            }
        }
        
        return null;
    }

    /**
     * Parse view count from text
     */
    private function parseViewCount(string $viewText): int
    {
        if (preg_match('/(\d+)/', $viewText, $matches)) {
            return (int) $matches[1];
        }
        
        return 0;
    }

    /**
     * Extract Bazos ID from URL
     */
    private function extractBazosId(string $url): ?string
    {
        if (preg_match('/\/inzerat\/(\d+)\//', $url, $matches)) {
            return $matches[1];
        }
        
        return null;
    }

    /**
     * Resolve relative URL to absolute
     */
    private function resolveUrl(string $url): string
    {
        if (str_starts_with($url, 'http')) {
            return $url;
        }
        
        if (str_starts_with($url, '//')) {
            return 'https:' . $url;
        }
        
        if (str_starts_with($url, '/')) {
            return 'https://bazos.sk' . $url;
        }
        
        return 'https://bazos.sk/' . $url;
    }

    /**
     * Get random user agent
     */
    private function getRandomUserAgent(): string
    {
        return self::USER_AGENTS[array_rand(self::USER_AGENTS)];
    }
}