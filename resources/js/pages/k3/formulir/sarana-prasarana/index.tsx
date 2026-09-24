import { FormulirRecordPage, recordBreadcrumbs } from '@/components/k3/formulir-record-page';
import type { FormulirRecordPageProps } from '@/components/k3/formulir-record-page';

export default function SaranaPrasaranaPage(props: FormulirRecordPageProps) {
    return <FormulirRecordPage {...props} />;
}

SaranaPrasaranaPage.layout = {
    breadcrumbs: recordBreadcrumbs('sarana-prasarana', 'Formulir Atribut, Peralatan, Administrasi & Sarana Prasarana'),
};
