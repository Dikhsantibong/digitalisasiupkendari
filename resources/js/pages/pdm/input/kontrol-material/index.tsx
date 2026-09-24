import { PdmFormInputPage, pdmFormBreadcrumbs } from '@/components/pdm/form-input-page';
import type { PdmFormInputPageProps } from '@/components/pdm/form-input-page';

/**
 * Form Kontrol Material, Peralatan & Tools PdM — definisi form: App\Support\PdmForms (key `kontrol-material`).
 */
export default function KontrolMaterialInput(props: PdmFormInputPageProps) {
    return <PdmFormInputPage {...props} />;
}

KontrolMaterialInput.layout = {
    breadcrumbs: pdmFormBreadcrumbs('kontrol-material', 'Form Kontrol Material, Peralatan & Tools PdM'),
};
