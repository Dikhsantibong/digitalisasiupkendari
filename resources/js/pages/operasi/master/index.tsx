import { Head } from '@inertiajs/react';
import MasterController from '@/actions/App/Http/Controllers/Operasi/MasterController';
import { MasterScreen  } from '@/components/master/master-screen';
import type {MasterScreenProps} from '@/components/master/master-screen';
import { dashboard } from '@/routes';
import master from '@/routes/operasi/master';

type Props = Omit<MasterScreenProps, 'routes'>;

export default function OperasiMasterPage(props: Props) {
    return (
        <>
            <Head title={`Master Operasi — ${props.resource.label}`} />
            <MasterScreen
                {...props}
                routes={{
                    title: 'Master Data Operasi',
                    description: 'Kelola feeder, tangki, pelumas, faktor kalibrasi, dan kode status.',
                    indexUrl: (slug) => master.index(slug).url,
                    storeAction: (slug) => MasterController.store.form(slug),
                    updateAction: (args) => MasterController.update.form(args),
                    destroyAction: (args) => MasterController.destroy.form(args),
                }}
            />
        </>
    );
}

OperasiMasterPage.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Master Operasi', href: master.index('feeders') },
    ],
};
