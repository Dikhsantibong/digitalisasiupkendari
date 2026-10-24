import { UnsafeConditionSheet } from '@/components/k3/unsafe-condition-sheet';
import type { UnsafeConditionSheetProps } from '@/components/k3/unsafe-condition-sheet';
import { dashboard } from '@/routes';
import operasiInput from '@/routes/operasi/input';
import unsafeConditionRoutes from '@/routes/operasi/input/unsafe-condition';

/** Input Operasi "Unsafe Action & Unsafe Condition" (rows feed the K3 module). */
export default function OperasiUnsafeConditionsPage(props: UnsafeConditionSheetProps) {
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

OperasiUnsafeConditionsPage.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Input Operasi', href: operasiInput.index() },
        { title: 'Unsafe Action & Condition', href: unsafeConditionRoutes.index() },
    ],
};
