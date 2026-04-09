import { Head, Link, router } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Toggle } from '@/components/ui/toggle';
import { dashboard } from '@/routes';
import listingsRoute from '@/routes/listings';
import { Download, ExternalLink, MonitorPlay, Pencil, Tag } from 'lucide-react';
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
    game_id: number | null;
    game_platform: string;
    price_cents: number;
    condition: string;
    condition_label: string;
    marketplace: string;
    listing_url: string;
    seller_name: string | null;
    is_available: boolean;
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
    games?: Array<{ id: number; label: string }>;
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

interface EditForm {
    title: string;
    price_cents: number;
    condition: string;
    listing_url: string;
    game_id: string;
    seller_name: string;
    is_available: boolean;
}

export default function ListingsIndex({
    listings: paginatedListings,
    marketplaces,
    platforms,
    conditions,
    tags,
    games,
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
    const [previewInline, setPreviewInline] = useState(false);
    const [previewListing, setPreviewListing] = useState<Listing | null>(null);
    const [editListing, setEditListing] = useState<Listing | null>(null);
    const [editForm, setEditForm] = useState<EditForm>({
        title: '',
        price_cents: 0,
        condition: 'unknown',
        listing_url: '',
        game_id: '',
        seller_name: '',
        is_available: true,
    });
    const [editSaving, setEditSaving] = useState(false);

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
        // Optimistically update the preview listing if it's the one being tagged
        if (previewListing && previewListing.id === listingId) {
            const tag = tags.find((t) => t.id === tagId);
            if (tag) {
                const hasCurrent = previewListing.tags.some((t) => t.id === tagId);
                setPreviewListing({
                    ...previewListing,
                    tags: hasCurrent
                        ? previewListing.tags.filter((t) => t.id !== tagId)
                        : [...previewListing.tags, tag],
                });
            }
        }

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

    function openListing(listing: Listing) {
        if (previewInline) {
            setPreviewListing(listing);
        } else {
            window.open(listing.listing_url, '_blank', 'noopener,noreferrer');
        }
    }

    function openEdit(listing: Listing) {
        setEditListing(listing);
        setEditForm({
            title: listing.title,
            price_cents: listing.price_cents,
            condition: listing.condition,
            listing_url: listing.listing_url,
            game_id: listing.game_id ? String(listing.game_id) : '',
            seller_name: listing.seller_name ?? '',
            is_available: listing.is_available,
        });
        // Load games list if not already loaded (optional prop)
        if (!games) {
            router.reload({ only: ['games'] });
        }
    }

    function saveEdit() {
        if (!editListing) return;
        setEditSaving(true);
        router.patch(
            listingsRoute.update.url(editListing.id),
            {
                title: editForm.title,
                price_cents: editForm.price_cents,
                condition: editForm.condition,
                listing_url: editForm.listing_url,
                game_id: editForm.game_id ? Number(editForm.game_id) : null,
                seller_name: editForm.seller_name || null,
                is_available: editForm.is_available,
            },
            {
                preserveState: true,
                preserveScroll: true,
                onSuccess: () => {
                    setEditListing(null);
                    setEditSaving(false);
                },
                onError: () => setEditSaving(false),
            },
        );
    }

    // Use the local previewListing state directly — it's optimistically updated on tag toggle
    const activePreview = previewListing;

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
                    <Toggle
                        variant="outline"
                        size="sm"
                        pressed={previewInline}
                        onPressedChange={setPreviewInline}
                        title={previewInline ? 'Preview: inline (click to switch to new tab)' : 'Preview: new tab (click to switch to inline)'}
                    >
                        <MonitorPlay className="size-4" />
                        Inline Preview
                    </Toggle>
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
                                        <div className="flex items-center gap-2">
                                            <button
                                                onClick={() => openEdit(listing)}
                                                className="text-muted-foreground hover:text-primary"
                                                title="Edit listing"
                                            >
                                                <Pencil className="size-3.5" />
                                            </button>
                                            <button
                                                onClick={() => openListing(listing)}
                                                className="text-primary hover:underline"
                                                title={previewInline ? 'Open inline preview' : 'Open in new tab'}
                                            >
                                                {previewInline ? (
                                                    <MonitorPlay className="size-3.5" />
                                                ) : (
                                                    <ExternalLink className="size-3.5" />
                                                )}
                                            </button>
                                        </div>
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

            <Dialog open={activePreview !== null} onOpenChange={(open) => { if (!open) setPreviewListing(null); }}>
                <DialogContent className="flex h-[90vh] w-[90vw] max-w-[90vw] sm:max-w-[90vw] flex-col gap-0 overflow-hidden p-0">
                    <DialogHeader className="shrink-0 border-b px-6 py-4">
                        <DialogTitle className="flex items-center gap-2 pr-8">
                            <span className="truncate">{activePreview?.title}</span>
                            {activePreview && (
                                <button
                                    onClick={() => openEdit(activePreview)}
                                    className="shrink-0 text-muted-foreground hover:text-primary"
                                    title="Edit listing"
                                >
                                    <Pencil className="size-4" />
                                </button>
                            )}
                            <a
                                href={activePreview?.listing_url ?? '#'}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="shrink-0 text-muted-foreground hover:text-primary"
                                title="Open in new tab"
                            >
                                <ExternalLink className="size-4" />
                            </a>
                        </DialogTitle>
                    </DialogHeader>

                    {activePreview && (
                        <div className="shrink-0 border-b px-6 py-4">
                            <div className="flex flex-wrap items-start gap-x-6 gap-y-3">
                                <div className="flex items-center gap-2 text-sm">
                                    <span className="text-muted-foreground">Price:</span>
                                    <span className="font-semibold">{formatPrice(activePreview.price_cents)}</span>
                                </div>
                                {activePreview.game_title && (
                                    <div className="flex items-center gap-2 text-sm">
                                        <span className="text-muted-foreground">Game:</span>
                                        <span>{activePreview.game_title}</span>
                                    </div>
                                )}
                                {activePreview.game_platform && (
                                    <div className="flex items-center gap-2 text-sm">
                                        <span className="text-muted-foreground">Platform:</span>
                                        <Badge variant="secondary">{activePreview.game_platform}</Badge>
                                    </div>
                                )}
                                <div className="flex items-center gap-2 text-sm">
                                    <span className="text-muted-foreground">Condition:</span>
                                    <Badge variant="outline">{activePreview.condition_label}</Badge>
                                </div>
                                <div className="flex items-center gap-2 text-sm">
                                    <span className="text-muted-foreground">Marketplace:</span>
                                    <span>{activePreview.marketplace}</span>
                                </div>
                                <div className="flex items-center gap-2 text-sm">
                                    <span className="text-muted-foreground">Last Seen:</span>
                                    <span className="text-muted-foreground">{activePreview.last_seen_at}</span>
                                </div>
                            </div>

                            <div className="mt-3 flex flex-wrap items-center gap-1.5">
                                <span className="mr-1 text-sm text-muted-foreground">Tags:</span>
                                {activePreview.tags.map((t) => (
                                    <button
                                        key={t.id}
                                        onClick={() => toggleTag(activePreview.id, t.id)}
                                        className="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium text-white transition-opacity hover:opacity-80"
                                        style={{ backgroundColor: t.color }}
                                        title={`Remove "${t.name}"`}
                                    >
                                        {t.name}
                                        <span className="ml-1">&times;</span>
                                    </button>
                                ))}
                                {tags
                                    .filter((t) => !hasTag(activePreview, t.id))
                                    .map((t) => (
                                        <button
                                            key={t.id}
                                            onClick={() => toggleTag(activePreview.id, t.id)}
                                            className="inline-flex items-center gap-1 rounded-full border border-dashed px-2.5 py-0.5 text-xs text-muted-foreground transition-colors hover:border-current"
                                            style={{ borderColor: t.color, color: t.color }}
                                            title={`Add "${t.name}"`}
                                        >
                                            <span
                                                className="inline-block size-2 rounded-full"
                                                style={{ backgroundColor: t.color }}
                                            />
                                            {t.name}
                                        </button>
                                    ))}
                            </div>
                        </div>
                    )}

                    <div className="min-h-0 flex-1 p-6">
                        {activePreview && (
                            <iframe
                                src={activePreview.listing_url}
                                className="size-full rounded-md border"
                                sandbox="allow-scripts allow-same-origin allow-popups allow-forms"
                                referrerPolicy="no-referrer"
                            />
                        )}
                    </div>
                </DialogContent>
            </Dialog>

            <Dialog open={editListing !== null} onOpenChange={(open) => { if (!open) setEditListing(null); }}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Edit Listing</DialogTitle>
                    </DialogHeader>
                    <div className="grid gap-4 py-2">
                        <div className="grid gap-2">
                            <Label htmlFor="edit-title">Title</Label>
                            <Input
                                id="edit-title"
                                value={editForm.title}
                                onChange={(e) => setEditForm({ ...editForm, title: e.target.value })}
                            />
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="edit-price">Price (BRL)</Label>
                                <Input
                                    id="edit-price"
                                    type="number"
                                    step="0.01"
                                    value={(editForm.price_cents / 100).toFixed(2)}
                                    onChange={(e) =>
                                        setEditForm({
                                            ...editForm,
                                            price_cents: Math.round(parseFloat(e.target.value || '0') * 100),
                                        })
                                    }
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label>Condition</Label>
                                <Select
                                    value={editForm.condition}
                                    onValueChange={(value) => setEditForm({ ...editForm, condition: value })}
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {conditions.map((c) => (
                                            <SelectItem key={c.value} value={c.value}>
                                                {c.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                        <div className="grid gap-2">
                            <Label>Game</Label>
                            <Select
                                value={editForm.game_id || 'none'}
                                onValueChange={(value) => setEditForm({ ...editForm, game_id: value === 'none' ? '' : value })}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Select a game..." />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">No game assigned</SelectItem>
                                    {(games ?? []).map((g) => (
                                        <SelectItem key={g.id} value={String(g.id)}>
                                            {g.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="edit-url">Listing URL</Label>
                            <Input
                                id="edit-url"
                                value={editForm.listing_url}
                                onChange={(e) => setEditForm({ ...editForm, listing_url: e.target.value })}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="edit-seller">Seller Name</Label>
                            <Input
                                id="edit-seller"
                                value={editForm.seller_name}
                                onChange={(e) => setEditForm({ ...editForm, seller_name: e.target.value })}
                            />
                        </div>
                        <div className="flex items-center gap-2">
                            <input
                                id="edit-available"
                                type="checkbox"
                                checked={editForm.is_available}
                                onChange={(e) => setEditForm({ ...editForm, is_available: e.target.checked })}
                                className="size-4 rounded border-input"
                            />
                            <Label htmlFor="edit-available">Available</Label>
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setEditListing(null)}>
                            Cancel
                        </Button>
                        <Button onClick={saveEdit} disabled={editSaving}>
                            {editSaving ? 'Saving...' : 'Save Changes'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

ListingsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Listings', href: listingsRoute.index() },
    ],
};
