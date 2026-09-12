import { Head } from '@inertiajs/react';
import MasterController from '@/actions/App/Http/Controllers/Har/MasterController';
import { MasterScreen  } from '@/components/master/master-screen';
import type {MasterScreenProps} from '@/components/master/master-screen';
import { dashboard } from '@/routes';
import master from '@/routes/har/master';

type Props = Omit<MasterScreenProps, 'routes'>;

export default function HarMasterPage(props: Props) {
    return (
        <>
            <Head title={`Master Pemeliharaan — ${props.resource.label}`} />
            <MasterScreen
                {...props}
                routes={{
                    title: 'Master Data Pemeliharaan',
                    description: 'Kelola jenis pemeliharaan, siklus, status WO, work group, dan kategori SR.',
                    indexUrl: (slug) => master.index(slug).url,
                    storeAction: (slug) => MasterController.store.form(slug),
                    updateAction: (args) => MasterController.update.form(args),
                    destroyAction: (args) => MasterController.destroy.form(args),
                }}
            />
        </>
    );
}

HarMasterPage.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Master Pemeliharaan', href: master.index('maintenance-types') },
    ],
};
