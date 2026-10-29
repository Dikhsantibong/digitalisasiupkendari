import { useSidebar } from '@/components/ui/sidebar';

/**
 * Sidebar logo: the PLN icon with "Management Pembangkit" stacked beside it;
 * the folded (icon-only) sidebar shows the icon alone.
 */
export default function AppLogo() {
    const { state, isMobile } = useSidebar();
    const isCollapsed = state === 'collapsed' && !isMobile;

    return (
        <div className="flex w-full items-center gap-2.5">
            <div className="flex shrink-0 items-center justify-center rounded-md bg-white p-1 shadow-sm">
                <img
                    src="/logo/icon.png"
                    alt="PLN"
                    className={isCollapsed ? 'h-6 w-auto object-contain' : 'h-8 w-auto object-contain'}
                />
            </div>
            {!isCollapsed && (
                <div className="flex min-w-0 flex-col leading-tight">
                    <span className="truncate text-[15px] font-bold tracking-wide uppercase">Management</span>
                    <span className="truncate text-[11px] font-semibold tracking-[0.2em] uppercase opacity-80">Pembangkit</span>
                </div>
            )}
        </div>
    );
}
