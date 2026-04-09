import { Head, Link, router } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { dashboard } from '@/routes';
import catalog from '@/routes/catalog';
import { useState } from 'react';

interface GameReference {
    id: number;
    title: string;
    slug: string;
    platform: string;
    platform_label: string;
    publisher: string | null;
    developer: string | null;
    release_date: string | null;
    active_listings_count: number;
    average_price_cents: number | null;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Props {
    games: {
        data: GameReference[];
        links: PaginationLink[];
        current_page: number;
        last_page: number;
    };
    platforms: Array<{ value: string; label: string }>;
    filters: {
        platform?: string;
        search?: string;
        sort?: string;
        direction?: string;
    };
}

function formatPrice(cents: number | null): string {
    if (!cents) return '-';
    return (cents / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

export default function CatalogIndex({ games, platforms, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const activePlatform = filters.platform ?? 'ps4';

    function navigate(params: Record<string, string>) {
        const merged = { ...filters, ...params };
        const cleaned = Object.fromEntries(
            Object.entries(merged).filter(([, v]) => v !== '' && v !== undefined),
        );
        router.get(catalog.index.url(), cleaned, { preserveState: true });
    }

    function handleSearchKeyDown(e: React.KeyboardEvent<HTMLInputElement>) {
        if (e.key === 'Enter') {
            navigate({ search, platform: activePlatform });
        }
    }

    function toggleSort(field: string) {
        const currentSort = filters.sort ?? 'title';
        const currentDir = filters.direction ?? 'asc';
        const newDir = currentSort === field && currentDir === 'asc' ? 'desc' : 'asc';
        navigate({ sort: field, direction: newDir, platform: activePlatform });
    }

    function sortIndicator(field: string) {
        if ((filters.sort ?? 'title') !== field) return '';
        return (filters.direction ?? 'asc') === 'asc' ? ' \u2191' : ' \u2193';
    }

    return (
        <>
            <Head title="Game Catalog" />
            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-center gap-2">
                    {platforms.map((p) => (
                        <button
                            key={p.value}
                            onClick={() => navigate({ platform: p.value, search: '' })}
                            className={`rounded-md px-3 py-1.5 text-sm font-medium transition-colors ${
                                activePlatform === p.value
                                    ? 'bg-primary text-primary-foreground'
                                    : 'bg-muted text-muted-foreground hover:bg-accent'
                            }`}
                        >
                            {p.label}
                        </button>
                    ))}
                </div>

                <div className="flex items-center gap-3">
                    <Input
                        placeholder="Search games..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        onKeyDown={handleSearchKeyDown}
                        className="w-72"
                    />
                    <span className="text-sm text-muted-foreground">
                        {games.data.length > 0
                            ? `Page ${games.current_page} of ${games.last_page}`
                            : 'No games found'}
                    </span>
                </div>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b bg-muted/50 text-left text-muted-foreground">
                                <th
                                    className="cursor-pointer px-4 py-3 font-medium hover:text-foreground"
                                    onClick={() => toggleSort('title')}
                                >
                                    Title{sortIndicator('title')}
                                </th>
                                <th className="px-4 py-3 font-medium">Publisher</th>
                                <th
                                    className="cursor-pointer px-4 py-3 font-medium hover:text-foreground"
                                    onClick={() => toggleSort('release_date')}
                                >
                                    Release{sortIndicator('release_date')}
                                </th>
                                <th
                                    className="cursor-pointer px-4 py-3 font-medium hover:text-foreground"
                                    onClick={() => toggleSort('active_listings_count')}
                                >
                                    Listings{sortIndicator('active_listings_count')}
                                </th>
                                <th
                                    className="cursor-pointer px-4 py-3 font-medium hover:text-foreground"
                                    onClick={() => toggleSort('average_price_cents')}
                                >
                                    Avg Price{sortIndicator('average_price_cents')}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {games.data.map((game) => (
                                <tr key={game.id} className="border-b last:border-0 hover:bg-muted/30">
                                    <td className="px-4 py-3">
                                        <Link
                                            href={catalog.show.url(game.id)}
                                            className="font-medium text-primary hover:underline"
                                        >
                                            {game.title}
                                        </Link>
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {game.publisher ?? '-'}
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {game.release_date ? new Date(game.release_date).getFullYear() : '-'}
                                    </td>
                                    <td className="px-4 py-3">
                                        {game.active_listings_count > 0 ? (
                                            <Badge variant="secondary">{game.active_listings_count}</Badge>
                                        ) : (
                                            <span className="text-muted-foreground">-</span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 font-medium">
                                        {formatPrice(game.average_price_cents)}
                                    </td>
                                </tr>
                            ))}
                            {games.data.length === 0 && (
                                <tr>
                                    <td colSpan={5} className="px-4 py-8 text-center text-muted-foreground">
                                        No games found for this platform.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {games.last_page > 1 && (
                    <div className="flex flex-wrap items-center justify-center gap-1">
                        {games.links.map((link, i) => (
                            <Link
                                key={i}
                                href={link.url ?? '#'}
                                className={`rounded-md px-3 py-1.5 text-sm ${
                                    link.active
                                        ? 'bg-primary text-primary-foreground'
                                        : link.url
                                          ? 'hover:bg-accent'
                                          : 'pointer-events-none text-muted-foreground opacity-50'
                                }`}
                                preserveState
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

CatalogIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Game Catalog', href: catalog.index() },
    ],
};
