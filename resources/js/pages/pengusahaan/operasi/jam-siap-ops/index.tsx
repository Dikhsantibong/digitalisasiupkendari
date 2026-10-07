import { Link } from '@inertiajs/react';
import { JamMesinPage } from '@/components/operasi/jam-mesin-page';
import type { JamMesinPageProps } from '@/components/operasi/jam-mesin-page';
import jamGangguan from '@/routes/operasi/pengusahaan/jam-gangguan';
import jamOperasi from '@/routes/operasi/pengusahaan/jam-operasi';
import jamPemeliharaan from '@/routes/operasi/pengusahaan/jam-pemeliharaan';
import jamSiapOps from '@/routes/operasi/pengusahaan/jam-siap-ops';

type Props = JamMesinPageProps & {
    over: { machine_id: number; day: number }[];
    filled: { operasi: boolean; pemeliharaan: boolean; gangguan: boolean };
};

const SOURCES = [
    { key: 'operasi', title: 'Jam Operasi', route: jamOperasi },
    { key: 'pemeliharaan', title: 'Jam Pemeliharaan', route: jamPemeliharaan },
    { key: 'gangguan', title: 'Jam Gangguan', route: jamGangguan },
] as const;

/** Jam Siap Operasi = 24 jam − Jam Operasi − Jam Pemeliharaan − Jam Gangguan, per mesin per hari (read-only). */
export default function JamSiapOpsIndex({ over, filled, ...props }: Props) {
    const query = {
        unit_id: props.filters.unit_id,
        month: props.filters.month,
        year: props.filters.year,
    };
    const missing = SOURCES.filter((source) => !filled[source.key]);

    return (
        <JamMesinPage
            {...props}
            title="Jam Siap Operasi"
            description="Dihitung otomatis: 24 jam dikurangi Jam Operasi, Jam Pemeliharaan, dan Jam Gangguan per mesin per hari"
            routes={{ index: jamSiapOps.index, pdf: jamSiapOps.pdf }}
            highlight={
                new Set(over.map((cell) => `${cell.machine_id}-${cell.day}`))
            }
            notice={
                <>
                    {missing.length > 0 && (
                        <p className="flex flex-wrap items-center gap-x-3 gap-y-1 rounded-md border border-amber-300 bg-amber-50 px-4 py-3 text-[13px] text-amber-900">
                            <span>Belum disimpan untuk periode ini:</span>
                            {missing.map((source) => (
                                <Link
                                    key={source.key}
                                    href={source.route.index({ query }).url}
                                    className="font-medium underline"
                                >
                                    {source.title}
                                </Link>
                            ))}
                        </p>
                    )}
                    {over.length > 0 && (
                        <p className="rounded-md border border-red-300 bg-red-50 px-4 py-3 text-[13px] text-red-800">
                            Ada {over.length} hari-mesin yang jumlah jam
                            operasi, pemeliharaan, dan gangguannya lebih dari 24
                            jam (ditandai merah, siap operasi negatif). Periksa
                            kembali isiannya.
                        </p>
                    )}
                </>
            }
        />
    );
}
