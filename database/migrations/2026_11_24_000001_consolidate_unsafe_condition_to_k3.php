<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('k3_kondisi_k3s')) {
            $this->migrateFindingsToK3();
        }

        if (Schema::hasTable('permissions')) {
            $permissionIds = DB::table('permissions')
                ->whereIn('name', [
                    'operasi.lapangan.unsafe_condition',
                    'har.lapangan.unsafe_condition',
                ])
                ->pluck('id')
                ->all();

            if ($permissionIds !== []) {
                if (Schema::hasTable('permission_role')) {
                    DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
                }

                DB::table('permissions')->whereIn('id', $permissionIds)->delete();
            }
        }
    }

    public function down(): void
    {
        // Consolidation migration is forward-only; obsolete permissions can be re-seeded via RolePermissionSeeder if reverted.
    }

    private function migrateFindingsToK3(): void
    {
        $existingKeys = [];
        $nextSortByPeriod = [];

        foreach (DB::table('k3_kondisi_k3s')->get(['unit_id', 'year', 'month', 'temuan', 'sort_order']) as $row) {
            $periodKey = "{$row->unit_id}|{$row->year}|{$row->month}";
            $existingKeys[$periodKey.'|'.mb_strtolower(trim((string) $row->temuan))] = true;
            $nextSortByPeriod[$periodKey] = max($nextSortByPeriod[$periodKey] ?? 0, ((int) $row->sort_order) + 1);
        }

        $now = Carbon::now();

        if (Schema::hasTable('har_unsafe_conditions')) {
            $harRows = DB::table('har_unsafe_conditions')->orderBy('unit_id')->orderBy('year')->orderBy('month')->orderBy('sort_order')->orderBy('id')->get();

            foreach ($harRows as $row) {
                $temuan = trim((string) $row->temuan);
                if ($temuan === '') {
                    continue;
                }

                $periodKey = "{$row->unit_id}|{$row->year}|{$row->month}";
                $dedupKey = $periodKey.'|'.mb_strtolower($temuan);
                if (isset($existingKeys[$dedupKey])) {
                    continue;
                }

                $sortOrder = $nextSortByPeriod[$periodKey] ?? 0;
                $nextSortByPeriod[$periodKey] = $sortOrder + 1;
                $existingKeys[$dedupKey] = true;

                DB::table('k3_kondisi_k3s')->insert([
                    'unit_id' => $row->unit_id,
                    'year' => $row->year,
                    'month' => $row->month,
                    'no_urut' => (string) ($sortOrder + 1),
                    'periode' => $row->periode ?: '-',
                    'kategori' => $this->normalizeKategori((string) $row->kategori),
                    'temuan' => $temuan,
                    'kondisi' => $row->kondisi,
                    'tindak_lanjut' => $row->tindak_lanjut,
                    'rekomendasi' => $row->rekomendasi,
                    'lokasi' => $row->lokasi,
                    'keterangan' => $this->normalizeStatus((string) $row->keterangan),
                    'eviden_sebelum' => $this->normalizePhotoUrl($row->foto_sebelum),
                    'eviden_sesudah' => $this->normalizePhotoUrl($row->foto_sesudah),
                    'sort_order' => $sortOrder,
                    'input_by' => $row->input_by,
                    'created_at' => $row->created_at ?? $now,
                    'updated_at' => $row->updated_at ?? $now,
                ]);
            }

            DB::table('har_unsafe_conditions')->delete();
        }

        if (Schema::hasTable('logistik_form_rows')) {
            $logistikRows = DB::table('logistik_form_rows')
                ->where('form', 'unsafe')
                ->orderBy('unit_id')->orderBy('year')->orderBy('month')->orderBy('sort_order')->orderBy('id')
                ->get();

            foreach ($logistikRows as $row) {
                $data = json_decode((string) $row->data, true);
                if (! is_array($data)) {
                    continue;
                }

                $temuan = trim((string) ($data['temuan'] ?? ''));
                if ($temuan === '') {
                    continue;
                }

                $periodKey = "{$row->unit_id}|{$row->year}|{$row->month}";
                $dedupKey = $periodKey.'|'.mb_strtolower($temuan);
                if (isset($existingKeys[$dedupKey])) {
                    continue;
                }

                $sortOrder = $nextSortByPeriod[$periodKey] ?? 0;
                $nextSortByPeriod[$periodKey] = $sortOrder + 1;
                $existingKeys[$dedupKey] = true;

                DB::table('k3_kondisi_k3s')->insert([
                    'unit_id' => $row->unit_id,
                    'year' => $row->year,
                    'month' => $row->month,
                    'no_urut' => (string) ($sortOrder + 1),
                    'periode' => trim((string) ($data['periode'] ?? '')) ?: '-',
                    'kategori' => $this->normalizeKategori((string) ($data['kategori'] ?? '')),
                    'temuan' => $temuan,
                    'kondisi' => $this->nullableText($data['kondisi'] ?? null),
                    'tindak_lanjut' => $this->nullableText($data['tindak_lanjut'] ?? null),
                    'rekomendasi' => $this->nullableText($data['rekomendasi'] ?? null),
                    'lokasi' => $this->nullableText($data['lokasi'] ?? null),
                    'keterangan' => $this->normalizeStatus((string) ($data['keterangan'] ?? '')),
                    'eviden_sebelum' => $this->normalizePhotoUrl($data['eviden_sebelum'] ?? null),
                    'eviden_sesudah' => $this->normalizePhotoUrl($data['eviden_sesudah'] ?? null),
                    'sort_order' => $sortOrder,
                    'input_by' => $row->input_by,
                    'created_at' => $row->created_at ?? $now,
                    'updated_at' => $row->updated_at ?? $now,
                ]);
            }

            DB::table('logistik_form_rows')->where('form', 'unsafe')->delete();
        }
    }

    private function normalizeKategori(string $value): string
    {
        return str_contains(strtoupper($value), 'ACTION') ? 'Unsafe Action' : 'Unsafe Condition';
    }

    private function normalizeStatus(string $value): string
    {
        $normalized = strtolower(trim($value));

        return match (true) {
            str_contains($normalized, 'close') || str_contains($normalized, 'selesai') => 'Closed',
            str_contains($normalized, 'progress') => 'On Progress',
            default => 'Open',
        };
    }

    private function normalizePhotoUrl(mixed $value): ?string
    {
        $path = trim((string) $value);
        if ($path === '') {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '/')) {
            return $path;
        }

        return Storage::disk('public')->url($path);
    }

    private function nullableText(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
};
