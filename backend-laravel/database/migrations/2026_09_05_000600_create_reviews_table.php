<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table): void {
            $table->id();

            // Un avis par intervention.
            $table->foreignId('ticket_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('technician_id')->constrained('users')->cascadeOnDelete();

            $table->unsignedTinyInteger('rating');    // 1 à 5

            // Tags rapides : ponctuel, propre, professionnel, bon conseil…
            $table->jsonb('tags')->nullable();
            $table->text('comment')->nullable();
            $table->unsignedBigInteger('tip_gnf')->default(0);

            $table->timestamps();

            $table->index(['technician_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
