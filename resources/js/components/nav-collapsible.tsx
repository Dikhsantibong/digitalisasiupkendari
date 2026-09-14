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
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
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
    const hasActive = items.some((item) =>
        item.href
            ? isCurrentUrl(item.href)
            : item.items?.some((sub) => isCurrentUrl(sub.href)),
    );

    return (
        <Collapsible
            defaultOpen={hasActive}
            className="group/collapsible"
            asChild
        >
            <SidebarGroup className="px-2 py-0">
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
                            {items.map((item) => {
                                if (item.items && item.items.length > 0) {
                                    const isSubActive = item.items.some((sub) =>
                                        isCurrentUrl(sub.href),
                                    );
                                    return (
                                        <Collapsible
                                            key={item.title}
                                            asChild
                                            defaultOpen={isSubActive}
                                            className="group/sub-collapsible"
                                        >
                                            <SidebarMenuItem>
                                                <CollapsibleTrigger asChild>
                                                    <SidebarMenuButton
                                                        tooltip={{ children: item.title }}
                                                        className="cursor-pointer"
                                                        isActive={isSubActive}
                                                    >
                                                        {item.icon && <item.icon />}
                                                        <span>{item.title}</span>
                                                        <ChevronRight className="ml-auto size-4 transition-transform duration-200 group-data-[state=open]/sub-collapsible:rotate-90" />
                                                    </SidebarMenuButton>
                                                </CollapsibleTrigger>
                                                <CollapsibleContent>
                                                    <SidebarMenuSub>
                                                        {item.items.map((subItem) => (
                                                            <SidebarMenuSubItem key={subItem.title}>
                                                                <SidebarMenuSubButton
                                                                    asChild
                                                                    isActive={isCurrentUrl(subItem.href)}
                                                                >
                                                                    <Link href={subItem.href} prefetch>
                                                                        {subItem.icon && (
                                                                            <subItem.icon className="size-3.5" />
                                                                        )}
                                                                        <span>{subItem.title}</span>
                                                                    </Link>
                                                                </SidebarMenuSubButton>
                                                            </SidebarMenuSubItem>
                                                        ))}
                                                    </SidebarMenuSub>
                                                </CollapsibleContent>
                                            </SidebarMenuItem>
                                        </Collapsible>
                                    );
                                }

                                return (
                                    <SidebarMenuItem key={item.title}>
                                        <SidebarMenuButton
                                            asChild
                                            isActive={item.href ? isCurrentUrl(item.href) : false}
                                            tooltip={{ children: item.title }}
                                        >
                                            <Link href={item.href ?? '#'} prefetch>
                                                {item.icon && <item.icon />}
                                                <span>{item.title}</span>
                                            </Link>
                                        </SidebarMenuButton>
                                    </SidebarMenuItem>
                                );
                            })}
                        </SidebarMenu>
                    </SidebarGroupContent>
                </CollapsibleContent>
            </SidebarGroup>
        </Collapsible>
    );
}
