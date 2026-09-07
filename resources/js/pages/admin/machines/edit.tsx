import { Head } from '@inertiajs/react';
import MachineController from '@/actions/App/Http/Controllers/Admin/MachineController';
import { MachineForm } from '@/components/machine-form';
import { PageHeader } from '@/components/page-header';
import { dashboard } from '@/routes';
import machines from '@/routes/admin/machines';
import type { IdName, LubricantOption, MachineRow, Option } from '@/types';

type Props = {
    machine: MachineRow;
    options: {
        units: IdName[];
        lubricant_types: LubricantOption[];
        fuel_types: Option[];
    };
};

export default function MachineEdit({ machine, options }: Props) {
    return (
        <>
            <Head title={`Ubah ${machine.name}`} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={machine.name}
                    description={`Mengubah data mesin ${machine.name}.`}
                />
                <MachineForm
                    action={MachineController.update.form(machine.id)}
                    machine={machine}
                    options={options}
                    submitLabel="Simpan Perubahan"
                />
            </div>
        </>
    );
}

MachineEdit.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Master Mesin', href: machines.index() },
        { title: 'Ubah', href: machines.index() },
    ],
};
