<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Report-signer data on employees:
 * - user_id: the login account of the employee (User → Employee), so a
 *   pengesahan / tanda tangan is only accepted from the employee's own account;
 * - division: the divisi (work_modules.code) of the jabatan;
 * - singleton_key: "unit:{id}:{jabatan}" (or "su:{id}:Manager UL") for the
 *   jabatan that may have only one active holder per unit — unique, so a
 *   second active Project Leader / Office / Koordinator is rejected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->unique()->after('service_unit_id')->constrained()->nullOnDelete();
            $table->string('division', 32)->nullable()->after('position')->index();
            $table->string('singleton_key', 160)->nullable()->unique()->after('division');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropUnique(['singleton_key']);
            $table->dropColumn('singleton_key');
            $table->dropIndex(['division']);
            $table->dropColumn('division');
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
