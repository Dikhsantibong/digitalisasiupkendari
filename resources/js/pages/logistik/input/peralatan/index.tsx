import { LogistikFormInputPage, logistikFormBreadcrumbs } from '@/components/logistik/form-input-page';
import type { LogistikFormInputPageProps } from '@/components/logistik/form-input-page';

/**
 * Laporan Peralatan, Material dan Tools Logistik & Gudang — definisi form: App\Support\LogistikForms (key `peralatan`).
 */
export default function PeralatanInput(props: LogistikFormInputPageProps) {
    return <LogistikFormInputPage {...props} />;
}

PeralatanInput.layout = {
    breadcrumbs: logistikFormBreadcrumbs('peralatan', 'Laporan Peralatan, Material dan Tools Logistik & Gudang'),
};
