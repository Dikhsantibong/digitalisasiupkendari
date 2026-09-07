import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupContent,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { NavItem } from '@/types';

/**
 * A sidebar section that collapses, so the many role-specific groups don't all
 * stack open at once. The group holding the current page starts expanded.
 */
export function NavCollapsible({
    label,
    items = [],
}: {
    label: string;
    items: NavItem[];
}) {
    const { isCurrentUrl } = useCurrentUrl();
    const hasActive = items.some((item) => isCurrentUrl(item.href));

    return (
        <Collapsible
            defaultOpen={hasActive}
            className="group/collapsible px-2 py-0"
            asChild
        >
            <SidebarGroup className="px-0 py-0">
                <SidebarGroupLabel
                    asChild
                    className="cursor-pointer hover:bg-sidebar-accent hover:text-sidebar-accent-foreground"
                >
                    <CollapsibleTrigger>
                        {label}
                        <ChevronRight className="ml-auto size-4 transition-transform group-data-[state=open]/collapsible:rotate-90" />
                    </CollapsibleTrigger>
                </SidebarGroupLabel>
                <CollapsibleContent>
                    <SidebarGroupContent>
                        <SidebarMenu>
                            {items.map((item) => (
                                <SidebarMenuItem key={item.title}>
                                    <SidebarMenuButton
                                        asChild
                                        isActive={isCurrentUrl(item.href)}
                                        tooltip={{ children: item.title }}
                                    >
                                        <Link href={item.href} prefetch>
                                            {item.icon && <item.icon />}
                                            <span>{item.title}</span>
                                        </Link>
                                    </SidebarMenuButton>
                                </SidebarMenuItem>
                            ))}
                        </SidebarMenu>
                    </SidebarGroupContent>
                </CollapsibleContent>
            </SidebarGroup>
        </Collapsible>
    );
}
