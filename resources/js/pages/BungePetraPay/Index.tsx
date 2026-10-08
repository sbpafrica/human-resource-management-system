import { Head, router } from "@inertiajs/react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Download } from "lucide-react";
import AppLayout from "@/layouts/app-layout";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/components/ui/table";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { useState, useEffect } from "react";
import { useForm } from "@inertiajs/react";
import ErrorBoundary from "@/components/ErrorBoundary";
import { CheckCircle, XCircle } from "lucide-react";

interface Record {
    staff_id: string;
    shop: string;
    lastname: string;
    firstname: string;
    othername: string;
    petra_id: string;
    ghana_card: string;
    basic_salary: number | null;
    petra_amount: number | null;
}

interface Props {
    records: {
        data: Record[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    selectedMonth: string;
    formattedMonth: string;
    totalRecords: number;
    searchCount: number | null;
    flash?: { success?: string; error?: string };
}

const breadcrumbs = [
    { title: "Bunge PETRA Pay", href: "/bungepetrapay" },
];

export default function BungePetraPay({
    records,
    selectedMonth,
    formattedMonth,
    totalRecords,
    searchCount,
    flash,
}: Props) {
    const [showToast, setShowToast] = useState(false);
    const [toastMessage, setToastMessage] = useState("");
    const [toastType, settoastType] = useState<"success" | "error">("success");
    const [search, setSearch] = useState("");
    const [sort, setSort] = useState("staff_id:asc");

    const { data, setData, errors } = useForm({
        month: selectedMonth,
    });

    useEffect(() => {
        if (flash?.success || flash?.error) {
            setToastMessage(flash.success || flash.error || "");
            settoastType(flash.success ? "success" : "error");
            setShowToast(true);
        }
    }, [flash]);

    useEffect(() => {
        if (Object.keys(errors).length > 0) {
            setToastMessage(Object.values(errors).join(" | "));
            settoastType("error");
            setShowToast(true);
        }
    }, [errors]);

    useEffect(() => {
        if (showToast) {
            const timer = setTimeout(() => setShowToast(false), toastType === "error" ? 60000 : 4000);
            return () => clearTimeout(timer);
        }
    }, [showToast, toastType]);

    const handleSearchKeyUp = () => {
        const [col, dir] = sort.split(":");
        router.get(
            route("bungepetrapay.index"),
            { month: data.month, search, sort: col, order: dir },
            { preserveState: true, preserveScroll: true }
        );
    };

    const handleMonthChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const month = e.target.value;
        setData("month", month);
        const [col, dir] = sort.split(":");
        router.get(
            route("bungepetrapay.index"),
            { month, search, sort: col, order: dir },
            { preserveState: true, preserveScroll: true }
        );
    };

    const handleSearchChange = (e: React.ChangeEvent<HTMLInputElement>) => setSearch(e.target.value);

    const handleSortChange = (value: string) => {
        setSort(value);
        const [col, dir] = value.split(":");
        router.get(
            route("bungepetrapay.index"),
            { month: data.month, search, sort: col, order: dir },
            { preserveState: true, preserveScroll: true }
        );
    };

    const handleExport = async () => {
        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute("content");
            if (!csrfToken) throw new Error("CSRF token missing");

            const [col, dir] = sort.split(":");

            const response = await fetch(
                route("bungepetrapay.export", {
                    month: data.month,
                    search: search || undefined,
                    sort: col,
                    order: dir,
                }),
                {
                    method: "GET",
                    headers: {
                        Accept: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
                        "X-CSRF-TOKEN": csrfToken,
                    },
                }
            );

            if (!response.ok) {
                const text = await response.text();
                let msg = "Export failed";
                try { const json = JSON.parse(text); msg = json.error || msg; } catch {}
                throw new Error(msg);
            }

            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement("a");
            a.href = url;
            a.download = `bunge_petra_${data.month}.xlsx`;
            a.click();
            window.URL.revokeObjectURL(url);

            setToastMessage("Excel exported successfully!");
            settoastType("success");
            setShowToast(true);
        } catch (err: any) {
            setToastMessage(err.message || "Export failed");
            settoastType("error");
            setShowToast(true);
        }
    };

    return (
        <ErrorBoundary>
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Bunge PETRA Pay" />

                <div className="flex flex-1 flex-col gap-6 p-4">

                    {showToast && (
                        <div className={`fixed top-4 right-4 z-50 flex items-center gap-3 rounded-lg px-5 py-3 text-white shadow-lg ${toastType === "success" ? "bg-green-600" : "bg-red-600"} animate-in slide-in-from-top`}>
                            {toastType === "success" ? <CheckCircle className="h-5 w-5" /> : <XCircle className="h-5 w-5" />}
                            <span className="font-medium">{toastMessage}</span>
                            {toastType === "error" && <button onClick={() => setShowToast(false)} className="ml-3"><XCircle className="h-5 w-5" /></button>}
                        </div>
                    )}

                    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <h1 className="text-2xl font-bold text-foreground">Bunge PETRA Pay</h1>
                        <div className="flex items-center gap-3">
                            <Label htmlFor="month-picker">Month:</Label>
                            <Input
                                id="month-picker"
                                type="month"
                                value={data.month}
                                onChange={handleMonthChange}
                                min="2020-01"
                                max={new Date().toISOString().slice(0, 7)}
                                className="w-48"
                            />
                        </div>
                    </div>

                    <div className="flex flex-col lg:flex-row gap-4 items-end justify-end">
                        <div className="flex flex-col sm:flex-row gap-4 w-full lg:w-auto">
                            <div className="flex items-center gap-3">
                                <Label htmlFor="search">Search:</Label>
                                <Input
                                    id="search"
                                    type="text"
                                    placeholder="Staff ID, Name, PETRA ID..."
                                    value={search}
                                    onChange={handleSearchChange}
                                    onKeyUp={handleSearchKeyUp}
                                    className="w-full sm:w-64"
                                />
                            </div>

                            <div className="flex items-center gap-3">
                                <Label htmlFor="sort">Sort By:</Label>
                                <Select value={sort} onValueChange={handleSortChange}>
                                    <SelectTrigger id="sort" className="w-48">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="staff_id:asc">Staff ID (A–Z)</SelectItem>
                                        <SelectItem value="staff_id:desc">Staff ID (Z–A)</SelectItem>
                                        <SelectItem value="lastname:asc">Last Name (A–Z)</SelectItem>
                                        <SelectItem value="lastname:desc">Last Name (Z–A)</SelectItem>
                                        <SelectItem value="shop:asc">Shop (A–Z)</SelectItem>
                                        <SelectItem value="shop:desc">Shop (Z–A)</SelectItem>
                                        <SelectItem value="petra_id:asc">PETRA ID (A–Z)</SelectItem>
                                        <SelectItem value="petra_id:desc">PETRA ID (Z–A)</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        <Button onClick={handleExport} className="bg-purple-600 hover:bg-purple-700">
                            <Download className="mr-2 h-4 w-4" />
                            Export to Excel
                        </Button>
                    </div>

                    <Card>
                        <CardHeader>
                            <CardTitle>Bunge PETRA Payment – {formattedMonth}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-sm text-muted-foreground mb-4">
                                Showing {records.data.length} of {searchCount ?? totalRecords} records
                                {searchCount !== null && ` (filtered from ${totalRecords})`}
                            </div>

                            {records.data.length === 0 ? (
                                <div className="text-center py-12 text-muted-foreground">
                                    No records found for the selected month.
                                </div>
                            ) : (
                                <>
                                    <div className="grid gap-4 md:hidden">
                                        {records.data.map((r) => (
                                            <div key={r.staff_id} className="border rounded-lg p-5 bg-card">
                                                <div className="font-semibold text-lg">
                                                    {r.lastname}, {r.firstname} {r.othername && `– ${r.othername}`}
                                                </div>
                                                <div className="mt-3 space-y-2 text-sm">
                                                    <div><span className="font-medium">Staff ID:</span> {r.staff_id}</div>
                                                    <div><span className="font-medium">Shop:</span> {r.shop}</div>
                                                    <div><span className="font-medium">PETRA ID:</span> {r.petra_id || "—"}</div>
                                                    <div><span className="font-medium">Ghana Card:</span> {r.ghana_card || "—"}</div>
                                                    <div><span className="font-medium">Basic Salary:</span> {r.basic_salary?.toLocaleString() || "N/A"}</div>
                                                    <div className="text-green-600 font-bold text-lg">
                                                        PETRA: GHS {r.petra_amount?.toLocaleString(undefined, { minimumFractionDigits: 2 }) || "N/A"}
                                                    </div>
                                                </div>
                                            </div>
                                        ))}
                                    </div>

                                    <div className="hidden md:block overflow-x-auto">
                                        <Table>
                                            <TableHeader>
                                                <TableRow>
                                                    <TableHead className="w-32">Staff ID</TableHead>
                                                    <TableHead>Shop</TableHead>
                                                    <TableHead>Last Name</TableHead>
                                                    <TableHead>First Name</TableHead>
                                                    <TableHead>Other Name</TableHead>
                                                    <TableHead>PETRA ID</TableHead>
                                                    <TableHead>Ghana Card</TableHead>
                                                    <TableHead className="text-right">Basic Salary</TableHead>
                                                    <TableHead className="text-right">PETRA Amount</TableHead>
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody>
                                                {records.data.map((r) => (
                                                    <TableRow key={r.staff_id}>
                                                        <TableCell className="font-medium">{r.staff_id}</TableCell>
                                                        <TableCell>{r.shop}</TableCell>
                                                        <TableCell>{r.lastname}</TableCell>
                                                        <TableCell>{r.firstname}</TableCell>
                                                        <TableCell>{r.othername || "—"}</TableCell>
                                                        <TableCell>{r.petra_id || "—"}</TableCell>
                                                        <TableCell>{r.ghana_card || "—"}</TableCell>
                                                        <TableCell className="text-right">
                                                            {r.basic_salary?.toLocaleString(undefined, { minimumFractionDigits: 2 }) || "N/A"}
                                                        </TableCell>
                                                        <TableCell className="text-right font-bold text-green-600">
                                                            {r.petra_amount?.toLocaleString(undefined, { minimumFractionDigits: 2 }) || "N/A"}
                                                        </TableCell>
                                                    </TableRow>
                                                ))}
                                            </TableBody>
                                        </Table>
                                    </div>
                                </>
                            )}

                            {records.links && records.links.length > 3 && (
                                <div className="flex justify-center gap-2 mt-8 flex-wrap">
                                    {records.links.map((link, i) => (
                                        <Button
                                            key={i}
                                            variant={link.active ? "default" : "outline"}
                                            size="sm"
                                            disabled={!link.url}
                                            asChild={!!link.url}
                                        >
                                            {link.url ? (
                                                <a href={link.url} dangerouslySetInnerHTML={{ __html: link.label }} />
                                            ) : (
                                                <span dangerouslySetInnerHTML={{ __html: link.label }} />
                                            )}
                                        </Button>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </AppLayout>
        </ErrorBoundary>
    );
}
