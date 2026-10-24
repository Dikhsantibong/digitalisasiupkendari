<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\PermissionName;
use App\Models\User;

/**
 * Permission gate of a module input page that field staff also fill. The page
 * opens for the module's own permission (e.g. har.input.view for the TL) or
 * for the page's field permission (e.g. har.lapangan.work_order for Harmes /
 * Harlist, operasi.lapangan.daily_report for operators), which a Super Admin
 * grants or withdraws per role in Role & Akses.
 */
trait AuthorizesFieldInput
{
    protected function allowsFieldInput(User $user, PermissionName $modulePermission, ?PermissionName $pagePermission = null): bool
    {
        return $user->hasPermissionTo($modulePermission)
            || ($pagePermission !== null && $user->hasPermissionTo($pagePermission))
            || ($this->isPengusahaanOperasi($modulePermission) && $user->hasPermissionTo(PermissionName::OperasiPengusahaanInput));
    }

    /**
     * The Pengusahaan Operasi input pages (gated here with the TL/Staf
     * permission) are also filled by the operators, who hold
     * operasi.pengusahaan.input. The Berita Acara and the Laporan Pengusahaan
     * check the TL/Staf permission directly, so they stay with TL & Staf.
     */
    private function isPengusahaanOperasi(PermissionName $permission): bool
    {
        return $permission === PermissionName::OperasiPengusahaanView || $permission === PermissionName::OperasiPengusahaanWrite;
    }
}
