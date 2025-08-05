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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable(); // Nullable for OAuth users
            $table->string('avatar')->nullable();
            $table->string('provider')->nullable(); // google, facebook, apple
            $table->string('provider_id')->nullable();
            $table->text('provider_token')->nullable();
            $table->json('settings')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();

            // Indexes
            $table->index(['provider', 'provider_id']);
            $table->index('email');
            $table->index('is_active');
            $table->index('last_activity_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};