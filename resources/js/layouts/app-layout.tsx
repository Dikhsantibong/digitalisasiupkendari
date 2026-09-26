import { useIsMobile } from '@/hooks/use-mobile';
import { useMobileModuleCandidate } from '@/hooks/use-mobile-module';
import AppLayoutTemplate from '@/layouts/app/app-sidebar-layout';
import MobileModuleLayout, {
    MobileSplash,
} from '@/layouts/mobile/mobile-module-layout';
import type { BreadcrumbItem } from '@/types';

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

    if (candidate && isMobile) {
        return (
            <MobileModuleLayout resolved={candidate}>
                {children}
            </MobileModuleLayout>
        );
    }

    const layout = (
        <AppLayoutTemplate breadcrumbs={breadcrumbs}>
            {children}
        </AppLayoutTemplate>
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
