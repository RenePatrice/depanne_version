<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Comptes du back-office (§4). Table et guard séparés des utilisateurs de
 * l'application : un administrateur n'est pas un client, et ne doit jamais
 * pouvoir se connecter à l'API mobile avec les mêmes identifiants.
 * Les rôles ADMIN / SUPPORT / FINANCE passent par spatie/laravel-permission.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_users', function (Blueprint $table): void {
            $table->id();
            $table->string('email', 190)->unique();
            $table->string('password');
            $table->string('full_name', 120);
            $table->string('avatar_url', 500)->nullable();
            $table->boolean('is_active')->default(true);

            // 2FA optionnelle.
            $table->text('two_factor_secret')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();

            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_users');
    }
};
