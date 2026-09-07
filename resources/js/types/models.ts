/**
 * Shapes returned by the controllers. These are deliberately explicit rather
 * than mirrors of the database so that schema changes do not ripple into pages.
 */

export type Tone = 'neutral' | 'info' | 'success' | 'warning' | 'danger';

export type Option = {
    value: string;
    label: string;
    description?: string;
    tone?: Tone;
};

export type IdName = {
    id: number;
    name: string;
};

export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
};

export type ServiceUnitRow = {
    id: number;
    code: string;
    name: string;
    description: string | null;
    is_active: boolean;
    units_count: number;
};

export type UnitRow = {
    id: number;
    code: string;
    name: string;
    type: string;
    type_label: string;
    status: string;
    status_label: string;
    status_tone: Tone;
    installed_capacity_mw: string | null;
    location: string | null;
    is_active: boolean;
    service_unit_id: number | null;
    service_unit: string | null;
    machines_count?: number | null;
    machines_capacity?: string | null;
};

export type MachineRow = {
    id: number;
    name: string;
    type: string | null;
    fuel_type: string | null;
    serial_number: string | null;
    capacity_kw: string | null;
    is_active: boolean;
    unit_id: number;
    unit: string | null;
    lubricant_type_ids?: number[];
    lubricant_types?: string[];
};

/** A lubricant type option, tagged with its owning unit for client filtering. */
export type LubricantOption = {
    id: number;
    name: string;
    unit_id: number;
};

export type EmployeeRow = {
    id: number;
    name: string;
    nip: string | null;
    position: string | null;
    is_active: boolean;
    unit_id: number | null;
    unit: string | null;
};

export type RoleRow = {
    id: number;
    name: string;
    display_name: string;
    scope: string;
    scope_label: string;
    description: string | null;
    is_system: boolean;
    permissions_count: number;
    assignments_count: number;
};

export type AssignmentRow = {
    id: number;
    role_id: number;
    role: string;
    scope_label: string;
};

export type UserRow = {
    id: number;
    name: string;
    employee_id: string | null;
    email: string;
    position: string | null;
    phone: string | null;
    is_active: boolean;
    last_login_at: string | null;
    created_at: string | null;
    assignments: AssignmentRow[];
};

export type ActivityRow = {
    id: number;
    event: string;
    event_label: string;
    tone: Tone;
    description: string;
    user: { id: number; name: string; email: string } | null;
    unit: string | null;
    ip_address: string | null;
    properties: Record<string, unknown> | null;
    created_at: string | null;
};

export type PermissionGroupOption = {
    value: string;
    label: string;
    permissions: { name: string; label: string }[];
};
