<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Penanda tangan lintas unit: the reports of `unit_id` are signed, for one
        // jabatan, by that jabatan's holder at `source_unit_id` (e.g. the TL
        // Pemeliharaan of PLTD Poasia also verifies PLTD Poasia Containerized).
        Schema::create('report_signer_delegations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->string('position', 60);
            $table->foreignId('source_unit_id')->constrained('units')->cascadeOnDelete();
            // Role assignments created to give the signer access to `unit_id`, revoked with the delegation.
            $table->json('granted_assignment_ids')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['unit_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_signer_delegations');
    }
};
