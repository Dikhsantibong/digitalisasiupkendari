import { Head } from '@inertiajs/react';
import MachineController from '@/actions/App/Http/Controllers/Admin/MachineController';
import { MachineForm } from '@/components/machine-form';
import { PageHeader } from '@/components/page-header';
import { dashboard } from '@/routes';
import machines from '@/routes/admin/machines';
import type { IdName, LubricantOption, Option } from '@/types';

type Props = {
    options: {
        units: IdName[];
        lubricant_types: LubricantOption[];
        fuel_types: Option[];
    };
};

export default function MachineCreate({ options }: Props) {
    return (
        <>
            <Head title="Tambah Mesin" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Tambah Mesin"
                    description="Daftarkan mesin pembangkit baru pada unit yang dipilih."
                />
                <MachineForm
                    action={MachineController.store.form()}
                    options={options}
                    submitLabel="Simpan Mesin"
                />
            </div>
        </>
    );
}

MachineCreate.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Master Mesin', href: machines.index() },
        { title: 'Tambah', href: machines.create() },
    ],
};
