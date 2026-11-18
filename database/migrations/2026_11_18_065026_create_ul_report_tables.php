<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Laporan UL documents (user, 2026-11-18): the editable document of a
     * service unit's report (Pengusahaan Pembangkit, Logistik, K3L) per month
     * and its own approval chain — submitted by the Admin UL, approved by one
     * TL per bidang, ratified by the Manager UL. Kept apart from the unit
     * reports (report_workflows) so those flows are untouched.
     */
    public function up(): void
    {
        Schema::create('ul_report_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_unit_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20);
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('year');
            $table->string('document_number', 100)->nullable();
            $table->string('format', 10)->default('html');
            $table->longText('content_html')->nullable();
            $table->json('content_grid')->nullable();
            $table->unsignedSmallInteger('content_version')->default(1);
            $table->string('status', 20)->default('draft')->index();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['service_unit_id', 'kind', 'month', 'year']);
        });

        Schema::create('ul_report_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ul_report_document_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('sequence');
            $table->string('caption', 40);
            $table->string('position', 120);
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('signed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('signed_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['ul_report_document_id', 'sequence']);
        });

        Schema::create('ul_report_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ul_report_document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_name', 160)->nullable();
            $table->string('jabatan', 160)->nullable();
            $table->string('action', 30);
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->text('note')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ul_report_logs');
        Schema::dropIfExists('ul_report_steps');
        Schema::dropIfExists('ul_report_documents');
    }
};
