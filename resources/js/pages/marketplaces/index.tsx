import { Head, router } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import marketplaces from '@/routes/marketplaces';
import { Play, ToggleLeft, ToggleRight } from 'lucide-react';

interface Marketplace {
    id: number;
    name: string;
    slug: string;
    base_url: string;
    is_active: boolean;
    scrape_interval_minutes: number;
    last_scraped_at: string | null;
    listings_count: number;
    active_listings_count: number;
}

interface Props {
    marketplaces: Marketplace[];
}

export default function MarketplacesIndex({ marketplaces: items }: Props) {
    function handleToggle(id: number) {
        router.patch(marketplaces.toggle.url(id), {}, { preserveState: true });
    }

    function handleScrape(id: number) {
        router.post(marketplaces.scrape.url(id), {}, { preserveState: true });
    }

    return (
        <>
            <Head title="Marketplaces" />
            <div className="flex flex-col gap-6 p-4">
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {items.map((mp) => (
                        <Card key={mp.id}>
                            <CardHeader className="flex flex-row items-center justify-between">
                                <div>
                                    <CardTitle>{mp.name}</CardTitle>
                                    <a
                                        href={mp.base_url}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="text-xs text-muted-foreground hover:underline"
                                    >
                                        {mp.base_url}
                                    </a>
                                </div>
                                <Badge variant={mp.is_active ? 'default' : 'secondary'}>
                                    {mp.is_active ? 'Active' : 'Inactive'}
                                </Badge>
                            </CardHeader>
                            <CardContent className="flex flex-col gap-4">
                                <div className="grid grid-cols-2 gap-2 text-sm">
                                    <div>
                                        <span className="text-muted-foreground">Total Listings</span>
                                        <div className="font-medium">{mp.listings_count.toLocaleString('pt-BR')}</div>
                                    </div>
                                    <div>
                                        <span className="text-muted-foreground">Active Listings</span>
                                        <div className="font-medium">{mp.active_listings_count.toLocaleString('pt-BR')}</div>
                                    </div>
                                    <div>
                                        <span className="text-muted-foreground">Interval</span>
                                        <div className="font-medium">{mp.scrape_interval_minutes} min</div>
                                    </div>
                                    <div>
                                        <span className="text-muted-foreground">Last Scraped</span>
                                        <div className="font-medium">{mp.last_scraped_at ?? 'Never'}</div>
                                    </div>
                                </div>
                                <div className="flex gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => handleToggle(mp.id)}
                                    >
                                        {mp.is_active ? (
                                            <ToggleRight className="size-4" />
                                        ) : (
                                            <ToggleLeft className="size-4" />
                                        )}
                                        {mp.is_active ? 'Disable' : 'Enable'}
                                    </Button>
                                    <Button
                                        variant="default"
                                        size="sm"
                                        onClick={() => handleScrape(mp.id)}
                                        disabled={!mp.is_active}
                                    >
                                        <Play className="size-4" />
                                        Run Now
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                {items.length === 0 && (
                    <div className="py-12 text-center text-muted-foreground">No marketplaces configured.</div>
                )}
            </div>
        </>
    );
}

MarketplacesIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Marketplaces', href: marketplaces.index() },
    ],
};
