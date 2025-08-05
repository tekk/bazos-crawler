<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CrawlerSearchRequest;
use App\Models\CrawlerSearch;
use App\Jobs\RunCrawlerSearchJob;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CrawlerSearchController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('throttle:60,1'); // Rate limiting
    }

    /**
     * Get user's crawler searches
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $searches = $user->crawlerSearches()
            ->with(['category', 'foundItems' => function ($query) {
                $query->latest()->limit(5);
            }])
            ->withCount('foundItems')
            ->latest()
            ->paginate(20);
        
        return response()->json([
            'success' => true,
            'data' => $searches,
            'meta' => [
                'quota' => $user->getSearchQuota(),
                'used' => $user->crawlerSearches()->active()->count(),
                'can_create' => $user->canCreateSearch(),
            ]
        ]);
    }

    /**
     * Create new crawler search
     */
    public function store(CrawlerSearchRequest $request): JsonResponse
    {
        $user = $request->user();
        
        // Check quota
        if (!$user->canCreateSearch()) {
            return response()->json([
                'success' => false,
                'message' => 'Search quota exceeded. Upgrade your plan or deactivate existing searches.',
                'quota' => $user->getSearchQuota(),
            ], 403);
        }
        
        $search = $user->crawlerSearches()->create([
            'name' => $request->name,
            'query' => $request->query,
            'category_id' => $request->category_id,
            'price_min' => $request->price_min,
            'price_max' => $request->price_max,
            'max_age_days' => $request->max_age_days ?? 14,
            'location' => $request->location,
            'radius_km' => $request->radius_km ?? 25,
            'is_active' => true,
            'notification_enabled' => $request->notification_enabled ?? true,
            'crawl_interval_hours' => $request->crawl_interval_hours ?? 2,
            'next_crawl_at' => now(),
            'settings' => $request->settings ?? [],
        ]);
        
        // Dispatch initial crawl job
        RunCrawlerSearchJob::dispatch($search);
        
        activity()
            ->causedBy($user)
            ->performedOn($search)
            ->log('Created new crawler search');
        
        return response()->json([
            'success' => true,
            'message' => 'Search created successfully',
            'data' => $search->load('category'),
        ], 201);
    }

    /**
     * Get specific crawler search
     */
    public function show(Request $request, CrawlerSearch $search): JsonResponse
    {
        $this->authorize('view', $search);
        
        $search->load([
            'category',
            'foundItems' => function ($query) {
                $query->latest()->with('itemImages');
            },
            'statistics'
        ]);
        
        return response()->json([
            'success' => true,
            'data' => $search,
        ]);
    }

    /**
     * Update crawler search
     */
    public function update(CrawlerSearchRequest $request, CrawlerSearch $search): JsonResponse
    {
        $this->authorize('update', $search);
        
        $search->update([
            'name' => $request->name,
            'query' => $request->query,
            'category_id' => $request->category_id,
            'price_min' => $request->price_min,
            'price_max' => $request->price_max,
            'max_age_days' => $request->max_age_days,
            'location' => $request->location,
            'radius_km' => $request->radius_km,
            'notification_enabled' => $request->notification_enabled,
            'crawl_interval_hours' => $request->crawl_interval_hours,
            'settings' => $request->settings ?? $search->settings,
        ]);
        
        activity()
            ->causedBy($request->user())
            ->performedOn($search)
            ->log('Updated crawler search');
        
        return response()->json([
            'success' => true,
            'message' => 'Search updated successfully',
            'data' => $search->load('category'),
        ]);
    }

    /**
     * Toggle search active status
     */
    public function toggle(Request $request, CrawlerSearch $search): JsonResponse
    {
        $this->authorize('update', $search);
        
        $user = $request->user();
        $newStatus = !$search->is_active;
        
        // Check quota when activating
        if ($newStatus && !$user->canCreateSearch()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot activate search. Quota exceeded.',
                'quota' => $user->getSearchQuota(),
            ], 403);
        }
        
        $search->update([
            'is_active' => $newStatus,
            'next_crawl_at' => $newStatus ? now() : null,
        ]);
        
        $action = $newStatus ? 'activated' : 'deactivated';
        
        activity()
            ->causedBy($user)
            ->performedOn($search)
            ->log("Search {$action}");
        
        return response()->json([
            'success' => true,
            'message' => "Search {$action} successfully",
            'data' => $search,
        ]);
    }

    /**
     * Delete crawler search
     */
    public function destroy(Request $request, CrawlerSearch $search): JsonResponse
    {
        $this->authorize('delete', $search);
        
        activity()
            ->causedBy($request->user())
            ->performedOn($search)
            ->log('Deleted crawler search');
        
        $search->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Search deleted successfully',
        ]);
    }

    /**
     * Run search manually
     */
    public function run(Request $request, CrawlerSearch $search): JsonResponse
    {
        $this->authorize('update', $search);
        
        if (!$search->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot run inactive search',
            ], 400);
        }
        
        // Check rate limiting
        if ($search->last_crawled_at && $search->last_crawled_at->gt(now()->subMinutes(30))) {
            return response()->json([
                'success' => false,
                'message' => 'Search was run recently. Please wait before running again.',
                'next_allowed_at' => $search->last_crawled_at->addMinutes(30)->toISOString(),
            ], 429);
        }
        
        RunCrawlerSearchJob::dispatch($search);
        
        activity()
            ->causedBy($request->user())
            ->performedOn($search)
            ->log('Manually triggered crawler search');
        
        return response()->json([
            'success' => true,
            'message' => 'Search queued for execution',
        ]);
    }

    /**
     * Get search statistics
     */
    public function statistics(Request $request, CrawlerSearch $search): JsonResponse
    {
        $this->authorize('view', $search);
        
        $stats = [
            'total_items' => $search->foundItems()->count(),
            'available_items' => $search->foundItems()->available()->count(),
            'recent_items' => $search->foundItems()->recent(7)->count(),
            'avg_price' => $search->foundItems()->available()->avg('price'),
            'min_price' => $search->foundItems()->available()->min('price'),
            'max_price' => $search->foundItems()->available()->max('price'),
            'last_crawled' => $search->last_crawled_at?->toISOString(),
            'next_crawl' => $search->next_crawl_at?->toISOString(),
            'crawl_frequency' => $search->crawl_interval_hours . ' hours',
        ];
        
        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}