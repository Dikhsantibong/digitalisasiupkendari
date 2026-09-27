import { HarFormulirPage } from '@/components/pengusahaan/har-formulir';
import type { HarFormulirProps } from '@/components/pengusahaan/har-formulir';
import { dashboard } from '@/routes';
import harPengusahaan from '@/routes/har/pengusahaan';
import batteryVoltageRoutes from '@/routes/har/pengusahaan/battery-voltage';

export default function BatteryVoltagePage(props: HarFormulirProps) {
    return (
        <HarFormulirPage
            props={props}
            description="Pengukuran tegangan per sel baterai 24 V & 110 V dan kondisi charging (floating, equalizing, boosting). Maks, min dan total dihitung otomatis."
            indexUrl={batteryVoltageRoutes.index().url}
            storeUrl={batteryVoltageRoutes.store().url}
            tables={[
                {
                    key: 'cells_24v',
                    title: 'Tegangan Sel Baterai 24 Volt',
                    rowHeader: 'Sel',
                    rowLabel: (row) => `Sel ${row.cell}`,
                    addable: { label: 'Tambah Sel 24 V', blank: (rows) => ({ cell: rows.length + 1, voltage: '' }) },
                    columns: [{ key: 'voltage', label: 'Tegangan (V)', type: 'number' }],
                },
                {
                    key: 'cells_110v',
                    title: 'Tegangan Sel Baterai 110 Volt',
                    rowHeader: 'Sel',
                    rowLabel: (row) => `Sel ${row.cell}`,
                    addable: { label: 'Tambah Sel 110 V', blank: (rows) => ({ cell: rows.length + 1, voltage: '' }) },
                    columns: [{ key: 'voltage', label: 'Tegangan (V)', type: 'number' }],
                },
                {
                    key: 'charging_conditions',
                    title: 'Kondisi Charging',
                    rowHeader: 'Mode · Item',
                    rowLabel: (row) => `${row.mode} · ${row.item}`,
                    columns: [
                        { key: 'cond_24v', label: 'Kondisi 24 V' },
                        { key: 'cond_110v', label: 'Kondisi 110 V' },
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

BatteryVoltagePage.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Formulir Pengusahaan Pemeliharaan', href: harPengusahaan.index('formulir') },
        { title: 'Tegangan Baterai', href: batteryVoltageRoutes.index() },
    ],
};
