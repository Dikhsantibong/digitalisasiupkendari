<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * FLM findings for Pemeliharaan (user, 2026-11-24): each finding keeps who
 * reported it (name + shift) and who closed it, across the Operasi page's
 * save-all (rows are re-created on every save, so `uid` ties a row to its
 * history). Existing rows get a uid and their reporter from input_by.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operasi_flm_monitorings', function (Blueprint $table): void {
            $table->string('uid', 36)->nullable()->after('id')->index();
            $table->foreignId('reported_by')->nullable()->after('input_by')->constrained('users')->nullOnDelete();
            $table->string('reporter_name')->nullable()->after('reported_by');
            $table->string('reporter_shift', 60)->nullable()->after('reporter_name');
            $table->timestamp('reported_at')->nullable()->after('reporter_shift');
            $table->foreignId('closed_by')->nullable()->after('reported_at')->constrained('users')->nullOnDelete();
            $table->string('closed_by_name')->nullable()->after('closed_by');
            $table->timestamp('closed_at')->nullable()->after('closed_by_name');
        });

        $names = DB::table('users')->pluck('name', 'id');
        DB::table('operasi_flm_monitorings')->orderBy('id')->get(['id', 'input_by', 'created_at', 'status', 'updated_at'])->each(function ($row) use ($names): void {
            DB::table('operasi_flm_monitorings')->where('id', $row->id)->update([
                'uid' => (string) Str::uuid(),
                'reported_by' => $row->input_by,
                'reporter_name' => $row->input_by !== null ? ($names[$row->input_by] ?? null) : null,
                'reported_at' => $row->created_at,
                'closed_at' => $row->status === 'close' ? $row->updated_at : null,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('operasi_flm_monitorings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('closed_by');
            $table->dropConstrainedForeignId('reported_by');
            $table->dropColumn(['uid', 'reporter_name', 'reporter_shift', 'reported_at', 'closed_by_name', 'closed_at']);
        });
    }
};
