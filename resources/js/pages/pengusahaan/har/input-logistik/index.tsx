import { LogistikFormInputPage } from '@/components/logistik/form-input-page';
import type { LogistikFormInputPageProps } from '@/components/logistik/form-input-page';
import { dashboard } from '@/routes';
import harPengusahaan from '@/routes/har/pengusahaan';
import inputLogistik from '@/routes/har/pengusahaan/input-logistik';

/**
 * Akses 2 — Input Logistik (TL Pemeliharaan / TL Ophar & Staf Pemeliharaan):
 * kondisi stok tools & material with Dasar Stok (Cashcard, SPPL, Internal,
 * Kontrak) and Nomor Surat / Kontrak. Form definition:
 * App\Support\LogistikForms\InputLogistikForm; served by Har\InputLogistikController.
 */
export default function InputLogistikPage(props: LogistikFormInputPageProps) {
    return <LogistikFormInputPage {...props} />;
}

InputLogistikPage.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Input Pemeliharaan', href: harPengusahaan.index('input') },
        { title: 'Input Logistik', href: inputLogistik.index() },
    ],
};
