import { usePage } from '@inertiajs/react';
import { MobileTableCards } from '@/components/mobile/table-cards';
import { useIsMobile } from '@/hooks/use-mobile';
import { useMobileModuleCandidate } from '@/hooks/use-mobile-module';
import AppLayoutTemplate from '@/layouts/app/app-sidebar-layout';
import MobileModuleLayout, {
    MobileSplash,
} from '@/layouts/mobile/mobile-module-layout';
import PortalLayout from '@/layouts/portal/portal-layout';
import type { Auth, BreadcrumbItem } from '@/types';

/** Pages whose hand-built input tables become cards on a phone (MobileTableCards). */
const TABLE_CARD_PAGES = /^(k3|pengusahaan\/(k3|har|operasi)|operasi\/(jadwal|input)|har\/(jadwal|input|formulir)|pdm\/(jadwal|input)|logistik\/(jadwal|input))\//;

export default function AppLayout({
    breadcrumbs = [],
    children,
}: {
    breadcrumbs?: BreadcrumbItem[];
    children: React.ReactNode;
}) {
    // On a phone, pages of a module registered in layouts/mobile/modules.ts
    // swap to the sidebar-less mobile shell.
    const candidate = useMobileModuleCandidate();
    const isMobile = useIsMobile();
    const { component, props } = usePage();
    const page = (
        <MobileTableCards enabled={isMobile && TABLE_CARD_PAGES.test(component)}>
            {children}
        </MobileTableCards>
    );

    // Kantor induk UP Kendari & Manager UL: the view-focused Portal Pemantauan instead of the sidebar app.
    if ((props.auth as Auth | undefined)?.usesPortal) {
        return <PortalLayout breadcrumbs={breadcrumbs}>{page}</PortalLayout>;
    }

    if (candidate && isMobile) {
        return (
            <MobileModuleLayout resolved={candidate}>{page}</MobileModuleLayout>
        );
    }

    const layout = (
        <AppLayoutTemplate breadcrumbs={breadcrumbs}>{page}</AppLayoutTemplate>
    );

    if (candidate) {
        // The server renders before the viewport is known: small screens show
        // the splash (not the desktop page and its dashboard charts) until the
        // browser switches to the mobile shell.
        return (
            <>
                <div className="md:hidden">
                    <MobileSplash />
                </div>
                <div className="hidden md:contents">{layout}</div>
            </>
        );
    }

    return layout;
}
