import { Head } from '@inertiajs/react';
import UnitController from '@/actions/App/Http/Controllers/Admin/UnitController';
import { PageHeader } from '@/components/page-header';
import { UnitForm } from '@/components/unit-form';
import { dashboard } from '@/routes';
import units from '@/routes/admin/units';
import type { IdName, Option, UnitRow } from '@/types';

type Props = {
    unit: UnitRow;
    options: {
        serviceUnits: IdName[];
        types: Option[];
        statuses: Option[];
    };
};

export default function UnitEdit({ unit, options }: Props) {
    return (
        <>
            <Head title={`Ubah ${unit.name}`} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={unit.name}
                    description={`Mengubah data unit ${unit.code}.`}
                />
                <UnitForm
                    action={UnitController.update.form(unit.id)}
                    unit={unit}
                    options={options}
                    submitLabel="Simpan Perubahan"
                />
            </div>
        </>
    );
}

UnitEdit.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Unit Pembangkit', href: units.index() },
        { title: 'Ubah', href: units.index() },
    ],
};
