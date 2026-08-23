import { Head } from '@inertiajs/react';
import RoleController from '@/actions/App/Http/Controllers/Admin/RoleController';
import { PageHeader } from '@/components/page-header';
import { RoleForm } from '@/components/role-form';
import { dashboard } from '@/routes';
import roles from '@/routes/admin/roles';
import type { Option, PermissionGroupOption } from '@/types';

type Props = {
    permissionGroups: PermissionGroupOption[];
    scopes: Option[];
};

export default function RoleCreate({ permissionGroups, scopes }: Props) {
    return (
        <>
            <Head title="Tambah Role" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Tambah Role"
                    description="Buat role baru dan tentukan permission yang dimilikinya."
                />
                <RoleForm
                    action={RoleController.store.form()}
                    permissionGroups={permissionGroups}
                    scopes={scopes}
                    submitLabel="Simpan Role"
                />
            </div>
        </>
    );
}

RoleCreate.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Role & Akses', href: roles.index() },
        { title: 'Tambah', href: roles.create() },
    ],
};
