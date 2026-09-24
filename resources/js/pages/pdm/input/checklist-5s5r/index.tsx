import { PdmFormInputPage, pdmFormBreadcrumbs } from '@/components/pdm/form-input-page';
import type { PdmFormInputPageProps } from '@/components/pdm/form-input-page';

/**
 * Laporan Inspeksi Checklist 5S5R PdM — definisi form: App\Support\PdmForms (key `checklist-5s5r`).
 */
export default function Checklist5s5rInput(props: PdmFormInputPageProps) {
    return <PdmFormInputPage {...props} />;
}

Checklist5s5rInput.layout = {
    breadcrumbs: pdmFormBreadcrumbs('checklist-5s5r', 'Laporan Inspeksi Checklist 5S5R PdM'),
};
