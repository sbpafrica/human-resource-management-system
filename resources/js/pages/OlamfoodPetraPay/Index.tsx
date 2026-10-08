import { Head, router } from "@inertiajs/react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { FileSpreadsheet, ArrowUpDown } from "lucide-react";
import AppLayout from "@/layouts/app-layout";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { useState, useEffect } from "react";
import { useForm } from "@inertiajs/react";
import ErrorBoundary from "@/components/ErrorBoundary";
import { CheckCircle, XCircle } from "lucide-react";
import * as XLSX from "xlsx";

interface Record {
    staff_id: string;
    shop: string;
    lastname: string;
    firstname: string;
    othername: string;
    petra_id: string;
    ghana_card: string;
    basic_salary?: number | null;
    basic_salary?: number | null;
    basic?: number | null;
    petra_amount: number | null;
}

interface Props {
    records: {
        data: Record[];
        current_page: number;
        last_page: number;
        total: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
        from?: number;
    };
    selectedMonth: string;
    formattedMonth: string;
    totalRecords: number;
    searchCount: number | null;
    flash?: {
        success?: string;
        error?: string;
    };
}

const breadcrumbs = [
    { title: "PETRA Payment", href: "#" },
    { title: "Olam Food", href: "/olamfoodpetrapay" },
];

export default function OlamfoodPetraPay({ records, selectedMonth, formattedMonth, totalRecords, searchCount, flash }: Props) {
    const [showToast, setShowToast] = useState(false);
    const [toastMessage, setToastMessage] = useState("");
    const [toastType, setToastType] = useState<"success" | "error">("success");
    const [search, setSearch] = useState("");
    const [sort, setSort] = useState("staff_id:asc");
    const [isExporting, setIsExporting] = useState(false);

    const { data, setData, errors } = useForm({
        month: selectedMonth,
    });

    useEffect(() => {
        if (flash?.success) {
            setToastMessage(flash.success);
            setToastType("success");
            setShowToast(true);
        } else if (flash?.error) {
            setToastMessage(flash.error);
            setToastType("error");
            setShowToast(true);
        }
    }, [flash]);

    useEffect(() => {
        if (Object.keys(errors).length > 0) {
            const errorMessage = Object.values(errors).join(" | ");
            setToastMessage(errorMessage);
            setToastType("error");
            setShowToast(true);
        }
    }, [errors]);

    useEffect(() => {
        if (showToast) {
            const timeout = toastType === "error" ? 6000 : 3000;
            const timer = setTimeout(() => setShowToast(false), timeout);
            return () => clearTimeout(timer);
        }
    }, [showToast, toastType]);

    const handleSearchSort = () => {
        const [sortColumn, sortOrder] = sort.split(':');
        router.get(
            '/olamfoodpetrapay',
            { month: data.month, search, sort: sortColumn, order: sortOrder },
            { preserveState: true, preserveScroll: true }
        );
    };

    const handleMonthChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        setData("month", e.target.value);
        const [sortColumn, sortOrder] = sort.split(':');
        router.get(
            '/olamfoodpetrapay',
            { month: e.target.value, search, sort: sortColumn, order: sortOrder },
            { preserveState: false, preserveScroll: true }
        );
    };

    const handleSortToggle = (column: string) => {
        const [currentCol, currentDir] = sort.split(':');
        const newDir = currentCol === column && currentDir === 'asc' ? 'desc' : 'asc';
        setSort(`${column}:${newDir}`);
        router.get(
            '/olamfoodpetrapay',
            { month: data.month, search, sort: column, order: newDir },
            { preserveState: true, preserveScroll: true }
        );
    };

    const handleExport = async () => {
        try {
            setIsExporting(true);
            const [sortColumn, sortOrder] = sort.split(':');
            const url = `/olamfoodpetrapay/export?month=${encodeURIComponent(data.month)}&search=${encodeURIComponent(search)}&sort=${encodeURIComponent(sortColumn)}&order=${encodeURIComponent(sortOrder)}?month=${encodeURIComponent(data.month)}&search=${encodeURIComponent(search)}&sort=${encodeURIComponent(sortColumn)}&order=${encodeURIComponent(sortOrder)}`;
            const response = await fetch(url);

            if (!response.ok) {
                const text = await response.text();
                let errorMsg = "Failed to export data";
                try {
                    const json = JSON.parse(text);
                    errorMsg = json.error || errorMsg;
                } catch {
                    errorMsg = text || errorMsg;
                }
                throw new Error(errorMsg);
            }

            const blob = await response.blob();
            const downloadUrl = window.URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = downloadUrl;
            link.download = `OlamfoodPetraPay_${data.month}.xlsx`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            window.URL.revokeObjectURL(downloadUrl);

            setToastMessage("Export completed successfully");
            setToastType("success");
            setShowToast(true);
        } catch (err: any) {
            setToastMessage(err?.message || "Export failed");
            setToastType("error");
            setShowToast(true);
        } finally {
            setIsExporting(false);
        }
    };

    return (
        <ErrorBoundary>
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Olam Food PETRA Payment" />

                <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                    {showToast && (
                        <div
                            className={`fixed top-4 right-4 z-50 flex items-center gap-2 rounded-lg p-4 shadow-lg w-full sm:w-auto max-w-sm ${
                                toastType === "success" ? "bg-green-500 text-white" : "bg-red-500 text-white"
                            } animate-in fade-in slide-in-from-top-5`}
                        >
                            {toastType === "success" ? <CheckCircle className="h-5 w-5" /> : <XCircle className="h-5 w-5" />}
                            <span>{toastMessage}</span>
                            <Button
                                variant="ghost"
                                size="sm"
                                className="ml-auto p-1 text-white hover:text-white hover:bg-black/20"
                                onClick={() => setShowToast(false)}
                            >
                                <XCircle className="h-4 w-4" />
                            </Button>
                        </div>
                    )}

                    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div>
                            <h1 className="text-2xl font-bold">Olam Food PETRA Payment</h1>
                            <p className="text-sm text-muted-foreground mt-1">
                                Tier 2 / PETRA pension contributions for Olam Food employees
                            </p>
                        </div>

                        <div className="flex flex-wrap items-center gap-2">
                            <div className="flex items-center gap-2">
                                <Label htmlFor="month" className="text-sm whitespace-nowrap">Month:</Label>
                                <Input
                                    id="month"
                                    type="month"
                                    value={data.month}
                                    onChange={handleMonthChange}
                                    className="w-40"
                                />
                            </div>

                            <Button
                                variant="outline"
                                onClick={handleExport}
                                disabled={isExporting || !records?.data || records.data.length === 0}
                                className="flex items-center gap-1 bg-green-50 text-green-700 border-green-200 hover:bg-green-100"
                            >
                                <FileSpreadsheet className="h-4 w-4 text-green-600" />
                                {isExporting ? "Exporting..." : "Export Excel"}
                            </Button>
                        </div>
                    </div>

                    <Card>
                        <CardHeader className="pb-3">
                            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                                <div>
                                    <CardTitle>PETRA Payment — {formattedMonth}</CardTitle>
                                    <p className="text-xs text-muted-foreground mt-1">
                                        {searchCount !== null
                                            ? `Showing ${searchCount} of ${totalRecords} matching records`
                                            : `Total Records: ${totalRecords}`}
                                    </p>
                                </div>

                                <div className="flex items-center gap-2">
                                    <Input
                                        placeholder="Search by ID, name, PETRA No..."
                                        value={search}
                                        onChange={(e) => setSearch(e.target.value)}
                                        onKeyDown={(e) => e.key === "Enter" && handleSearchSort()}
                                        className="w-full sm:w-64"
                                    />
                                    <Button size="sm" onClick={handleSearchSort}>
                                        Search
                                    </Button>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent>
                            {records?.data && records.data.length > 0 ? (
                                <>
                                    <div className="rounded-md border overflow-x-auto">
                                        <Table>
                                            <TableHeader>
                                                <TableRow>
                                                    <TableHead className="w-12">No.</TableHead>
                                                    <TableHead>
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            className="-ml-3 h-8 data-[state=open]:bg-accent"
                                                            onClick={() => handleSortToggle('staff_id')}
                                                        >
                                                            Staff ID
                                                            <ArrowUpDown className="ml-2 h-3.5 w-3.5" />
                                                        </Button>
                                                    </TableHead>
                                                    <TableHead>
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            className="-ml-3 h-8 data-[state=open]:bg-accent"
                                                            onClick={() => handleSortToggle('lastname')}
                                                        >
                                                            Employee Name
                                                            <ArrowUpDown className="ml-2 h-3.5 w-3.5" />
                                                        </Button>
                                                    </TableHead>
                                                    <TableHead>Shop</TableHead>
                                                    <TableHead>Tier 2 / PETRA ID</TableHead>
                                                    <TableHead>Ghana Card</TableHead>
                                                    <TableHead className="text-right">Basic (GHS)</TableHead>
                                                    <TableHead className="text-right font-semibold">PETRA (5%) (GHS)</TableHead>
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody>
                                                {records.data.map((r, idx) => (
                                                    <TableRow key={r.staff_id + idx}>
                                                        <TableCell>{(records.from ?? 1) + idx}</TableCell>
                                                        <TableCell className="font-mono font-medium">{r.staff_id}</TableCell>
                                                        <TableCell>
                                                            {[r.lastname, r.firstname, r.othername].filter(Boolean).join(" ")}
                                                        </TableCell>
                                                        <TableCell>{r.shop || "Olam Food"}</TableCell>
                                                        <TableCell className="font-mono text-xs">{r.petra_id || "—"}</TableCell>
                                                        <TableCell className="font-mono text-xs">{r.ghana_card || "—"}</TableCell>
                                                        <TableCell className="text-right font-mono">
                                                            {r.basic !== null ? Number(r.basic).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : "—"}
                                                        </TableCell>
                                                        <TableCell className="text-right font-mono font-semibold text-emerald-700">
                                                            {r.petra_amount !== null ? Number(r.petra_amount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : "—"}
                                                        </TableCell>
                                                    </TableRow>
                                                ))}
                                            </TableBody>
                                        </Table>
                                    </div>

                                    {records.links && records.links.length > 3 && (
                                        <div className="flex justify-end gap-2 mt-4">
                                            {records.links.map((link, idx) => (
                                                <Button
                                                    key={idx}
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
                                </>
                            ) : (
                                <div className="text-center py-12">
                                    <FileSpreadsheet className="mx-auto h-12 w-12 text-gray-400" />
                                    <h3 className="mt-2 text-sm font-semibold text-gray-900">No PETRA records found</h3>
                                    <p className="mt-1 text-sm text-gray-500">
                                        No PETRA contributions recorded for Olam Food in {formattedMonth}.
                                    </p>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </AppLayout>
        </ErrorBoundary>
    );
}
