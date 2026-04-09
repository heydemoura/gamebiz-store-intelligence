import { Head, Link, router } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { dashboard } from '@/routes';
import listingsRoute from '@/routes/listings';
import { Download, ExternalLink, Tag } from 'lucide-react';
import { useState } from 'react';

interface TagData {
    id: number;
    name: string;
    slug: string;
    color: string;
}

interface Listing {
    id: number;
    title: string;
    game_title: string;
    game_platform: string;
    price_cents: number;
    condition_label: string;
    marketplace: string;
    listing_url: string;
    last_seen_at: string;
    tags: TagData[];
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedListings {
    data: Listing[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
}

interface Props {
    listings: PaginatedListings;
    marketplaces: Array<{ id: number; name: string }>;
    platforms: Array<{ value: string; label: string }>;
    conditions: Array<{ value: string; label: string }>;
    tags: TagData[];
    filters: {
        search?: string;
        marketplace?: string;
        condition?: string;
        platform?: string;
        min_price?: string;
        max_price?: string;
        tag?: string;
    };
}

function formatPrice(cents: number): string {
    return (cents / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

export default function ListingsIndex({
    listings: paginatedListings,
    marketplaces,
    platforms,
    conditions,
    tags,
    filters,
}: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [marketplace, setMarketplace] = useState(filters.marketplace ?? '');
    const [condition, setCondition] = useState(filters.condition ?? '');
    const [platform, setPlatform] = useState(filters.platform ?? '');
    const [priceMin, setPriceMin] = useState(filters.min_price ?? '');
    const [priceMax, setPriceMax] = useState(filters.max_price ?? '');
    const [tagFilter, setTagFilter] = useState(filters.tag ?? '');
    const [openTagMenuId, setOpenTagMenuId] = useState<number | null>(null);

    function applyFilters(newFilters: Record<string, string>) {
        const merged = { ...filters, ...newFilters };
        const cleaned = Object.fromEntries(
            Object.entries(merged).filter(([, v]) => v !== '' && v !== undefined),
        );
        router.get(listingsRoute.index.url(), cleaned, { preserveState: true });
    }

    function handleSearchKeyDown(e: React.KeyboardEvent<HTMLInputElement>) {
        if (e.key === 'Enter') {
            applyFilters({ search });
        }
    }

    function exportUrl(): string {
        const params = Object.fromEntries(
            Object.entries(filters).filter(([, v]) => v !== '' && v !== undefined),
        );
        return listingsRoute.export.url({ query: params });
    }

    function toggleTag(listingId: number, tagId: number) {
        router.post(
            listingsRoute.toggleTag.url({ listing: listingId, tag: tagId }),
            {},
            { preserveState: true, preserveScroll: true },
        );
        setOpenTagMenuId(null);
    }

    function hasTag(listing: Listing, tagId: number): boolean {
        return listing.tags.some((t) => t.id === tagId);
    }

    return (
        <>
            <Head title="Listings" />
            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-center gap-3">
                    <Input
                        placeholder="Search listings..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        onKeyDown={handleSearchKeyDown}
                        className="w-64"
                    />
                    <Select
                        value={marketplace}
                        onValueChange={(value) => {
                            setMarketplace(value);
                            applyFilters({ marketplace: value === 'all' ? '' : value });
                        }}
                    >
                        <SelectTrigger className="w-48">
                            <SelectValue placeholder="All Marketplaces" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Marketplaces</SelectItem>
                            {marketplaces.map((m) => (
                                <SelectItem key={m.id} value={String(m.id)}>
                                    {m.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Select
                        value={condition}
                        onValueChange={(value) => {
                            setCondition(value);
                            applyFilters({ condition: value === 'all' ? '' : value });
                        }}
                    >
                        <SelectTrigger className="w-40">
                            <SelectValue placeholder="All Conditions" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Conditions</SelectItem>
                            {conditions.map((c) => (
                                <SelectItem key={c.value} value={c.value}>
                                    {c.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Select
                        value={platform}
                        onValueChange={(value) => {
                            setPlatform(value);
                            applyFilters({ platform: value === 'all' ? '' : value });
                        }}
                    >
                        <SelectTrigger className="w-40">
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
                    <Select
                        value={tagFilter}
                        onValueChange={(value) => {
                            setTagFilter(value);
                            applyFilters({ tag: value === 'all' ? '' : value });
                        }}
                    >
                        <SelectTrigger className="w-48">
                            <SelectValue placeholder="All Tags" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Tags</SelectItem>
                            <SelectItem value="untagged">Untagged</SelectItem>
                            {tags.map((t) => (
                                <SelectItem key={t.slug} value={t.slug}>
                                    <span className="flex items-center gap-2">
                                        <span
                                            className="inline-block size-2.5 rounded-full"
                                            style={{ backgroundColor: t.color }}
                                        />
                                        {t.name}
                                    </span>
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Input
                        type="number"
                        placeholder="Min price"
                        value={priceMin}
                        onChange={(e) => setPriceMin(e.target.value)}
                        onBlur={() => applyFilters({ min_price: priceMin })}
                        className="w-28"
                    />
                    <Input
                        type="number"
                        placeholder="Max price"
                        value={priceMax}
                        onChange={(e) => setPriceMax(e.target.value)}
                        onBlur={() => applyFilters({ max_price: priceMax })}
                        className="w-28"
                    />
                    <a href={exportUrl()}>
                        <Button variant="outline" size="sm">
                            <Download className="size-4" />
                            Export
                        </Button>
                    </a>
                </div>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b bg-muted/50 text-left text-muted-foreground">
                                <th className="px-4 py-3 font-medium">Title</th>
                                <th className="px-4 py-3 font-medium">Game</th>
                                <th className="px-4 py-3 font-medium">Platform</th>
                                <th className="px-4 py-3 font-medium">Price</th>
                                <th className="px-4 py-3 font-medium">Condition</th>
                                <th className="px-4 py-3 font-medium">Marketplace</th>
                                <th className="px-4 py-3 font-medium">Tags</th>
                                <th className="px-4 py-3 font-medium">Last Seen</th>
                                <th className="px-4 py-3 font-medium"></th>
                            </tr>
                        </thead>
                        <tbody>
                            {paginatedListings.data.map((listing) => (
                                <tr key={listing.id} className="border-b last:border-0">
                                    <td className="px-4 py-3 font-medium">{listing.title}</td>
                                    <td className="px-4 py-3">{listing.game_title}</td>
                                    <td className="px-4 py-3">
                                        {listing.game_platform && (
                                            <Badge variant="secondary">{listing.game_platform}</Badge>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 font-medium">{formatPrice(listing.price_cents)}</td>
                                    <td className="px-4 py-3">
                                        <Badge variant="outline">{listing.condition_label}</Badge>
                                    </td>
                                    <td className="px-4 py-3">{listing.marketplace}</td>
                                    <td className="px-4 py-3">
                                        <div className="relative flex flex-wrap items-center gap-1">
                                            {listing.tags.map((t) => (
                                                <button
                                                    key={t.id}
                                                    onClick={() => toggleTag(listing.id, t.id)}
                                                    className="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium text-white transition-opacity hover:opacity-80"
                                                    style={{ backgroundColor: t.color }}
                                                    title={`Remove "${t.name}"`}
                                                >
                                                    {t.name}
                                                    <span className="ml-1">&times;</span>
                                                </button>
                                            ))}
                                            <button
                                                onClick={() =>
                                                    setOpenTagMenuId(openTagMenuId === listing.id ? null : listing.id)
                                                }
                                                className="inline-flex items-center rounded-full border border-dashed px-1.5 py-0.5 text-xs text-muted-foreground transition-colors hover:border-primary hover:text-primary"
                                                title="Add tag"
                                            >
                                                <Tag className="size-3" />
                                            </button>
                                            {openTagMenuId === listing.id && (
                                                <div className="absolute top-full left-0 z-10 mt-1 rounded-md border bg-popover p-1 shadow-md">
                                                    {tags.map((t) => (
                                                        <button
                                                            key={t.id}
                                                            onClick={() => toggleTag(listing.id, t.id)}
                                                            className="flex w-full items-center gap-2 rounded-sm px-2 py-1.5 text-xs hover:bg-accent"
                                                        >
                                                            <span
                                                                className="inline-block size-2.5 rounded-full"
                                                                style={{ backgroundColor: t.color }}
                                                            />
                                                            {t.name}
                                                            {hasTag(listing, t.id) && (
                                                                <span className="ml-auto text-primary">&#10003;</span>
                                                            )}
                                                        </button>
                                                    ))}
                                                </div>
                                            )}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">{listing.last_seen_at}</td>
                                    <td className="px-4 py-3">
                                        <a
                                            href={listing.listing_url}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="inline-flex items-center gap-1 text-primary hover:underline"
                                        >
                                            <ExternalLink className="size-3.5" />
                                        </a>
                                    </td>
                                </tr>
                            ))}
                            {paginatedListings.data.length === 0 && (
                                <tr>
                                    <td colSpan={9} className="px-4 py-8 text-center text-muted-foreground">
                                        No listings found.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {paginatedListings.last_page > 1 && (
                    <div className="flex flex-wrap items-center justify-center gap-1">
                        {paginatedListings.links.map((link, index) => (
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

ListingsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Listings', href: listingsRoute.index() },
    ],
};
