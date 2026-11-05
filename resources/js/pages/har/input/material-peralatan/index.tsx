import { MaterialPeralatanPage } from '@/components/material-peralatan/material-peralatan-page';
import type { MaterialPeralatanPageProps } from '@/components/material-peralatan/material-peralatan-page';
import { dashboard } from '@/routes';
import harInput from '@/routes/har/input';
import materialPeralatan from '@/routes/har/input/material-peralatan';

/**
 * Input Pemeliharaan "Laporan Material dan Peralatan": the Koordinator
 * Pemeliharaan prepares the items and their stock; Harmes / Harlist check
 * each item.
 */
export default function HarMaterialPeralatanInput(props: MaterialPeralatanPageProps) {
    return <MaterialPeralatanPage {...props} routes={materialPeralatan} copy={{ manager: 'Koordinator Pemeliharaan', checker: 'teknisi (Harmes / Harlist)' }} />;
}

HarMaterialPeralatanInput.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Input Pemeliharaan', href: harInput.index() },
        { title: 'Material & Peralatan', href: materialPeralatan.index() },
    ],
};
