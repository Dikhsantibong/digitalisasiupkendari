import { useMobileModule } from '@/hooks/use-mobile-module';
import AppLayoutTemplate from '@/layouts/app/app-sidebar-layout';
import MobileModuleLayout from '@/layouts/mobile/mobile-module-layout';
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
    const mobileModule = useMobileModule();

    if (mobileModule) {
        return (
            <MobileModuleLayout resolved={mobileModule}>
                {children}
            </MobileModuleLayout>
        );
    }

    return (
        <AppLayoutTemplate breadcrumbs={breadcrumbs}>
            {children}
        </AppLayoutTemplate>
    );
}
