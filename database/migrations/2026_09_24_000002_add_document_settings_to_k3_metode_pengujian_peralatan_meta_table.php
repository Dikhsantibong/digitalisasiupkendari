<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolom dokumen resmi (kop ISO, penandatangan, layout PDF, mode editor teks)
     * untuk Formulir Metode Pengujian Peralatan, sepadan dengan formulir Prelube Test.
     */
    public function up(): void
    {
        Schema::table('k3_metode_pengujian_peralatan_meta', function (Blueprint $table): void {
            $table->string('document_number', 100)->nullable()->after('catatan');
            $table->string('revision', 20)->nullable()->after('document_number');
            $table->string('effective_date', 100)->nullable()->after('revision');

            $table->foreignId('manager_ul_id')->nullable()->after('effective_date')->constrained('employees')->nullOnDelete();
            $table->string('manager_ul_name', 150)->nullable()->after('manager_ul_id');
            $table->string('manager_ul_title', 150)->nullable()->after('manager_ul_name');
            $table->foreignId('tl_k3_id')->nullable()->after('manager_ul_title')->constrained('employees')->nullOnDelete();
            $table->string('tl_k3_name', 150)->nullable()->after('tl_k3_id');
            $table->string('tl_k3_title', 150)->nullable()->after('tl_k3_name');
            $table->foreignId('staff_k3_id')->nullable()->after('tl_k3_title')->constrained('employees')->nullOnDelete();
            $table->string('staff_k3_name', 150)->nullable()->after('staff_k3_id');
            $table->string('staff_k3_title', 150)->nullable()->after('staff_k3_name');

            $table->string('sign_place_date', 150)->nullable()->after('staff_k3_title');

            $table->unsignedTinyInteger('page_margin_top')->nullable()->after('sign_place_date');
            $table->unsignedTinyInteger('page_margin_bottom')->nullable()->after('page_margin_top');
            $table->unsignedTinyInteger('page_margin_left')->nullable()->after('page_margin_bottom');
            $table->unsignedTinyInteger('page_margin_right')->nullable()->after('page_margin_left');
            $table->string('line_spacing', 10)->nullable()->after('page_margin_right');

            $table->string('format', 10)->default('form')->after('line_spacing');
            $table->longText('content_html')->nullable()->after('format');
        });
    }

    public function down(): void
    {
        Schema::table('k3_metode_pengujian_peralatan_meta', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('manager_ul_id');
            $table->dropConstrainedForeignId('tl_k3_id');
            $table->dropConstrainedForeignId('staff_k3_id');
            $table->dropColumn([
                'document_number',
                'revision',
                'effective_date',
                'manager_ul_name',
                'manager_ul_title',
                'tl_k3_name',
                'tl_k3_title',
                'staff_k3_name',
                'staff_k3_title',
                'sign_place_date',
                'page_margin_top',
                'page_margin_bottom',
                'page_margin_left',
                'page_margin_right',
                'line_spacing',
                'format',
                'content_html',
            ]);
        });
    }
};
