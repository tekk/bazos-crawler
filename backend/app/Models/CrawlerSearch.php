<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class CrawlerSearch extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'user_id',
        'name',
        'query',
        'category_id',
        'price_min',
        'price_max',
        'max_age_days',
        'location',
        'radius_km',
        'is_active',
        'notification_enabled',
        'last_crawled_at',
        'next_crawl_at',
        'crawl_interval_hours',
        'settings',
    ];

    protected $casts = [
        'price_min' => 'integer',
        'price_max' => 'integer',
        'max_age_days' => 'integer',
        'radius_km' => 'integer',
        'is_active' => 'boolean',
        'notification_enabled' => 'boolean',
        'last_crawled_at' => 'datetime',
        'next_crawl_at' => 'datetime',
        'crawl_interval_hours' => 'integer',
        'settings' => 'array',
    ];

    /**
     * Activity log options
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'query', 'is_active', 'price_min', 'price_max'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Get the user that owns the search
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get found items for this search
     */
    public function foundItems()
    {
        return $this->hasMany(FoundItem::class);
    }

    /**
     * Get the category
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get search statistics
     */
    public function statistics()
    {
        return $this->hasOne(SearchStatistic::class);
    }

    /**
     * Scope for active searches
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for searches that need crawling
     */
    public function scopeNeedsCrawling($query)
    {
        return $query->active()
            ->where(function ($q) {
                $q->whereNull('next_crawl_at')
                  ->orWhere('next_crawl_at', '<=', now());
            });
    }

    /**
     * Scope for user's searches
     */
    public function scopeForUser($query, User $user)
    {
        return $query->where('user_id', $user->id);
    }

    /**
     * Get the next crawl time
     */
    public function getNextCrawlTime(): \Carbon\Carbon
    {
        $hours = $this->crawl_interval_hours ?? 2;
        return now()->addHours($hours);
    }

    /**
     * Mark as crawled
     */
    public function markAsCrawled(): void
    {
        $this->update([
            'last_crawled_at' => now(),
            'next_crawl_at' => $this->getNextCrawlTime(),
        ]);
    }

    /**
     * Get search URL for Bazos
     */
    public function getBazosUrl(): string
    {
        $category = $this->category;
        $subdomain = $category ? $category->bazos_subdomain : 'www';
        
        $params = [
            'hledat' => $this->query,
            'rubriky' => 'www',
            'hlokalita' => $this->location ?? '',
            'humkreis' => $this->radius_km ?? 25,
            'cenaod' => $this->price_min ?? '',
            'cenado' => $this->price_max ?? '',
            'submit' => 'Hľadať',
            'order' => 'nejnovejsi'
        ];
        
        return "https://{$subdomain}.bazos.sk/?" . http_build_query($params);
    }

    /**
     * Check if search has new items
     */
    public function hasNewItems(): bool
    {
        return $this->foundItems()
            ->where('created_at', '>', $this->last_crawled_at ?? now()->subDay())
            ->exists();
    }

    /**
     * Get total found items count
     */
    public function getTotalItemsCount(): int
    {
        return $this->foundItems()->count();
    }

    /**
     * Get new items count since last check
     */
    public function getNewItemsCount(): int
    {
        return $this->foundItems()
            ->where('created_at', '>', $this->last_crawled_at ?? now()->subDay())
            ->count();
    }
}