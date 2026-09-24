import { HarLembarPage, harLembarBreadcrumbs } from '@/components/har/lembar-page';
import type { HarLembarPageProps } from '@/components/har/lembar-page';

/**
 * Jadwal Pemeriksaan Instalasi Blackstart — definisi: App\Support\HarLembar\PemeriksaanBlackstartLembar.
 */
export default function PemeriksaanBlackstartLembarPage(props: HarLembarPageProps) {
    return <HarLembarPage {...props} />;
}

PemeriksaanBlackstartLembarPage.layout = {
    breadcrumbs: harLembarBreadcrumbs('jadwal', 'pemeriksaan-blackstart', 'Jadwal Pemeriksaan Instalasi Blackstart'),
};
