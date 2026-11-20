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
        Schema::create('generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('prompt');
            $table->string('size');
            $table->string('quality');
            $table->json('reference_images')->nullable();
            $table->uuid('idempotency_key')->unique();
            $table->string('pixelforge_id')->nullable()->unique();
            $table->string('status')->default('pending');
            $table->json('rates')->nullable();
            $table->json('usage')->nullable();
            $table->unsignedBigInteger('estimated_charge_nano');
            $table->unsignedBigInteger('provider_cost_nano')->nullable();
            $table->unsignedBigInteger('charge_nano')->nullable();
            $table->text('image_url')->nullable();
            $table->timestamp('image_url_expires_at')->nullable();
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedInteger('submit_attempts')->default(0);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('generations');
    }
};
