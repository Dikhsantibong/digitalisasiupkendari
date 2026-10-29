import { usePage } from '@inertiajs/react';
import { createContext, useCallback, useContext, useMemo, useState } from 'react';
import type { ReactNode } from 'react';
import { SidebarProvider } from '@/components/ui/sidebar';
import type { AppVariant } from '@/types';

type Props = {
    children: ReactNode;
    variant?: AppVariant;
};

type SidebarPin = {
    /** Locked open; otherwise the desktop sidebar is an icon rail that opens while hovered. */
    pinned: boolean;
    togglePinned: () => void;
    setPeek: (peek: boolean) => void;
};

const SidebarPinContext = createContext<SidebarPin>({ pinned: true, togglePinned: () => {}, setPeek: () => {} });

export const useSidebarPin = () => useContext(SidebarPinContext);

export function AppShell({ children, variant = 'sidebar' }: Props) {
    const isOpen = usePage().props.sidebarOpen;
    // The sidebar_state cookie keeps whether the sidebar is locked open.
    const [pinned, setPinned] = useState<boolean>(isOpen);
    const [peek, setPeek] = useState(false);

    const togglePinned = useCallback(() => {
        setPinned((current) => {
            document.cookie = `sidebar_state=${!current}; path=/; max-age=${60 * 60 * 24 * 7}`;

            return !current;
        });
        setPeek(false);
    }, []);

    const pin = useMemo(() => ({ pinned, togglePinned, setPeek }), [pinned, togglePinned]);

    if (variant === 'header') {
        return (
            <div className="flex min-h-screen w-full flex-col">{children}</div>
        );
    }

    return (
        <SidebarPinContext.Provider value={pin}>
            <SidebarProvider
                open={pinned || peek}
                onOpenChange={(open) => {
                    // The header trigger / Ctrl+B lock or release the sidebar.
                    setPinned(open);
                    setPeek(false);
                }}
            >
                {children}
            </SidebarProvider>
        </SidebarPinContext.Provider>
    );
}
