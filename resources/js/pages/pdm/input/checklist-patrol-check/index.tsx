import { PdmFormInputPage, pdmFormBreadcrumbs } from '@/components/pdm/form-input-page';
import type { PdmFormInputPageProps } from '@/components/pdm/form-input-page';

/**
 * Laporan Checklist Patrol Check PdM — definisi form: App\Support\PdmForms (key `checklist-patrol-check`).
 */
export default function ChecklistPatrolCheckInput(props: PdmFormInputPageProps) {
    return <PdmFormInputPage {...props} />;
}

ChecklistPatrolCheckInput.layout = {
    breadcrumbs: pdmFormBreadcrumbs('checklist-patrol-check', 'Laporan Checklist Patrol Check PdM'),
};
