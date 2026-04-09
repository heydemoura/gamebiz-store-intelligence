import { Head, router } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { dashboard } from '@/routes';
import searchTerms from '@/routes/search-terms';
import { Trash2 } from 'lucide-react';
import { useState } from 'react';

interface SearchTerm {
    id: number;
    term: string;
    platform: string | null;
    is_category: boolean;
    is_active: boolean;
}

interface Props {
    searchTerms: SearchTerm[];
    platforms: Array<{ value: string; label: string }>;
}

export default function SearchTermsIndex({ searchTerms: items, platforms }: Props) {
    const [term, setTerm] = useState('');
    const [platform, setPlatform] = useState('');
    const [isCategory, setIsCategory] = useState(false);

    function handleAdd(e: React.FormEvent) {
        e.preventDefault();
        if (!term.trim()) return;

        router.post(
            searchTerms.store.url(),
            {
                term: term.trim(),
                platform: platform || null,
                is_category: isCategory,
            },
            {
                preserveState: true,
                onSuccess: () => {
                    setTerm('');
                    setPlatform('');
                    setIsCategory(false);
                },
            },
        );
    }

    function handleDelete(id: number) {
        router.delete(searchTerms.destroy.url(id), { preserveState: true });
    }

    return (
        <>
            <Head title="Search Terms" />
            <div className="flex flex-col gap-6 p-4">
                <Card>
                    <CardHeader>
                        <CardTitle>Add Search Term</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleAdd} className="flex flex-wrap items-end gap-3">
                            <div className="flex flex-col gap-1.5">
                                <Label htmlFor="term">Term</Label>
                                <Input
                                    id="term"
                                    placeholder="e.g. Mario Kart"
                                    value={term}
                                    onChange={(e) => setTerm(e.target.value)}
                                    className="w-64"
                                />
                            </div>
                            <div className="flex flex-col gap-1.5">
                                <Label>Platform (optional)</Label>
                                <Select
                                    value={platform}
                                    onValueChange={(value) => setPlatform(value === 'none' ? '' : value)}
                                >
                                    <SelectTrigger className="w-48">
                                        <SelectValue placeholder="Any Platform" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">Any Platform</SelectItem>
                                        {platforms.map((p) => (
                                            <SelectItem key={p.value} value={p.value}>
                                                {p.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="flex items-center gap-2 pb-0.5">
                                <Checkbox
                                    id="is_category"
                                    checked={isCategory}
                                    onCheckedChange={(checked) => setIsCategory(checked === true)}
                                />
                                <Label htmlFor="is_category">Category</Label>
                            </div>
                            <Button type="submit">Add</Button>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Search Terms ({items.length})</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b text-left text-muted-foreground">
                                        <th className="pb-2 font-medium">Term</th>
                                        <th className="pb-2 font-medium">Platform</th>
                                        <th className="pb-2 font-medium">Type</th>
                                        <th className="pb-2 font-medium">Status</th>
                                        <th className="pb-2 font-medium"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {items.map((item) => (
                                        <tr key={item.id} className="border-b last:border-0">
                                            <td className="py-2 font-medium">{item.term}</td>
                                            <td className="py-2">
                                                {item.platform ? (
                                                    <Badge variant="secondary">{item.platform}</Badge>
                                                ) : (
                                                    <span className="text-muted-foreground">All</span>
                                                )}
                                            </td>
                                            <td className="py-2">
                                                {item.is_category ? (
                                                    <Badge variant="outline">Category</Badge>
                                                ) : (
                                                    <span className="text-muted-foreground">Search</span>
                                                )}
                                            </td>
                                            <td className="py-2">
                                                <Badge variant={item.is_active ? 'default' : 'secondary'}>
                                                    {item.is_active ? 'Active' : 'Inactive'}
                                                </Badge>
                                            </td>
                                            <td className="py-2">
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => handleDelete(item.id)}
                                                    className="text-destructive hover:text-destructive"
                                                >
                                                    <Trash2 className="size-4" />
                                                </Button>
                                            </td>
                                        </tr>
                                    ))}
                                    {items.length === 0 && (
                                        <tr>
                                            <td colSpan={5} className="py-4 text-center text-muted-foreground">
                                                No search terms yet. Add one above.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

SearchTermsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Search Terms', href: searchTerms.index() },
    ],
};
