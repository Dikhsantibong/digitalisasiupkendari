import { HarFormulirPage } from '@/components/pengusahaan/har-formulir';
import type { HarFormulirProps } from '@/components/pengusahaan/har-formulir';
import { dashboard } from '@/routes';
import harPengusahaan from '@/routes/har/pengusahaan';
import motorCurrentRoutes from '@/routes/har/pengusahaan/motor-current';

export default function MotorCurrentPage(props: HarFormulirProps) {
    return (
        <HarFormulirPage
            props={props}
            description="Pencatatan arus kerja elektro motor auxiliary mesin per fasa (R, S, T)."
            indexUrl={motorCurrentRoutes.index().url}
            storeUrl={motorCurrentRoutes.store().url}
            tables={[
                {
                    key: 'items',
                    title: 'Arus per Phasa (Ampere)',
                    rowHeader: 'No',
                    rowLabel: (row, index) => `${index + 1}. ${row.motor_name || 'Motor'}`,
                    addable: { label: 'Tambah Elektro Motor', blank: (rows) => ({ no: rows.length + 1, motor_name: '', current_r: '', current_s: '', current_t: '', notes: '' }) },
                    columns: [
                        { key: 'motor_name', label: 'Nama Elektro Motor', wide: true },
                        { key: 'current_r', label: 'R (A)', type: 'number' },
                        { key: 'current_s', label: 'S (A)', type: 'number' },
                        { key: 'current_t', label: 'T (A)', type: 'number' },
                        { key: 'notes', label: 'Keterangan', wide: true },
                    ],
                },
            ]}
            notes={[
                { key: 'notes', label: 'Catatan', type: 'textarea', wide: true },
            ]}
        />
    );
}

MotorCurrentPage.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Formulir Pengusahaan Pemeliharaan', href: harPengusahaan.index('formulir') },
        { title: 'Arus Elektro Motor', href: motorCurrentRoutes.index() },
    ],
};
