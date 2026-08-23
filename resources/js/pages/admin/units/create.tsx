import { Head } from '@inertiajs/react';
import UnitController from '@/actions/App/Http/Controllers/Admin/UnitController';
import { PageHeader } from '@/components/page-header';
import { UnitForm } from '@/components/unit-form';
import { dashboard } from '@/routes';
import units from '@/routes/admin/units';
import type { IdName, Option } from '@/types';

type Props = {
    options: {
        serviceUnits: IdName[];
        types: Option[];
        statuses: Option[];
    };
};

export default function UnitCreate({ options }: Props) {
    return (
        <>
            <Head title="Tambah Unit Pembangkit" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Tambah Unit Pembangkit"
                    description="Daftarkan unit pembangkit baru ke dalam struktur UP Kendari."
                />
                <UnitForm
                    action={UnitController.store.form()}
                    options={options}
                    submitLabel="Simpan Unit"
                />
            </div>
        </>
    );
}

UnitCreate.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Unit Pembangkit', href: units.index() },
        { title: 'Tambah', href: units.create() },
    ],
};
