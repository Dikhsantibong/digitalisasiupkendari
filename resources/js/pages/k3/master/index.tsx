import { Head } from '@inertiajs/react';
import MasterController from '@/actions/App/Http/Controllers/K3/MasterController';
import { MasterScreen } from '@/components/master/master-screen';
import type { MasterScreenProps } from '@/components/master/master-screen';
import { dashboard } from '@/routes';
import master from '@/routes/k3/master';

type Props = Omit<MasterScreenProps, 'routes'>;

export default function K3MasterPage(props: Props) {
    return (
        <>
            <Head title={`Master K3 & Keamanan — ${props.resource.label}`} />
            <MasterScreen
                {...props}
                routes={{
                    title: 'Master Data K3 & Keamanan',
                    description: 'Kelola jenis kegiatan, lokasi patroli, fasilitas darurat, APD, APAR, kotak P3K, dan item checklist.',
                    indexUrl: (slug) => master.index(slug).url,
                    storeAction: (slug) => MasterController.store.form(slug),
                    updateAction: (args) => MasterController.update.form(args),
                    destroyAction: (args) => MasterController.destroy.form(args),
                }}
            />
        </>
    );
}

K3MasterPage.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Master K3 & Keamanan', href: master.index('activity-types') },
    ],
};
