<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FoundItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'crawler_search_id',
        'bazos_id',
        'title',
        'description',
        'price',
        'currency',
        'location',
        'seller_name',
        'seller_phone',
        'seller_email',
        'bazos_url',
        'published_at',
        'view_count',
        'is_available',
        'last_checked_at',
        'images',
        'metadata',
    ];

    protected $casts = [
        'price' => 'integer',
        'published_at' => 'datetime',
        'view_count' => 'integer',
        'is_available' => 'boolean',
        'last_checked_at' => 'datetime',
        'images' => 'array',
        'metadata' => 'array',
    ];

    /**
     * Get the search that found this item
     */
    public function crawlerSearch()
    {
        return $this->belongsTo(CrawlerSearch::class);
    }

    /**
     * Get the user who owns this item (through search)
     */
    public function user()
    {
        return $this->hasOneThrough(User::class, CrawlerSearch::class, 'id', 'id', 'crawler_search_id', 'user_id');
    }

    /**
     * Get item images
     */
    public function itemImages()
    {
        return $this->hasMany(ItemImage::class);
    }

    /**
     * Get user favorites for this item
     */
    public function favorites()
    {
        return $this->hasMany(UserFavorite::class);
    }

    /**
     * Scope for available items
     */
    public function scopeAvailable($query)
    {
        return $query->where('is_available', true);
    }

    /**
     * Scope for recent items
     */
    public function scopeRecent($query, $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Scope for items in price range
     */
    public function scopeInPriceRange($query, $min = null, $max = null)
    {
        if ($min) {
            $query->where('price', '>=', $min);
        }
        
        if ($max) {
            $query->where('price', '<=', $max);
        }
        
        return $query;
    }

    /**
     * Get formatted price
     */
    public function getFormattedPriceAttribute(): string
    {
        if (!$this->price) {
            return 'N/A';
        }
        
        return number_format($this->price, 0, ',', ' ') . ' ' . ($this->currency ?? '€');
    }

    /**
     * Get first image URL
     */
    public function getFirstImageAttribute(): ?string
    {
        if (empty($this->images)) {
            return null;
        }
        
        return $this->images[0] ?? null;
    }

    /**
     * Get parsed location (city only)
     */
    public function getParsedLocationAttribute(): array
    {
        if (!$this->location) {
            return ['city' => '', 'postal_code' => ''];
        }
        
        // Slovak postal code patterns
        if (preg_match('/(\d{3}\s*\d{2})/', $this->location, $matches)) {
            $postalCode = $matches[1];
            $city = trim(preg_replace('/\d{3}\s*\d{2}/', '', $this->location));
            return ['city' => $city, 'postal_code' => $postalCode];
        }
        
        // City + continuous digits
        if (preg_match('/([a-zA-ZáčďéěíňóřšťúůýžÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ\s]+?)(\d{5,6})/', $this->location, $matches)) {
            $city = trim($matches[1]);
            $postalCode = $matches[2];
            return ['city' => $city, 'postal_code' => $postalCode];
        }
        
        // 5 digits anywhere
        if (preg_match('/(\d{5})/', $this->location, $matches)) {
            $postalCode = $matches[1];
            $city = trim(preg_replace('/\d{5}/', '', $this->location));
            return ['city' => $city, 'postal_code' => $postalCode];
        }
        
        return ['city' => trim($this->location), 'postal_code' => ''];
    }

    /**
     * Get city name only
     */
    public function getCityAttribute(): string
    {
        return $this->parsed_location['city'];
    }

    /**
     * Check if item is favorited by user
     */
    public function isFavoritedBy(User $user): bool
    {
        return $this->favorites()->where('user_id', $user->id)->exists();
    }

    /**
     * Toggle favorite status for user
     */
    public function toggleFavorite(User $user): bool
    {
        $favorite = $this->favorites()->where('user_id', $user->id)->first();
        
        if ($favorite) {
            $favorite->delete();
            return false;
        } else {
            UserFavorite::create([
                'user_id' => $user->id,
                'found_item_id' => $this->id,
            ]);
            return true;
        }
    }

    /**
     * Mark as unavailable
     */
    public function markAsUnavailable(): void
    {
        $this->update([
            'is_available' => false,
            'last_checked_at' => now(),
        ]);
    }

    /**
     * Update availability status
     */
    public function updateAvailability(bool $isAvailable): void
    {
        $this->update([
            'is_available' => $isAvailable,
            'last_checked_at' => now(),
        ]);
    }

    /**
     * Get time since published
     */
    public function getTimeSincePublishedAttribute(): string
    {
        if (!$this->published_at) {
            return 'N/A';
        }
        
        return $this->published_at->diffForHumans();
    }
}