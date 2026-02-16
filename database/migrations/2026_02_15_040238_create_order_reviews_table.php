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
Schema::create('order_reviews', function (Blueprint $table) {
    $table->id();

    $table->foreignId('order_id')->constrained()->cascadeOnDelete();
    $table->foreignId('reviewer_id')->constrained('users');
    $table->foreignId('role_id')->constrained();

    $table->text('comment')->nullable();
    $table->boolean('approved')->default(false);

    $table->timestamp('reviewed_at')->nullable();

    $table->timestamps();
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_reviews');
    }
};
