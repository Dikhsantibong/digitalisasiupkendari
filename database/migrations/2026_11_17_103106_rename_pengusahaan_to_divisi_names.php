<?php

use App\Enums\PermissionName;
use App\Enums\RoleName;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Phrases replaced in the saved Laporan Operasi / Pemeliharaan / K3
     * documents (longest first), the same as in the code.
     *
     * @var array<string, string>
     */
    private const PHRASES = [
        'LAPORAN PENGUSAHAAN PEMBANGKIT (OPERASI)' => 'LAPORAN OPERASI',
        'LAPORAN PENGUSAHAAN PEMBANGKIT (K3 & KAM)' => 'LAPORAN K3 & KAM',
        'LAPORAN PENGUSAHAAN PEMBANGKIT (K3 &amp; KAM)' => 'LAPORAN K3 &amp; KAM',
        'LAPORAN PENGUSAHAAN OPERASI' => 'LAPORAN OPERASI',
        'LAPORAN PENGUSAHAAN PEMELIHARAAN' => 'LAPORAN PEMELIHARAAN',
        'LAPORAN PENGUSAHAAN K3 & KEAMANAN' => 'LAPORAN K3 & KEAMANAN',
        'LAPORAN PENGUSAHAAN K3 &amp; KEAMANAN' => 'LAPORAN K3 &amp; KEAMANAN',
        'PENGUSAHAAN PEMBANGKITAN' => 'KINERJA PEMBANGKITAN',
        'Laporan Pengusahaan Pembangkit Bidang Operasi' => 'Laporan Operasi',
        'Laporan Pengusahaan Pembangkit (Operasi)' => 'Laporan Operasi',
        'Laporan Pengusahaan Pembangkit (K3 & KAM)' => 'Laporan K3 & KAM',
        'Laporan Pengusahaan Pembangkit (K3 &amp; KAM)' => 'Laporan K3 &amp; KAM',
        'Laporan Pengusahaan Operasi' => 'Laporan Operasi',
        'Laporan Pengusahaan Pemeliharaan' => 'Laporan Pemeliharaan',
        'Laporan Pengusahaan K3' => 'Laporan K3',
        'Input &amp; Formulir Pengusahaan K3' => 'Input &amp; Formulir K3',
        'Input & Formulir Pengusahaan K3' => 'Input & Formulir K3',
        'Input / Formulir Pengusahaan' => 'Input / Formulir K3',
        'Input Pengusahaan Operasi' => 'Input Operasi',
        'Ringkasan Kinerja Pengusahaan Pembangkit' => 'Ringkasan Kinerja Pembangkit',
        'Time Frame Pengusahaan' => 'Time Frame K3 & Keamanan',
        'menu Pengusahaan Operasi' => 'menu Operasi',
        'menu Pengusahaan K3' => 'menu K3',
        'Operator / Staf Pengusahaan' => 'Operator / Staf Operasi',
        'Pengusahaan Operasi' => 'Operasi',
    ];

    /** @var array<string, array{0: string, 1: string, 2: string, 3: string}> table => [type column, type value, UPPER title, Title] */
    private const DOCUMENTS = [
        'operasi_report_documents' => ['report_code', 'pengusahaan', 'LAPORAN OPERASI', 'Laporan Operasi'],
        'har_document_records' => ['type', 'pengusahaan', 'LAPORAN PEMELIHARAAN', 'Laporan Pemeliharaan'],
        'k3_document_records' => ['type', 'pengusahaan', 'LAPORAN K3 &amp; KAM', 'Laporan K3 &amp; KAM'],
    ];

    /**
     * "Pengusahaan" is no longer shown (user, 2026-11-17): the Akses 2
     * menus and reports carry the divisi name (Laporan Operasi, Laporan
     * Pemeliharaan, Laporan K3 & KAM). Updates the stored display texts —
     * permission names and role descriptions still holding the word (texts
     * changed in Role & Akses are left alone) — and the saved documents.
     */
    public function up(): void
    {
        foreach (PermissionName::cases() as $permission) {
            DB::table('permissions')->where('name', $permission->value)->where('display_name', 'like', '%engusahaan%')
                ->update(['display_name' => $permission->label(), 'updated_at' => now()]);
        }

        foreach (RoleName::cases() as $role) {
            DB::table('roles')->where('name', $role->value)->where('description', 'like', '%engusahaan%')
                ->update(['description' => $role->description(), 'updated_at' => now()]);
        }

        foreach (self::DOCUMENTS as $table => [$column, $value, $upper, $title]) {
            $phrases = self::PHRASES + [
                'LAPORAN PENGUSAHAAN PEMBANGKIT' => $upper,
                'Laporan Pengusahaan Pembangkit' => $title,
            ];

            DB::table($table)->where($column, $value)->orderBy('id')->chunkById(50, function ($documents) use ($table, $phrases): void {
                foreach ($documents as $document) {
                    $changes = [];
                    foreach (['content_html', 'content_grid', 'snapshot'] as $field) {
                        if (is_string($document->{$field}) && $document->{$field} !== '') {
                            $replaced = str_replace(array_keys($phrases), array_values($phrases), $document->{$field});
                            if ($replaced !== $document->{$field}) {
                                $changes[$field] = $replaced;
                            }
                        }
                    }
                    if ($changes !== []) {
                        DB::table($table)->where('id', $document->id)->update($changes);
                    }
                }
            });
        }
    }

    /**
     * Texts are not restored: the old names are not kept anywhere.
     */
    public function down(): void {}
};
