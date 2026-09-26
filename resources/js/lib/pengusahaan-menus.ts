import { CalendarRange, ClipboardCheck, SquarePen } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import harPengusahaan from '@/routes/har/pengusahaan';
import k3Pengusahaan from '@/routes/k3/pengusahaan';
import operasiPengusahaan from '@/routes/operasi/pengusahaan';

/**
 * Akses 2 — Pengusahaan (Team Leader & Staf) of Operasi, Pemeliharaan & K3.
 *
 * Akses 1 — Laporan Project (Project Leader & Koordinator) keeps the existing
 * Jadwal / Input / Formulir menus. Akses 2 has its own menu per module (the
 * sections below, each a hub page), shown to roles holding the module's
 * `*.pengusahaan.view` permission — set per role in Role & Akses. Both
 * reports (Laporan Pembangkit & Laporan Pengusahaan) share one door: the
 * module's Laporan page, each card behind its own permission.
 *
 * To add a TL/Staf page: build the page (its controller authorises with the
 * module's pengusahaan permission, or a new permission of its own), then
 * append it to PENGUSAHAAN_MENUS with its module & section. Nothing else
 * needs to change: the hub page and the sidebar read this list.
 */
export type PengusahaanModuleKey = 'operasi' | 'har' | 'k3';

export type PengusahaanSectionKey = 'jadwal' | 'input' | 'formulir';

export const PENGUSAHAAN_MODULES: {
    key: PengusahaanModuleKey;
    title: string;
    permission: string;
    hub: (section: PengusahaanSectionKey) => string;
}[] = [
    { key: 'operasi', title: 'Operasi', permission: 'operasi.pengusahaan.view', hub: (section) => operasiPengusahaan.index(section).url },
    { key: 'har', title: 'Pemeliharaan', permission: 'har.pengusahaan.view', hub: (section) => harPengusahaan.index(section).url },
    { key: 'k3', title: 'K3 & Keamanan', permission: 'k3.pengusahaan.view', hub: (section) => k3Pengusahaan.index(section).url },
];

/** The menu sections of every module's Akses 2, in order (mirrors PengusahaanController::SECTIONS). */
export const PENGUSAHAAN_SECTIONS: { key: PengusahaanSectionKey; title: string; icon: LucideIcon }[] = [
    { key: 'jadwal', title: 'Jadwal', icon: CalendarRange },
    { key: 'input', title: 'Input', icon: SquarePen },
    { key: 'formulir', title: 'Formulir', icon: ClipboardCheck },
];

export type PengusahaanMenu = {
    module: PengusahaanModuleKey;
    section: PengusahaanSectionKey;
    title: string;
    description: string;
    icon: LucideIcon;
    /** Page URL; null while the page is still being built (shown disabled). */
    href: string | null;
    /** Shown only to users holding this permission (Role & Akses). */
    permission: string;
};

export const PENGUSAHAAN_MENUS: PengusahaanMenu[] = [
    // e.g. { module: 'har', section: 'input', title: '…', description: '…', icon: SquarePen, href: somePage.index().url, permission: 'har.pengusahaan.view' },
];
