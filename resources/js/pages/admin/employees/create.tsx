import { Head } from '@inertiajs/react';
import EmployeeController from '@/actions/App/Http/Controllers/Admin/EmployeeController';
import { EmployeeForm } from '@/components/employee-form';
import { PageHeader } from '@/components/page-header';
import { dashboard } from '@/routes';
import employees from '@/routes/admin/employees';
import type { IdName } from '@/types';

type Props = {
    options: {
        units: IdName[];
    };
};

export default function EmployeeCreate({ options }: Props) {
    return (
        <>
            <Head title="Tambah Pegawai" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Tambah Pegawai"
                    description="Daftarkan pegawai baru dan tempatkan pada unit pembangkit."
                />
                <EmployeeForm
                    action={EmployeeController.store.form()}
                    options={options}
                    submitLabel="Simpan Pegawai"
                />
            </div>
        </>
    );
}

EmployeeCreate.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Master Pegawai', href: employees.index() },
        { title: 'Tambah', href: employees.create() },
    ],
};
