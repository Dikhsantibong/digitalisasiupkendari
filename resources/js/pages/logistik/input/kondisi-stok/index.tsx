import { LogistikFormInputPage, logistikFormBreadcrumbs } from '@/components/logistik/form-input-page';
import type { LogistikFormInputPageProps } from '@/components/logistik/form-input-page';

/**
 * Laporan Kondisi Stok Tools dan Material — definisi form: App\Support\LogistikForms (key `kondisi-stok`).
 */
export default function KondisiStokInput(props: LogistikFormInputPageProps) {
    return <LogistikFormInputPage {...props} />;
}

KondisiStokInput.layout = {
    breadcrumbs: logistikFormBreadcrumbs('kondisi-stok', 'Laporan Kondisi Stok Tools dan Material'),
};
