import { PdmFormInputPage, pdmFormBreadcrumbs } from '@/components/pdm/form-input-page';
import type { PdmFormInputPageProps } from '@/components/pdm/form-input-page';

/**
 * Patrol Check Predictive Maintenance (PdM) — definisi form: App\Support\PdmForms (key `patrol-check-pdm`).
 */
export default function PatrolCheckPdmInput(props: PdmFormInputPageProps) {
    return <PdmFormInputPage {...props} />;
}

PatrolCheckPdmInput.layout = {
    breadcrumbs: pdmFormBreadcrumbs('patrol-check-pdm', 'Patrol Check Predictive Maintenance (PdM)'),
};
