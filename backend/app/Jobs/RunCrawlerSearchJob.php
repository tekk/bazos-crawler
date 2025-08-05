<?php

namespace App\Jobs;

use App\Models\CrawlerSearch;
use App\Models\FoundItem;
use App\Services\BazosCrawlerService;
use App\Notifications\NewItemsFoundNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunCrawlerSearchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes
    public $tries = 3;
    public $backoff = [60, 120, 300]; // Exponential backoff

    /**
     * Create a new job instance.
     */
    public function __construct(
        public CrawlerSearch $search
    ) {
        $this->onQueue('crawler');
    }

    /**
     * Execute the job.
     */
    public function handle(BazosCrawlerService $crawlerService): void
    {
        try {
            Log::info("Starting crawler job for search: {$this->search->name}", [
                'search_id' => $this->search->id,
                'user_id' => $this->search->user_id,
            ]);

            // Check if search is still active
            if (!$this->search->is_active) {
                Log::info("Skipping inactive search: {$this->search->name}");
                return;
            }

            // Update search status
            $this->search->update(['last_crawled_at' => now()]);

            // Run crawler
            $results = $crawlerService->crawlSearch($this->search);

            // Process results
            $newItems = $this->processResults($results);

            // Update next crawl time
            $this->search->update([
                'next_crawl_at' => $this->search->getNextCrawlTime(),
            ]);

            // Send notifications if new items found
            if (!empty($newItems) && $this->search->notification_enabled) {
                $this->search->user->notify(new NewItemsFoundNotification($this->search, $newItems));
            }

            // Update statistics
            $this->updateStatistics($newItems);

            Log::info("Crawler job completed successfully", [
                'search_id' => $this->search->id,
                'new_items' => count($newItems),
                'total_results' => count($results),
            ]);

        } catch (\Exception $e) {
            Log::error("Crawler job failed for search: {$this->search->name}", [
                'search_id' => $this->search->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Update next crawl time even on failure (with delay)
            $this->search->update([
                'next_crawl_at' => now()->addHours($this->search->crawl_interval_hours * 2),
            ]);

            throw $e;
        }
    }

    /**
     * Process crawler results and save new items
     */
    private function processResults(array $results): array
    {
        $newItems = [];

        foreach ($results as $result) {
            // Check if item already exists
            $existingItem = FoundItem::where('crawler_search_id', $this->search->id)
                ->where('bazos_id', $result['bazos_id'])
                ->first();

            if ($existingItem) {
                // Update existing item
                $existingItem->update([
                    'title' => $result['title'],
                    'description' => $result['description'],
                    'price' => $result['price'],
                    'location' => $result['location'],
                    'view_count' => $result['view_count'],
                    'is_available' => $result['is_available'],
                    'last_checked_at' => now(),
                    'images' => $result['images'],
                    'metadata' => $result['metadata'] ?? [],
                ]);
                continue;
            }

            // Apply filters
            if (!$this->passesFilters($result)) {
                continue;
            }

            // Create new item
            $item = FoundItem::create([
                'crawler_search_id' => $this->search->id,
                'bazos_id' => $result['bazos_id'],
                'title' => $result['title'],
                'description' => $result['description'],
                'price' => $result['price'],
                'currency' => $result['currency'] ?? '€',
                'location' => $result['location'],
                'seller_name' => $result['seller_name'] ?? null,
                'seller_phone' => $result['seller_phone'] ?? null,
                'seller_email' => $result['seller_email'] ?? null,
                'bazos_url' => $result['bazos_url'],
                'published_at' => $result['published_at'],
                'view_count' => $result['view_count'],
                'is_available' => $result['is_available'],
                'last_checked_at' => now(),
                'images' => $result['images'],
                'metadata' => $result['metadata'] ?? [],
            ]);

            $newItems[] = $item;

            // Log activity
            activity()
                ->causedBy($this->search->user)
                ->performedOn($item)
                ->withProperties(['search_name' => $this->search->name])
                ->log('New item found');
        }

        return $newItems;
    }

    /**
     * Check if result passes user-defined filters
     */
    private function passesFilters(array $result): bool
    {
        $settings = $this->search->settings ?? [];

        // Exclude keywords filter
        if (!empty($settings['exclude_keywords'])) {
            $text = strtolower($result['title'] . ' ' . $result['description']);
            foreach ($settings['exclude_keywords'] as $keyword) {
                if (str_contains($text, strtolower($keyword))) {
                    return false;
                }
            }
        }

        // Include keywords filter (must contain at least one)
        if (!empty($settings['include_keywords'])) {
            $text = strtolower($result['title'] . ' ' . $result['description']);
            $hasIncludeKeyword = false;
            foreach ($settings['include_keywords'] as $keyword) {
                if (str_contains($text, strtolower($keyword))) {
                    $hasIncludeKeyword = true;
                    break;
                }
            }
            if (!$hasIncludeKeyword) {
                return false;
            }
        }

        // Minimum images filter
        if (!empty($settings['min_images'])) {
            if (count($result['images']) < $settings['min_images']) {
                return false;
            }
        }

        // Exclude sellers filter
        if (!empty($settings['exclude_sellers']) && !empty($result['seller_name'])) {
            $sellerName = strtolower($result['seller_name']);
            foreach ($settings['exclude_sellers'] as $excludedSeller) {
                if (str_contains($sellerName, strtolower($excludedSeller))) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Update search statistics
     */
    private function updateStatistics(array $newItems): void
    {
        $stats = $this->search->statistics()->firstOrCreate([]);
        
        $stats->increment('total_crawls');
        $stats->increment('total_items_found', count($newItems));
        
        if (!empty($newItems)) {
            $stats->update(['last_items_found_at' => now()]);
        }
        
        $stats->update(['last_crawl_at' => now()]);
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Crawler job permanently failed", [
            'search_id' => $this->search->id,
            'search_name' => $this->search->name,
            'error' => $exception->getMessage(),
        ]);

        // Optionally notify user about persistent failures
        // $this->search->user->notify(new CrawlerJobFailedNotification($this->search, $exception));
    }
}