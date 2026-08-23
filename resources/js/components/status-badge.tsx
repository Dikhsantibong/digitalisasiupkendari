import { Badge } from '@/components/ui/badge';
import type { Tone } from '@/types';

const variantByTone = {
    neutral: 'status-draft',
    info: 'status-in-progress',
    success: 'status-completed',
    warning: 'status-pending',
    danger: 'status-rejected',
} as const;

/**
 * Renders a status with a soft background and dark text, per the design system.
 * The label carries the meaning so the state never depends on colour alone.
 */
export function StatusBadge({
    tone = 'neutral',
    children,
}: {
    tone?: Tone;
    children: React.ReactNode;
}) {
    return <Badge variant={variantByTone[tone]}>{children}</Badge>;
}
