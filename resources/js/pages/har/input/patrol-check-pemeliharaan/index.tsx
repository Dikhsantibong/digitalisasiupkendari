import { HarLembarPage, harLembarBreadcrumbs } from '@/components/har/lembar-page';
import type { HarLembarPageProps } from '@/components/har/lembar-page';

/**
 * Laporan Patrol Check Pemeliharaan — definisi: App\Support\HarLembar\PatrolCheckPemeliharaanLembar.
 */
export default function PatrolCheckPemeliharaanLembarPage(props: HarLembarPageProps) {
    return <HarLembarPage {...props} />;
}

PatrolCheckPemeliharaanLembarPage.layout = {
    breadcrumbs: harLembarBreadcrumbs('input', 'patrol-check-pemeliharaan', 'Laporan Patrol Check Pemeliharaan'),
};
