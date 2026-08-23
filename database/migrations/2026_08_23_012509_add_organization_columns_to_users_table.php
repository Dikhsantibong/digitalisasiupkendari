<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('employee_id')->nullable()->unique()->after('name');
            $table->string('position')->nullable()->after('email');
            $table->string('phone', 32)->nullable()->after('position');
            $table->boolean('is_active')->default(true)->after('phone');
            $table->timestamp('last_login_at')->nullable()->after('is_active');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'employee_id',
                'position',
                'phone',
                'is_active',
                'last_login_at',
                'last_login_ip',
            ]);
        });
    }
};
