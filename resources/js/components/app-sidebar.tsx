import { Link } from '@inertiajs/react';
import { BookOpen, FolderGit2, Gamepad2, Library, LayoutGrid, List, Search, Store } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import catalog from '@/routes/catalog';
import games from '@/routes/games';
import listings from '@/routes/listings';
import marketplaces from '@/routes/marketplaces';
import searchTerms from '@/routes/search-terms';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Game Catalog',
        href: catalog.index(),
        icon: Library,
    },
    {
        title: 'Games',
        href: games.index(),
        icon: Gamepad2,
    },
    {
        title: 'Listings',
        href: listings.index(),
        icon: List,
    },
    {
        title: 'Marketplaces',
        href: marketplaces.index(),
        icon: Store,
    },
    {
        title: 'Search Terms',
        href: searchTerms.index(),
        icon: Search,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/react-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
