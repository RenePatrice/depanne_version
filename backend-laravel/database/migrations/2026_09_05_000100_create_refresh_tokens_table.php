<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jetons de rafraîchissement (§4, ADR-0003) : l'access token Sanctum expire en
 * 15 minutes, ce jeton-ci vit 30 jours, ne circule que hashé, et tourne à
 * chaque usage — un jeton présenté deux fois est un vol.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refresh_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();

            // Chaîne de rotation : permet de détecter le rejeu d'un jeton déjà
            // échangé et de révoquer toute la lignée.
            $table->foreignId('replaced_by_id')->nullable()
                ->constrained('refresh_tokens')->nullOnDelete();

            $table->string('device_name', 120)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refresh_tokens');
    }
};
