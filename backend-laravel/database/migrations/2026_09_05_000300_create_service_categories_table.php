<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 30)->unique();       // PLOMBERIE | ELECTRICITE
            $table->string('name', 80);
            $table->string('description', 300)->nullable();
            $table->string('icon', 60)->nullable();     // nom d'icône Bootstrap Icons
            $table->string('color', 7)->default('#1B6FF3');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_categories');
    }
};
