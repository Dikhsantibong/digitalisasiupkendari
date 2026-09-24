import { LogistikFormInputPage, logistikFormBreadcrumbs } from '@/components/logistik/form-input-page';
import type { LogistikFormInputPageProps } from '@/components/logistik/form-input-page';

/**
 * Laporan Inventaris Lainnya Logistik & Gudang — definisi form: App\Support\LogistikForms (key `inventaris-lainnya`).
 */
export default function InventarisLainnyaInput(props: LogistikFormInputPageProps) {
    return <LogistikFormInputPage {...props} />;
}

InventarisLainnyaInput.layout = {
    breadcrumbs: logistikFormBreadcrumbs('inventaris-lainnya', 'Laporan Inventaris Lainnya Logistik & Gudang'),
};
