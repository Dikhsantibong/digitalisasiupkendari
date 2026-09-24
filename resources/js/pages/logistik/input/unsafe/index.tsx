import { LogistikFormInputPage, logistikFormBreadcrumbs } from '@/components/logistik/form-input-page';
import type { LogistikFormInputPageProps } from '@/components/logistik/form-input-page';

/**
 * Laporan Unsafe Action dan Unsafe Condition — definisi form: App\Support\LogistikForms (key `unsafe`).
 */
export default function UnsafeInput(props: LogistikFormInputPageProps) {
    return <LogistikFormInputPage {...props} />;
}

UnsafeInput.layout = {
    breadcrumbs: logistikFormBreadcrumbs('unsafe', 'Laporan Unsafe Action dan Unsafe Condition'),
};
