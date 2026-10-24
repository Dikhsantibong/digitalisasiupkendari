import { UnsafeConditionSheet } from '@/components/k3/unsafe-condition-sheet';
import type { UnsafeConditionSheetProps } from '@/components/k3/unsafe-condition-sheet';
import { dashboard } from '@/routes';
import harInput from '@/routes/har/input';
import unsafeConditionRoutes from '@/routes/har/input/unsafe-condition';

/** Input Pemeliharaan "Unsafe Action & Unsafe Condition" (rows feed the K3 module). */
export default function UnsafeConditionsInput(props: UnsafeConditionSheetProps) {
    return (
        <UnsafeConditionSheet
            {...props}
            routes={{
                index: unsafeConditionRoutes.index().url,
                sync: unsafeConditionRoutes.sync().url,
                pdf: (query) => unsafeConditionRoutes.pdf({ query }).url,
            }}
        />
    );
}

UnsafeConditionsInput.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Input Pemeliharaan', href: harInput.index() },
        { title: 'Unsafe Action & Condition', href: unsafeConditionRoutes.index() },
    ],
};
