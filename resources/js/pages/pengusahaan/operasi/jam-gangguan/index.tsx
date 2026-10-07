import { JamMesinPage } from '@/components/operasi/jam-mesin-page';
import type {
    JamMesinPageProps,
    JamReadings,
} from '@/components/operasi/jam-mesin-page';
import jam from '@/routes/operasi/pengusahaan/jam-gangguan';

type Props = JamMesinPageProps & {
    star_stop: JamReadings;
    catatan: string;
    saved_at: string | null;
    can_write: boolean;
};

export default function JamGangguanIndex({
    star_stop,
    catatan,
    saved_at,
    can_write,
    ...props
}: Props) {
    return (
        <JamMesinPage
            {...props}
            title="Jam Gangguan"
            description="Jam gangguan mesin per hari (jam, maks. 24 per hari), bisa diambil dari Star-Stop"
            routes={{ index: jam.index, store: jam.store, pdf: jam.pdf }}
            editable={{ star_stop, catatan, saved_at, can_write }}
        />
    );
}
