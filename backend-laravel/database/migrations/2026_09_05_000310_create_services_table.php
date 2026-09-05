<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->constrained('service_categories')->cascadeOnDelete();
            $table->string('slug', 120)->unique();
            $table->string('name', 150);
            $table->text('description')->nullable();

            // Ce qui est compris dans le prix fixe et ce qui ne l'est pas :
            // affiché tel quel sur l'écran de détail de la prestation.
            $table->jsonb('included')->nullable();
            $table->jsonb('excluded')->nullable();

            // Prix de référence de la prestation, hors déplacement (ADR-0002).
            $table->unsignedBigInteger('base_price_gnf');
            $table->unsignedSmallInteger('estimated_duration_min')->default(60);

            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['category_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
