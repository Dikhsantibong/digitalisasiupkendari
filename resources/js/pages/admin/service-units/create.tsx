import { Head } from '@inertiajs/react';
import ServiceUnitController from '@/actions/App/Http/Controllers/Admin/ServiceUnitController';
import { PageHeader } from '@/components/page-header';
import { ServiceUnitForm } from '@/components/service-unit-form';
import { dashboard } from '@/routes';
import serviceUnits from '@/routes/admin/service-units';

export default function ServiceUnitCreate() {
    return (
        <>
            <Head title="Tambah Unit Layanan" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Tambah Unit Layanan"
                    description="Buat kelompok unit layanan baru di bawah UP Kendari."
                />
                <ServiceUnitForm
                    action={ServiceUnitController.store.form()}
                    submitLabel="Simpan UL"
                />
            </div>
        </>
    );
}

ServiceUnitCreate.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Unit Layanan', href: serviceUnits.index() },
        { title: 'Tambah', href: serviceUnits.create() },
    ],
};
