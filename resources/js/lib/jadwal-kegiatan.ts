/**
 * The groups of a divisi's Jadwal Kegiatan (user, 2026-11-25): every divisi
 * plans its activities as I. Harian, II. Mingguan, III. Bulanan and
 * IV. Non Rutin. Mirrors App\Support\JadwalKegiatanGroups.
 */
export type KegiatanGrup = 'harian' | 'mingguan' | 'bulanan' | 'non_rutin';

export const KEGIATAN_GROUPS: {
    key: KegiatanGrup;
    roman: string;
    label: string;
}[] = [
    { key: 'harian', roman: 'I', label: 'Harian' },
    { key: 'mingguan', roman: 'II', label: 'Mingguan' },
    { key: 'bulanan', roman: 'III', label: 'Bulanan' },
    { key: 'non_rutin', roman: 'IV', label: 'Non Rutin' },
];
