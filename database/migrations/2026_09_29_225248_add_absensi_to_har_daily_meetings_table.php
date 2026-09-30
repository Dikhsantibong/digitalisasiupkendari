<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Daily Meeting Pemeliharaan with QR attendance: every meeting gets a secret
     * token (the public attendance link encoded in the QR code) and an open /
     * closed switch. Attendees (peserta JSON) now also carry their canvas
     * signature (PNG path on the public disk) and check-in time.
     */
    public function up(): void
    {
        Schema::table('har_daily_meetings', function (Blueprint $table): void {
            $table->string('token', 40)->nullable()->unique()->after('unit_id');
            $table->boolean('absensi_dibuka')->default(true)->after('eviden');
        });

        DB::table('har_daily_meetings')->whereNull('token')->orderBy('id')->each(function (object $meeting): void {
            DB::table('har_daily_meetings')->where('id', $meeting->id)->update(['token' => Str::random(32)]);
        });
    }

    public function down(): void
    {
        Schema::table('har_daily_meetings', function (Blueprint $table): void {
            $table->dropUnique(['token']);
            $table->dropColumn(['token', 'absensi_dibuka']);
        });
    }
};
