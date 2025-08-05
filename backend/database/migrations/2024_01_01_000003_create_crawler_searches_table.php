<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('crawler_searches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('category_id')->nullable()->constrained()->onDelete('set null');
            $table->string('name');
            $table->string('query');
            $table->integer('price_min')->nullable();
            $table->integer('price_max')->nullable();
            $table->integer('max_age_days')->default(14);
            $table->string('location')->nullable();
            $table->integer('radius_km')->default(25);
            $table->boolean('is_active')->default(true);
            $table->boolean('notification_enabled')->default(true);
            $table->timestamp('last_crawled_at')->nullable();
            $table->timestamp('next_crawl_at')->nullable();
            $table->integer('crawl_interval_hours')->default(2);
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['user_id', 'is_active']);
            $table->index('next_crawl_at');
            $table->index('is_active');
            $table->index(['user_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crawler_searches');
    }
};