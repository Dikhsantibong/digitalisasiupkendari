import { Head } from '@inertiajs/react';
import ServiceUnitController from '@/actions/App/Http/Controllers/Admin/ServiceUnitController';
import { PageHeader } from '@/components/page-header';
import { ServiceUnitForm } from '@/components/service-unit-form';
import { dashboard } from '@/routes';
import serviceUnits from '@/routes/admin/service-units';

type Props = {
    serviceUnit: {
        id: number;
        code: string;
        name: string;
        description: string | null;
        is_active: boolean;
    };
};

export default function ServiceUnitEdit({ serviceUnit }: Props) {
    return (
        <>
            <Head title={`Ubah ${serviceUnit.name}`} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={serviceUnit.name}
                    description={`Mengubah data unit layanan ${serviceUnit.code}.`}
                />
                <ServiceUnitForm
                    action={ServiceUnitController.update.form(serviceUnit.id)}
                    serviceUnit={serviceUnit}
                    submitLabel="Simpan Perubahan"
                />
            </div>
        </>
    );
}

ServiceUnitEdit.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Unit Layanan', href: serviceUnits.index() },
        { title: 'Ubah', href: serviceUnits.index() },
    ],
};
