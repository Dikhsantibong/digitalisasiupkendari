import { Head } from '@inertiajs/react';
import EmployeeController from '@/actions/App/Http/Controllers/Admin/EmployeeController';
import { EmployeeForm } from '@/components/employee-form';
import { PageHeader } from '@/components/page-header';
import { dashboard } from '@/routes';
import employees from '@/routes/admin/employees';
import type { EmployeeRow, IdName } from '@/types';

type Props = {
    employee: EmployeeRow;
    options: {
        units: IdName[];
    };
};

export default function EmployeeEdit({ employee, options }: Props) {
    return (
        <>
            <Head title={`Ubah ${employee.name}`} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={employee.name}
                    description={`Mengubah data pegawai ${employee.name}.`}
                />
                <EmployeeForm
                    action={EmployeeController.update.form(employee.id)}
                    employee={employee}
                    options={options}
                    submitLabel="Simpan Perubahan"
                />
            </div>
        </>
    );
}

EmployeeEdit.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Master Pegawai', href: employees.index() },
        { title: 'Ubah', href: employees.index() },
    ],
};
