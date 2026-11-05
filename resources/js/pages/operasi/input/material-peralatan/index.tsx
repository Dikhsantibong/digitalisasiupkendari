import { MaterialPeralatanPage } from '@/components/material-peralatan/material-peralatan-page';
import type { MaterialPeralatanPageProps } from '@/components/material-peralatan/material-peralatan-page';
import { dashboard } from '@/routes';
import operasiInput from '@/routes/operasi/input';
import materialPeralatan from '@/routes/operasi/input/material-peralatan';

/**
 * Input Operasi "Laporan Material dan Peralatan": the Koordinator / Project
 * Leader prepares the items and their stock; the operators check each item.
 */
export default function MaterialPeralatanInput(props: MaterialPeralatanPageProps) {
    return <MaterialPeralatanPage {...props} routes={materialPeralatan} copy={{ manager: 'Koordinator / Project Leader', checker: 'operator' }} />;
}

MaterialPeralatanInput.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Input Operasi', href: operasiInput.index() },
        { title: 'Material & Peralatan', href: materialPeralatan.index() },
    ],
};
