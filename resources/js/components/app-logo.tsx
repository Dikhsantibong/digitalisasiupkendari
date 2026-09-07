import { useSidebar } from '@/components/ui/sidebar';

export default function AppLogo() {
    const { state, isMobile } = useSidebar();
    const isCollapsed = state === 'collapsed' && !isMobile;

    return (
        <div className="flex w-full items-center justify-center">
            <div className="flex items-center justify-center rounded-md bg-white p-2 shadow-sm">
                <img
                    src={isCollapsed ? '/logo/icon.png' : '/logo/sidebar-logo.png'}
                    alt="PLN Nusantara Power"
                    className={isCollapsed ? 'h-6 w-6 object-contain' : 'h-8 w-auto'}
                />
            </div>
        </div>
    );
}
