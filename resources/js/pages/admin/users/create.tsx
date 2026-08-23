import { Head } from '@inertiajs/react';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import { PageHeader } from '@/components/page-header';
import { UserForm } from '@/components/user-form';
import { dashboard } from '@/routes';
import users from '@/routes/admin/users';

export default function UserCreate() {
    return (
        <>
            <Head title="Tambah Pengguna" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Tambah Pengguna"
                    description="Buat akun baru. Penugasan role dilakukan setelah akun tersimpan."
                />
                <UserForm
                    action={UserController.store.form()}
                    submitLabel="Simpan Pengguna"
                />
            </div>
        </>
    );
}

UserCreate.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Pengguna', href: users.index() },
        { title: 'Tambah', href: users.create() },
    ],
};
