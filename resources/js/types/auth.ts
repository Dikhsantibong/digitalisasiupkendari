export type User = {
    id: number;
    name: string;
    employee_id?: string | null;
    email: string;
    position?: string | null;
    phone?: string | null;
    is_active?: boolean;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type AuthRole = {
    name: string;
    display_name: string;
    scope: 'global' | 'service_unit' | 'unit';
};

export type Auth = {
    user: User;
    /** Permission names granted by the user's roles. The server remains the authority. */
    permissions: string[];
    roles: AuthRole[];
    isSuperAdmin: boolean;
    hasGlobalAccess: boolean;
};

export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
