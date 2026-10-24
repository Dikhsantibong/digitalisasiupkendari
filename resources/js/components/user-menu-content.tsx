import { Link, router, usePage } from '@inertiajs/react';
import { LogIn, LogOut, Settings, Users } from 'lucide-react';
import type { MouseEvent } from 'react';
import { stopImpersonation } from '@/components/impersonation/impersonation-banner';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { UserInfo } from '@/components/user-info';
import { useMobileNavigation } from '@/hooks/use-mobile-navigation';
import { openAccountSwitcher } from '@/lib/impersonation';
import { detachPushForLogout } from '@/lib/notifications';
import { logout } from '@/routes';
import { edit } from '@/routes/profile';
import type { Auth, User } from '@/types';

type Props = {
    user: User;
    /** The phone shell of the field roles hides the Settings entry. */
    showSettings?: boolean;
};

export function UserMenuContent({ user, showSettings = true }: Props) {
    const cleanup = useMobileNavigation();
    const auth = usePage().props.auth as Auth | undefined;

    // Unlink this device's push first (needs the session), then log out.
    const handleLogout = (event: MouseEvent) => {
        event.preventDefault();
        void detachPushForLogout().finally(() => {
            cleanup();
            router.flushAll();
            router.post(logout().url);
        });
    };

    return (
        <>
            <DropdownMenuLabel className="p-0 font-normal">
                <div className="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                    <UserInfo user={user} showEmail={true} />
                </div>
            </DropdownMenuLabel>
            {showSettings && (
                <>
                    <DropdownMenuSeparator />
                    <DropdownMenuGroup>
                        <DropdownMenuItem asChild>
                            <Link
                                className="block w-full cursor-pointer"
                                href={edit()}
                                prefetch
                                onClick={cleanup}
                            >
                                <Settings className="mr-2" />
                                Settings
                            </Link>
                        </DropdownMenuItem>
                    </DropdownMenuGroup>
                </>
            )}
            {(auth?.canImpersonate || auth?.impersonator) && (
                <>
                    <DropdownMenuSeparator />
                    {auth?.impersonator ? (
                        <DropdownMenuItem
                            className="cursor-pointer"
                            onSelect={() => stopImpersonation()}
                        >
                            <LogIn className="mr-2" />
                            Kembali ke Super Admin
                        </DropdownMenuItem>
                    ) : (
                        <DropdownMenuItem
                            className="cursor-pointer"
                            onSelect={() => openAccountSwitcher()}
                        >
                            <Users className="mr-2" />
                            Pindah Akun
                            <span className="ml-auto text-[11px] text-muted-foreground">
                                Ctrl+Shift+K
                            </span>
                        </DropdownMenuItem>
                    )}
                </>
            )}
            <DropdownMenuSeparator />
            <DropdownMenuItem asChild>
                <Link
                    className="block w-full cursor-pointer"
                    href={logout()}
                    as="button"
                    onClick={handleLogout}
                    data-test="logout-button"
                >
                    <LogOut className="mr-2" />
                    Log out
                </Link>
            </DropdownMenuItem>
        </>
    );
}
