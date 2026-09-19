<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The approval chain changed to Koordinator divisi (memeriksa) → TL
 * Pemeliharaan (menyetujui) → Manager UL (mengesahkan, last). A report still
 * in process was frozen with the old chain, so it is sent back for perbaikan
 * (DITOLAK) with a system note: its maker re-submits it and the new signers
 * are frozen. FINAL / draft / already-rejected reports and every audit-trail
 * row are left untouched.
 */
return new class extends Migration
{
    private const IN_PROGRESS = ['diajukan', 'verifikasi', 'disetujui', 'disahkan', 'ditandatangani'];

    private const REASON = 'Alur verifikasi & pengesahan diperbarui (Koordinator divisi → Team Leader Pemeliharaan → Manager UL). Silakan ajukan kembali laporan ini.';

    public function up(): void
    {
        $now = now();

        DB::table('report_workflows')->whereIn('status', self::IN_PROGRESS)->orderBy('id')->get(['id', 'status'])
            ->each(function (object $workflow) use ($now): void {
                DB::table('report_workflows')->where('id', $workflow->id)->update([
                    'status' => 'ditolak',
                    'rejected_by' => null,
                    'rejected_at' => $now,
                    'rejection_reason' => self::REASON,
                    'updated_at' => $now,
                ]);
                DB::table('report_workflow_steps')->where('report_workflow_id', $workflow->id)
                    ->update(['signed_by' => null, 'signed_at' => null, 'note' => null, 'updated_at' => $now]);
                DB::table('report_workflow_logs')->insert([
                    'report_workflow_id' => $workflow->id,
                    'user_id' => null,
                    'user_name' => 'Sistem',
                    'jabatan' => null,
                    'action' => 'tolak',
                    'from_status' => $workflow->status,
                    'to_status' => 'ditolak',
                    'note' => self::REASON,
                    'created_at' => $now,
                ]);
            });
    }

    public function down(): void
    {
        // Irreversible data realignment: the reports are re-submitted through the workflow.
    }
};
