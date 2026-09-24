import { FormulirRecordPage, recordBreadcrumbs } from '@/components/k3/formulir-record-page';
import type { FormulirRecordPageProps } from '@/components/k3/formulir-record-page';

export default function PemeliharaanTpsLb3Page(props: FormulirRecordPageProps) {
    return <FormulirRecordPage {...props} />;
}

PemeliharaanTpsLb3Page.layout = {
    breadcrumbs: recordBreadcrumbs('pemeliharaan-tps-lb3', 'Formulir Pemeliharaan TPS LB3'),
};
