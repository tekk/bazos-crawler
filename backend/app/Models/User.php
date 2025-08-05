<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'email_verified_at',
        'avatar',
        'provider',
        'provider_id',
        'provider_token',
        'settings',
        'last_activity_at',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'provider_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'settings' => 'array',
        'is_active' => 'boolean',
        'password' => 'hashed',
    ];

    /**
     * Activity log options
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Get user's crawler searches
     */
    public function crawlerSearches()
    {
        return $this->hasMany(CrawlerSearch::class);
    }

    /**
     * Get user's found items
     */
    public function foundItems()
    {
        return $this->hasManyThrough(FoundItem::class, CrawlerSearch::class);
    }

    /**
     * Get user's notifications settings
     */
    public function notificationSettings()
    {
        return $this->hasOne(NotificationSetting::class);
    }

    /**
     * Check if user has active searches
     */
    public function hasActiveSearches(): bool
    {
        return $this->crawlerSearches()->where('is_active', true)->exists();
    }

    /**
     * Get user's search quota
     */
    public function getSearchQuota(): int
    {
        if ($this->hasRole('premium')) {
            return 50;
        }
        
        if ($this->hasRole('pro')) {
            return 20;
        }
        
        return 5; // free tier
    }

    /**
     * Check if user can create more searches
     */
    public function canCreateSearch(): bool
    {
        $activeSearches = $this->crawlerSearches()->where('is_active', true)->count();
        return $activeSearches < $this->getSearchQuota();
    }

    /**
     * Get user's avatar URL
     */
    public function getAvatarUrlAttribute(): string
    {
        if ($this->avatar) {
            return asset('storage/avatars/' . $this->avatar);
        }
        
        return 'https://www.gravatar.com/avatar/' . md5(strtolower($this->email)) . '?d=identicon&s=200';
    }

    /**
     * Scope for active users
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for users with recent activity
     */
    public function scopeRecentlyActive($query, $days = 30)
    {
        return $query->where('last_activity_at', '>=', now()->subDays($days));
    }
}