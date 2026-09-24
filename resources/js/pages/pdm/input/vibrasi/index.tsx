import { PdmFormInputPage, pdmFormBreadcrumbs } from '@/components/pdm/form-input-page';
import type { PdmFormInputPageProps } from '@/components/pdm/form-input-page';

/**
 * Laporan Pengukuran Vibrasi Mesin & Generator — definisi form: App\Support\PdmForms (key `vibrasi`).
 */
export default function VibrasiInput(props: PdmFormInputPageProps) {
    return <PdmFormInputPage {...props} />;
}

VibrasiInput.layout = {
    breadcrumbs: pdmFormBreadcrumbs('vibrasi', 'Laporan Pengukuran Vibrasi Mesin & Generator'),
};
