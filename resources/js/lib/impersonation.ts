/** A user offered by the Pindah Akun switcher (admin.impersonation.users). */
export type SwitchableUser = {
    id: number;
    name: string;
    email: string;
    roles: string[];
};

type Chosen = Pick<SwitchableUser, 'id' | 'name' | 'email'>;

const EVENT = 'pindah-akun:open';

/**
 * Open the Pindah Akun dialog (mounted once in the app layout) from anywhere —
 * the user menu, the Kelola Pengguna list — optionally with a user chosen.
 */
export function openAccountSwitcher(user?: Chosen): void {
    window.dispatchEvent(new CustomEvent(EVENT, { detail: user ?? null }));
}

export function onOpenAccountSwitcher(
    listener: (user: Chosen | null) => void,
): () => void {
    const handler = (event: Event) =>
        listener((event as CustomEvent<Chosen | null>).detail ?? null);
    window.addEventListener(EVENT, handler);

    return () => window.removeEventListener(EVENT, handler);
}
