<?php

namespace App\Services\Monitoring;

use App\Enums\PermissionName;
use App\Enums\ReportModule;
use App\Models\User;

/**
 * Which bidang (areas) an account may see in Monitoring and the Portal
 * Pemantauan, from the view permissions its roles hold: e.g. TL Operasi UP
 * sees only Operasi (with Operator), Manager UP every area.
 */
class ModuleAccess
{
    /**
     * Area => label, permissions that open it (any), Kelengkapan Input groups, Laporan Pembangkit modules.
     *
     * @var array<string, array{0: string, 1: list<PermissionName>, 2: list<string>, 3: list<ReportModule>}>
     */
    public const AREAS = [
        'operasi' => ['Operasi', [PermissionName::OperasiInputView, PermissionName::OperasiLaporanView, PermissionName::OperasiPengusahaanView, PermissionName::OperatorLogsheetView], ['operasi', 'operasi_pengusahaan', 'operator'], [ReportModule::Operasi, ReportModule::OperasiPengusahaan]],
        'pemeliharaan' => ['Pemeliharaan', [PermissionName::HarInputView, PermissionName::HarLaporanView, PermissionName::HarPengusahaanView], ['har', 'har_pengusahaan'], [ReportModule::Har, ReportModule::HarPengusahaan]],
        'k3' => ['K3 & Lingkungan', [PermissionName::K3InputView, PermissionName::K3LaporanView, PermissionName::K3PengusahaanView], ['k3', 'k3_pengusahaan'], [ReportModule::K3, ReportModule::K3Pengusahaan]],
        'logistik' => ['Logistik & Gudang', [PermissionName::LogistikInputView, PermissionName::LogistikLaporanView], ['logistik'], [ReportModule::Logistik]],
        'pdm' => ['PdM & Maturity Level', [PermissionName::PdmInputView, PermissionName::PdmLaporanView], ['pdm'], [ReportModule::Pdm]],
    ];

    /**
     * @return list<string>
     */
    public function areas(User $user): array
    {
        return array_values(array_filter(array_keys(self::AREAS), fn (string $area): bool => collect(self::AREAS[$area][1])
            ->contains(fn (PermissionName $permission): bool => $user->hasPermissionTo($permission))));
    }

    /**
     * Kelengkapan Input groups ({@see InputCatalog::GROUPS}) the user may see.
     *
     * @return list<string>
     */
    public function groups(User $user): array
    {
        return $this->collect($user, 2);
    }

    /**
     * Laporan Pembangkit modules the user may see.
     *
     * @return list<ReportModule>
     */
    public function reportModules(User $user): array
    {
        return $this->collect($user, 3);
    }

    /**
     * @return list<mixed>
     */
    private function collect(User $user, int $index): array
    {
        $items = [];
        foreach ($this->areas($user) as $area) {
            array_push($items, ...self::AREAS[$area][$index]);
        }

        return $items;
    }
}
