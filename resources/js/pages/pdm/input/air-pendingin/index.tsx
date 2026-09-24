import { PdmFormInputPage, pdmFormBreadcrumbs } from '@/components/pdm/form-input-page';
import type { PdmFormInputPageProps } from '@/components/pdm/form-input-page';

/**
 * Laporan Pengukuran Kualitas Air Pendingin — definisi form: App\Support\PdmForms (key `air-pendingin`).
 */
export default function AirPendinginInput(props: PdmFormInputPageProps) {
    return <PdmFormInputPage {...props} />;
}

AirPendinginInput.layout = {
    breadcrumbs: pdmFormBreadcrumbs('air-pendingin', 'Laporan Pengukuran Kualitas Air Pendingin'),
};
