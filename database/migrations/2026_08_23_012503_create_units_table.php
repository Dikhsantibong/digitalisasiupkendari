<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_unit_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type');
            $table->decimal('installed_capacity_mw', 10, 2)->nullable();
            $table->string('location')->nullable();
            $table->string('status')->default('operating');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['service_unit_id', 'is_active']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
