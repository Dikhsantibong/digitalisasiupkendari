import { Head } from '@inertiajs/react';
import RoleController from '@/actions/App/Http/Controllers/Admin/RoleController';
import { PageHeader } from '@/components/page-header';
import { RoleForm } from '@/components/role-form';
import { StatusBadge } from '@/components/status-badge';
import { dashboard } from '@/routes';
import roles from '@/routes/admin/roles';
import type { Option, PermissionGroupOption } from '@/types';

type Props = {
    role: {
        id: number;
        name: string;
        display_name: string;
        scope: string;
        description: string | null;
        is_system: boolean;
        permissions: string[];
    };
    permissionGroups: PermissionGroupOption[];
    scopes: Option[];
};

export default function RoleEdit({ role, permissionGroups, scopes }: Props) {
    return (
        <>
            <Head title={`Ubah ${role.display_name}`} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={role.display_name}
                    description="Atur permission yang dimiliki role ini."
                    actions={
                        <StatusBadge tone={role.is_system ? 'info' : 'neutral'}>
                            {role.is_system ? 'Role Sistem' : 'Role Kustom'}
                        </StatusBadge>
                    }
                />
                <RoleForm
                    action={RoleController.update.form(role.id)}
                    role={role}
                    permissionGroups={permissionGroups}
                    scopes={scopes}
                    submitLabel="Simpan Perubahan"
                />
            </div>
        </>
    );
}

RoleEdit.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Role & Akses', href: roles.index() },
        { title: 'Ubah', href: roles.index() },
    ],
};
