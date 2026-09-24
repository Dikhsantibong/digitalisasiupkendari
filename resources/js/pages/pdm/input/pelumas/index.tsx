import { PdmFormInputPage, pdmFormBreadcrumbs } from '@/components/pdm/form-input-page';
import type { PdmFormInputPageProps } from '@/components/pdm/form-input-page';

/**
 * Laporan Pengukuran Kualitas Pelumas — definisi form: App\Support\PdmForms (key `pelumas`).
 */
export default function PelumasInput(props: PdmFormInputPageProps) {
    return <PdmFormInputPage {...props} />;
}

PelumasInput.layout = {
    breadcrumbs: pdmFormBreadcrumbs('pelumas', 'Laporan Pengukuran Kualitas Pelumas'),
};
