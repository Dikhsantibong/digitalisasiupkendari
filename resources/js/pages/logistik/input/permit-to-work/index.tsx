import { LogistikFormInputPage, logistikFormBreadcrumbs } from '@/components/logistik/form-input-page';
import type { LogistikFormInputPageProps } from '@/components/logistik/form-input-page';

/**
 * Laporan Permit To Work Pembangkit — definisi form: App\Support\LogistikForms (key `permit-to-work`).
 */
export default function PermitToWorkInput(props: LogistikFormInputPageProps) {
    return <LogistikFormInputPage {...props} />;
}

PermitToWorkInput.layout = {
    breadcrumbs: logistikFormBreadcrumbs('permit-to-work', 'Laporan Permit To Work Pembangkit'),
};
