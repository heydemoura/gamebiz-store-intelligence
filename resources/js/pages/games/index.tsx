import { Head, Link, router } from '@inertiajs/react';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { dashboard } from '@/routes';
import games from '@/routes/games';
import { useState } from 'react';

interface Game {
    id: number;
    title: string;
    slug: string;
    platform: string;
    platform_label: string;
    year: number | null;
    cover_image_url: string | null;
    avg_price_cents: number | null;
    listings_count: number;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedGames {
    data: Game[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
}

interface Props {
    games: PaginatedGames;
    platforms: Array<{ value: string; label: string }>;
    filters: {
        search?: string;
        platform?: string;
        sort?: string;
        direction?: string;
    };
}

function formatPrice(cents: number): string {
    return (cents / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

export default function GamesIndex({ games: paginatedGames, platforms, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [platform, setPlatform] = useState(filters.platform ?? '');

    function applyFilters(newFilters: Record<string, string>) {
        const merged = { ...filters, ...newFilters };
        const cleaned = Object.fromEntries(
            Object.entries(merged).filter(([, v]) => v !== '' && v !== undefined),
        );
        router.get(games.index.url(), cleaned, { preserveState: true });
    }

    function handleSearchKeyDown(e: React.KeyboardEvent<HTMLInputElement>) {
        if (e.key === 'Enter') {
            applyFilters({ search });
        }
    }

    return (
        <>
            <Head title="Games" />
            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-center gap-3">
                    <Input
                        placeholder="Search games..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        onKeyDown={handleSearchKeyDown}
                        className="w-64"
                    />
                    <Select
                        value={platform}
                        onValueChange={(value) => {
                            setPlatform(value);
                            applyFilters({ platform: value === 'all' ? '' : value });
                        }}
                    >
                        <SelectTrigger className="w-48">
                            <SelectValue placeholder="All Platforms" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Platforms</SelectItem>
                            {platforms.map((p) => (
                                <SelectItem key={p.value} value={p.value}>
                                    {p.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    {paginatedGames.data.map((game) => (
                        <Link key={game.id} href={games.show.url(game.id)} className="group">
                            <Card className="transition-shadow group-hover:shadow-md">
                                {game.cover_image_url && (
                                    <div className="aspect-[3/4] overflow-hidden rounded-t-xl">
                                        <img
                                            src={game.cover_image_url}
                                            alt={game.title}
                                            className="size-full object-cover"
                                        />
                                    </div>
                                )}
                                <CardContent className="pt-4">
                                    <h3 className="font-semibold leading-tight group-hover:text-primary">
                                        {game.title}
                                    </h3>
                                    <div className="mt-2 flex items-center gap-2">
                                        <Badge variant="secondary">{game.platform_label}</Badge>
                                        {game.year && (
                                            <span className="text-xs text-muted-foreground">{game.year}</span>
                                        )}
                                    </div>
                                    <div className="mt-3 flex items-center justify-between text-sm">
                                        <span className="text-muted-foreground">
                                            {game.listings_count} listing{game.listings_count !== 1 ? 's' : ''}
                                        </span>
                                        {game.avg_price_cents !== null && (
                                            <span className="font-medium">
                                                {formatPrice(game.avg_price_cents)}
                                            </span>
                                        )}
                                    </div>
                                </CardContent>
                            </Card>
                        </Link>
                    ))}
                </div>

                {paginatedGames.data.length === 0 && (
                    <div className="py-12 text-center text-muted-foreground">No games found.</div>
                )}

                {paginatedGames.last_page > 1 && (
                    <div className="flex flex-wrap items-center justify-center gap-1">
                        {paginatedGames.links.map((link, index) => (
                            <Link
                                key={index}
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

GamesIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Games', href: games.index() },
    ],
};
