import { Head, Link } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { dashboard } from '@/routes';
import games from '@/routes/games';
import listings from '@/routes/listings';
import { Gamepad2, List, DollarSign, Store } from 'lucide-react';

interface Listing {
    id: number;
    title: string;
    price_cents: number;
    condition: string;
    condition_label: string;
    listing_url: string;
    marketplace: string;
    game_title: string;
    last_seen_at: string;
}

interface Props {
    stats: {
        totalGames: number;
        totalListings: number;
        averagePriceCents: number;
        totalMarketplaces: number;
    };
    recentListings: Listing[];
    bestDeals: Listing[];
}

function formatPrice(cents: number): string {
    return (cents / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

export default function Dashboard({ stats, recentListings, bestDeals }: Props) {
    return (
        <>
            <Head title="Dashboard" />
            <div className="flex flex-col gap-6 p-4">
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">Total Games</CardTitle>
                            <Gamepad2 className="size-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stats.totalGames.toLocaleString('pt-BR')}</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">Total Listings</CardTitle>
                            <List className="size-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stats.totalListings.toLocaleString('pt-BR')}</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">Avg Price</CardTitle>
                            <DollarSign className="size-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{formatPrice(stats.averagePriceCents)}</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">Marketplaces</CardTitle>
                            <Store className="size-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stats.totalMarketplaces}</div>
                        </CardContent>
                    </Card>
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Recent Listings</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b text-left text-muted-foreground">
                                            <th className="pb-2 font-medium">Title</th>
                                            <th className="pb-2 font-medium">Game</th>
                                            <th className="pb-2 font-medium">Price</th>
                                            <th className="pb-2 font-medium">Marketplace</th>
                                            <th className="pb-2 font-medium">Last Seen</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {recentListings.map((listing) => (
                                            <tr key={listing.id} className="border-b last:border-0">
                                                <td className="py-2">
                                                    <a
                                                        href={listing.listing_url}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        className="text-primary hover:underline"
                                                    >
                                                        {listing.title}
                                                    </a>
                                                </td>
                                                <td className="py-2">{listing.game_title}</td>
                                                <td className="py-2 font-medium">{formatPrice(listing.price_cents)}</td>
                                                <td className="py-2">{listing.marketplace}</td>
                                                <td className="py-2 text-muted-foreground">{listing.last_seen_at}</td>
                                            </tr>
                                        ))}
                                        {recentListings.length === 0 && (
                                            <tr>
                                                <td colSpan={5} className="py-4 text-center text-muted-foreground">No listings yet.</td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Best Deals</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b text-left text-muted-foreground">
                                            <th className="pb-2 font-medium">Title</th>
                                            <th className="pb-2 font-medium">Game</th>
                                            <th className="pb-2 font-medium">Price</th>
                                            <th className="pb-2 font-medium">Condition</th>
                                            <th className="pb-2 font-medium">Marketplace</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {bestDeals.map((listing) => (
                                            <tr key={listing.id} className="border-b last:border-0">
                                                <td className="py-2">
                                                    <a
                                                        href={listing.listing_url}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        className="text-primary hover:underline"
                                                    >
                                                        {listing.title}
                                                    </a>
                                                </td>
                                                <td className="py-2">{listing.game_title}</td>
                                                <td className="py-2 font-medium text-green-600">{formatPrice(listing.price_cents)}</td>
                                                <td className="py-2">
                                                    <Badge variant="secondary">{listing.condition_label}</Badge>
                                                </td>
                                                <td className="py-2">{listing.marketplace}</td>
                                            </tr>
                                        ))}
                                        {bestDeals.length === 0 && (
                                            <tr>
                                                <td colSpan={5} className="py-4 text-center text-muted-foreground">No deals found yet.</td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
