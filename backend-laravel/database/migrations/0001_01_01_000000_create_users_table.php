<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();

            // Le téléphone est l'identifiant de connexion (§4), normalisé en
            // E.164 : +224XXXXXXXXX. L'email reste facultatif.
            $table->string('phone', 20)->unique();
            $table->string('password');
            $table->string('full_name', 120);
            $table->string('email', 190)->nullable()->unique();
            $table->string('avatar_url', 500)->nullable();

            // Double casquette : un même compte peut porter les deux profils,
            // le second s'active depuis les paramètres de l'application.
            $table->boolean('is_client')->default(true);
            $table->boolean('is_technician')->default(false);

            // ACTIF | SUSPENDU
            $table->string('status', 20)->default('ACTIF');

            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'is_technician']);
        });

        // Réservée aux comptes du back-office : les utilisateurs de
        // l'application réinitialisent par code SMS (password_reset_codes).
        Schema::create('password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
