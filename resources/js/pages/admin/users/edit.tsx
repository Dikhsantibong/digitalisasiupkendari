import { Head } from '@inertiajs/react';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import { PageHeader } from '@/components/page-header';
import { RoleAssignmentManager } from '@/components/role-assignment-manager';
import { UserForm } from '@/components/user-form';
import { dashboard } from '@/routes';
import users from '@/routes/admin/users';
import type { IdName, UserRow } from '@/types';

type Props = {
    user: UserRow;
    options: {
        roles: {
            id: number;
            display_name: string;
            scope: string;
            scope_label: string;
        }[];
        serviceUnits: IdName[];
        units: IdName[];
    };
    canAssignRoles: boolean;
};

export default function UserEdit({ user, options, canAssignRoles }: Props) {
    return (
        <>
            <Head title={`Ubah ${user.name}`} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={user.name}
                    description={`${user.email}${user.employee_id ? ` · NIP ${user.employee_id}` : ''}`}
                />

                <UserForm
                    action={UserController.update.form(user.id)}
                    user={user}
                    submitLabel="Simpan Perubahan"
                />

                <RoleAssignmentManager
                    userId={user.id}
                    assignments={user.assignments}
                    roles={options.roles}
                    serviceUnits={options.serviceUnits}
                    units={options.units}
                    canAssign={canAssignRoles}
                />
            </div>
        </>
    );
}

UserEdit.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Pengguna', href: users.index() },
        { title: 'Ubah', href: users.index() },
    ],
};
