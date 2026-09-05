<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paramètres modifiables depuis le back-office : taux de commission, rayons et
 * délais du matching, pondérations du score, fenêtres de litige et de séquestre.
 * Rien de tout cela ne doit vivre en dur dans le code (§8.2, §8.3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 80)->unique();
            $table->jsonb('value');
            $table->string('group', 40)->default('general');
            $table->string('label', 200);
            $table->string('description', 500)->nullable();

            // integer | decimal | boolean | string | json
            $table->string('type', 20)->default('string');

            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index('group');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_settings');
    }
};
