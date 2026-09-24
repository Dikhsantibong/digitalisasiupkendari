import { LogistikFormInputPage, logistikFormBreadcrumbs } from '@/components/logistik/form-input-page';
import type { LogistikFormInputPageProps } from '@/components/logistik/form-input-page';

/**
 * Laporan Pendukung Logistik & Gudang — definisi form: App\Support\LogistikForms (key `pendukung`).
 */
export default function PendukungInput(props: LogistikFormInputPageProps) {
    return <LogistikFormInputPage {...props} />;
}

PendukungInput.layout = {
    breadcrumbs: logistikFormBreadcrumbs('pendukung', 'Laporan Pendukung Logistik & Gudang'),
};
