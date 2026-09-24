import { FormulirRecordPage, recordBreadcrumbs } from '@/components/k3/formulir-record-page';
import type { FormulirRecordPageProps } from '@/components/k3/formulir-record-page';

export default function KontrolK3MingguanPage(props: FormulirRecordPageProps) {
    return <FormulirRecordPage {...props} />;
}

KontrolK3MingguanPage.layout = {
    breadcrumbs: recordBreadcrumbs('kontrol-mingguan', 'Form Kontrol K3 Mingguan'),
};
