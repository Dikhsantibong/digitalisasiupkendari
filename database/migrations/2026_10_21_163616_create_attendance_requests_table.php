<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pengajuan Absensi: izin / sakit / cuti over a date range, or a tukar
     * shift handing one shift to a colleague (optionally paid back on
     * `swap_date`). An approved request writes its codes on the Jadwal Shift;
     * the cells it changed carry attendance_request_id so "Isi Otomatis"
     * keeps them and the request can be undone.
     */
    public function up(): void
    {
        Schema::create('attendance_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 20);
            $table->date('start_date');
            $table->date('end_date');
            $table->text('reason');
            $table->string('attachment_path')->nullable();
            $table->foreignId('substitute_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->date('swap_date')->nullable();
            $table->string('status', 30);
            $table->timestamp('substitute_responded_at')->nullable();
            $table->string('substitute_note')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_note')->nullable();
            $table->json('applied')->nullable();
            $table->timestamps();

            $table->index(['unit_id', 'status']);
            $table->index(['employee_id', 'start_date']);
        });

        Schema::table('work_schedule_entries', function (Blueprint $table): void {
            $table->foreignId('attendance_request_id')->nullable()->after('note')->constrained('attendance_requests')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('work_schedule_entries', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('attendance_request_id');
        });

        Schema::dropIfExists('attendance_requests');
    }
};
