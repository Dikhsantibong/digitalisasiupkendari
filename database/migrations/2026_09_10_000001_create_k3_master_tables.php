<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Master data for the K3 & security module. Global lookups (activity types, APD
 * categories, equipment categories) carry no unit_id; the rest are per-unit
 * (locations, posts, APD items, extinguishers, P3K boxes, checklist items,
 * emergency equipment). Machines/employees/units are existing masters (FK only).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Global lookups.
        Schema::create('k3_activity_types', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('category')->nullable();
            $table->string('default_pic')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('apd_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('equipment_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Per-unit masters.
        Schema::create('emergency_equipments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->string('group_name')->nullable();
            $table->string('name');
            $table->string('location')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('apd_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('apd_category_id')->nullable()->constrained('apd_categories')->nullOnDelete();
            $table->string('name');
            $table->string('location')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('patrol_locations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['unit_id', 'code']);
        });

        Schema::create('security_posts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('fire_extinguishers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->string('rfid')->nullable();
            $table->string('location')->nullable();
            $table->string('merk')->nullable();
            $table->string('jenis')->nullable();
            $table->decimal('berat_kg', 8, 2)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('p3k_boxes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('location')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('inspection_checklists', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->string('form_code');
            $table->string('item_text');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['unit_id', 'form_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_checklists');
        Schema::dropIfExists('p3k_boxes');
        Schema::dropIfExists('fire_extinguishers');
        Schema::dropIfExists('security_posts');
        Schema::dropIfExists('patrol_locations');
        Schema::dropIfExists('apd_items');
        Schema::dropIfExists('emergency_equipments');
        Schema::dropIfExists('equipment_categories');
        Schema::dropIfExists('apd_categories');
        Schema::dropIfExists('k3_activity_types');
    }
};
