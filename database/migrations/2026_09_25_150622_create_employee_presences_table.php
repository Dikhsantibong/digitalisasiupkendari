<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One absen masuk/pulang per employee per work date, with the position and
 * distance to the unit office captured at each tap.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_presences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->date('work_date');

            $table->timestamp('check_in_at');
            $table->decimal('check_in_latitude', 10, 7);
            $table->decimal('check_in_longitude', 10, 7);
            $table->unsignedInteger('check_in_distance_m');
            $table->unsignedInteger('check_in_accuracy_m')->nullable();

            $table->timestamp('check_out_at')->nullable();
            $table->decimal('check_out_latitude', 10, 7)->nullable();
            $table->decimal('check_out_longitude', 10, 7)->nullable();
            $table->unsignedInteger('check_out_distance_m')->nullable();
            $table->unsignedInteger('check_out_accuracy_m')->nullable();

            $table->timestamps();

            $table->unique(['employee_id', 'work_date']);
            $table->index(['unit_id', 'work_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_presences');
    }
};
