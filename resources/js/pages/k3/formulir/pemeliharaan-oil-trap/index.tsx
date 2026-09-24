import { FormulirRecordPage, recordBreadcrumbs } from '@/components/k3/formulir-record-page';
import type { FormulirRecordPageProps } from '@/components/k3/formulir-record-page';

export default function PemeliharaanOilTrapPage(props: FormulirRecordPageProps) {
    return <FormulirRecordPage {...props} />;
}

PemeliharaanOilTrapPage.layout = {
    breadcrumbs: recordBreadcrumbs('pemeliharaan-oil-trap', 'Formulir Pemeliharaan Oil Trap'),
};
