import { Head } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { dashboard } from '@/routes';
import catalog from '@/routes/catalog';
import { ExternalLink } from 'lucide-react';
import {
    ResponsiveContainer,
    LineChart,
    Line,
    XAxis,
    YAxis,
    Tooltip,
    CartesianGrid,
} from 'recharts';

interface TagData {
    id: number;
    name: string;
    slug: string;
    color: string;
}

interface GameRef {
    id: number;
    title: string;
    slug: string;
    platform: string;
    platform_label: string;
    publisher: string | null;
    developer: string | null;
    release_date: string | null;
    source: string;
    source_url: string | null;
}

interface Listing {
    id: number;
    title: string;
    price_cents: number;
    condition_label: string;
    listing_url: string;
    image_url: string | null;
    marketplace: string;
    seller_name: string | null;
    last_seen_at: string;
    tags: TagData[];
}

interface PriceStats {
    min: number;
    max: number;
    avg: number;
    median: number;
    count: number;
}

interface PriceHistoryPoint {
    date: string;
    avg_price: number;
    min_price: number;
    max_price: number;
}

interface Props {
    game: GameRef;
    listings: Listing[];
    priceStats: PriceStats;
    priceHistory: PriceHistoryPoint[];
}

function formatPrice(cents: number): string {
    return (cents / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

export default function CatalogShow({ game, listings, priceStats, priceHistory }: Props) {
    const chartData = priceHistory.map((point) => ({
        date: point.date,
        avg: point.avg_price / 100,
        min: point.min_price / 100,
        max: point.max_price / 100,
    }));

    return (
        <>
            <Head title={game.title} />
            <div className="flex flex-col gap-6 p-4">
                <div>
                    <h1 className="text-2xl font-bold">{game.title}</h1>
                    <div className="mt-2 flex flex-wrap items-center gap-3">
                        <Badge variant="secondary">{game.platform_label}</Badge>
                        {game.release_date && (
                            <span className="text-sm text-muted-foreground">
                                Released: {game.release_date}
                            </span>
                        )}
                        {game.publisher && (
                            <span className="text-sm text-muted-foreground">
                                Publisher: {game.publisher}
                            </span>
                        )}
                        {game.developer && (
                            <span className="text-sm text-muted-foreground">
                                Genre: {game.developer}
                            </span>
                        )}
                        {game.source_url && (
                            <a
                                href={game.source_url}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="inline-flex items-center gap-1 text-sm text-primary hover:underline"
                            >
                                <ExternalLink className="size-3" />
                                {game.source}
                            </a>
                        )}
                    </div>
                </div>

                {priceStats.count > 0 && (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                        <Card>
                            <CardHeader className="pb-2">
                                <CardTitle className="text-sm font-medium text-muted-foreground">Min Price</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="text-xl font-bold text-green-600">{formatPrice(priceStats.min)}</div>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader className="pb-2">
                                <CardTitle className="text-sm font-medium text-muted-foreground">Max Price</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="text-xl font-bold text-red-600">{formatPrice(priceStats.max)}</div>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader className="pb-2">
                                <CardTitle className="text-sm font-medium text-muted-foreground">Avg Price</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="text-xl font-bold">{formatPrice(priceStats.avg)}</div>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader className="pb-2">
                                <CardTitle className="text-sm font-medium text-muted-foreground">Median</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="text-xl font-bold">{formatPrice(priceStats.median)}</div>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader className="pb-2">
                                <CardTitle className="text-sm font-medium text-muted-foreground">Listings</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="text-xl font-bold">{priceStats.count}</div>
                            </CardContent>
                        </Card>
                    </div>
                )}

                {chartData.length > 1 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Price History</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="h-72">
                                <ResponsiveContainer width="100%" height="100%">
                                    <LineChart data={chartData}>
                                        <CartesianGrid strokeDasharray="3 3" className="stroke-border" />
                                        <XAxis dataKey="date" className="text-xs" />
                                        <YAxis
                                            className="text-xs"
                                            tickFormatter={(v: number) => `R$ ${v.toFixed(0)}`}
                                        />
                                        <Tooltip
                                            formatter={(value) => [`R$ ${Number(value).toFixed(2)}`, '']}
                                            labelFormatter={(label) => `Date: ${label}`}
                                        />
                                        <Line type="monotone" dataKey="avg" name="Avg" stroke="hsl(var(--primary))" strokeWidth={2} dot={false} />
                                        <Line type="monotone" dataKey="min" name="Min" stroke="hsl(142, 76%, 36%)" strokeWidth={1} strokeDasharray="4 4" dot={false} />
                                        <Line type="monotone" dataKey="max" name="Max" stroke="hsl(0, 84%, 60%)" strokeWidth={1} strokeDasharray="4 4" dot={false} />
                                    </LineChart>
                                </ResponsiveContainer>
                            </div>
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Linked Listings ({listings.length})</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {listings.length === 0 ? (
                            <p className="py-4 text-center text-muted-foreground">
                                No listings linked to this game yet. Link listings from the Listings page.
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b text-left text-muted-foreground">
                                            <th className="pb-2 font-medium">Title</th>
                                            <th className="pb-2 font-medium">Price</th>
                                            <th className="pb-2 font-medium">Condition</th>
                                            <th className="pb-2 font-medium">Marketplace</th>
                                            <th className="pb-2 font-medium">Tags</th>
                                            <th className="pb-2 font-medium">Last Seen</th>
                                            <th className="pb-2 font-medium"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {listings.map((listing) => (
                                            <tr key={listing.id} className="border-b last:border-0">
                                                <td className="py-2 font-medium">{listing.title}</td>
                                                <td className="py-2 font-medium">{formatPrice(listing.price_cents)}</td>
                                                <td className="py-2">
                                                    <Badge variant="outline">{listing.condition_label}</Badge>
                                                </td>
                                                <td className="py-2">{listing.marketplace}</td>
                                                <td className="py-2">
                                                    <div className="flex flex-wrap gap-1">
                                                        {listing.tags.map((t) => (
                                                            <span
                                                                key={t.id}
                                                                className="rounded-full px-2 py-0.5 text-xs font-medium text-white"
                                                                style={{ backgroundColor: t.color }}
                                                            >
                                                                {t.name}
                                                            </span>
                                                        ))}
                                                    </div>
                                                </td>
                                                <td className="py-2 text-muted-foreground">{listing.last_seen_at}</td>
                                                <td className="py-2">
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
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

CatalogShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Game Catalog', href: catalog.index() },
        { title: 'Game Details', href: '#' },
    ],
};
