import { HarLembarPage, harLembarBreadcrumbs } from '@/components/har/lembar-page';
import type { HarLembarPageProps } from '@/components/har/lembar-page';

/**
 * Jadwal Inventarisasi Tools & Material — definisi: App\Support\HarLembar\InventarisasiToolsLembar.
 */
export default function InventarisasiToolsLembarPage(props: HarLembarPageProps) {
    return <HarLembarPage {...props} />;
}

InventarisasiToolsLembarPage.layout = {
    breadcrumbs: harLembarBreadcrumbs('jadwal', 'inventarisasi-tools', 'Jadwal Inventarisasi Tools & Material'),
};
