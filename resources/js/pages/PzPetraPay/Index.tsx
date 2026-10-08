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
        current_page: number;
        last_page: number;
        total: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
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
    { title: "PZ Cussons PETRA Pay", href: "/pzpetrapay" },
];

export default function PzPetraPay({
    records,
    selectedMonth,
    formattedMonth,
    totalRecords,
    searchCount,
    flash,
}: Props) {
    const [showToast, setShowToast] = useState(false);
    const [toastMessage, setToastMessage] = useState("");
    const [toastType, setToastType] = useState<"success" | "error">("success");
    const [search, setSearch] = useState("");
    const [sort, setSort] = useState("staff_id:asc");

    const { data, setData, errors } = useForm({
        month: selectedMonth,
    });

    // Flash messages & errors
    useEffect(() => {
        if (flash?.success || flash?.error) {
            setToastMessage(flash.success || flash.error || "");
            setToastType(flash.success ? "success" : "error");
            setShowToast(true);
        }
    }, [flash]);

    useEffect(() => {
        if (Object.keys(errors).length > 0) {
            setToastMessage(Object.values(errors).join(" | "));
            setToastType("error");
            setShowToast(true);
        }
    }, [errors]);

    useEffect(() => {
        if (showToast) {
            const timer = setTimeout(
                () => setShowToast(false),
                toastType === "error" ? 60000 : 3000
            );
            return () => clearTimeout(timer);
        }
    }, [showToast, toastType]);

    // Handlers
    const handleSearchSort = () => {
        const [sortColumn, sortOrder] = sort.split(":");
        router.get(
            route("pzpetrapay.index"),
            { month: data.month, search, sort: sortColumn, order: sortOrder },
            { preserveState: true, preserveScroll: true }
        );
    };

    const handleMonthChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const newMonth = e.target.value;
        setData("month", newMonth);
        const [sortColumn, sortOrder] = sort.split(":");
        router.get(
            route("pzpetrapay.index"),
            { month: newMonth, search, sort: sortColumn, order: sortOrder },
            { preserveState: true, preserveScroll: true }
        );
    };

    const handleSearchChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        setSearch(e.target.value);
    };

    const handleSortChange = (value: string) => {
        setSort(value);
        const [sortColumn, sortOrder] = value.split(":");
        router.get(
            route("pzpetrapay.index"),
            { month: data.month, search, sort: sortColumn, order: sortOrder },
            { preserveState: true, preserveScroll: true }
        );
    };

    const handleExport = async () => {
        try {
            const csrfToken = document
                .querySelector('meta[name="csrf-token"]')
                ?.getAttribute("content");
            if (!csrfToken) throw new Error("CSRF token not found");

            const [sortColumn, sortOrder] = sort.split(":");

            const url = route("pzpetrapay.export", {
                month: data.month,
                search,
                sort: sortColumn,
                order: sortOrder,
            });

            const response = await fetch(url, {
                method: "GET",
                headers: {
                    Accept:
                        "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
                    "X-CSRF-TOKEN": csrfToken,
                },
            });

            if (!response.ok) {
                const text = await response.text();
                let errorMsg = text || response.statusText;
                try {
                    const json = JSON.parse(text);
                    errorMsg = json.error || errorMsg;
                } catch {}
                throw new Error(errorMsg);
            }

            const blob = await response.blob();
            const downloadUrl = window.URL.createObjectURL(blob);
            const link = document.createElement("a");
            link.href = downloadUrl;
            link.download = `pzpetrapay_${data.month}.xlsx`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            window.URL.revokeObjectURL(downloadUrl);

            setToastMessage("Excel exported successfully!");
            setToastType("success");
            setShowToast(true);
        } catch (error: any) {
            console.error("Export error:", error);
            setToastMessage("Export failed: " + error.message);
            setToastType("error");
            setShowToast(true);
        }
    };

    return (
        <ErrorBoundary>
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="PZ Cussons PETRA Pay" />

                <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                    {/* Toast */}
                    {showToast && (
                        <div
                            className={`fixed top-4 right-4 z-50 flex items-center gap-2 rounded-lg p-4 shadow-lg max-w-sm text-white ${
                                toastType === "success"
                                    ? "bg-green-500"
                                    : "bg-red-500"
                            } animate-in fade-in slide-in-from-top-5`}
                        >
                            {toastType === "success" ? (
                                <CheckCircle className="h-5 w-5" />
                            ) : (
                                <XCircle className="h-5 w-5" />
                            )}
                            <span>{toastMessage}</span>
                            {toastType === "error" && (
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    className="ml-auto text-white hover:bg-red-600"
                                    onClick={() => setShowToast(false)}
                                >
                                    <XCircle className="h-4 w-4" />
                                </Button>
                            )}
                        </div>
                    )}

                    {/* Header & Controls */}
                    <div className="flex flex-col gap-6">
                        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                            <h1 className="text-2xl font-bold">PZ Cussons PETRA Pay</h1>
                            <div className="flex items-center gap-3">
                                <Label htmlFor="month">Select Month:</Label>
                                <Input
                                    id="month"
                                    type="month"
                                    value={data.month}
                                    onChange={handleMonthChange}
                                    min="2020-01"
                                    max={new Date().toISOString().slice(0, 7)}
                                    className="w-full sm:w-48"
                                />
                            </div>
                        </div>

                        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-end gap-4">
                            <div className="flex items-center gap-3">
                                <Label htmlFor="search">Search:</Label>
                                <Input
                                    id="search"
                                    type="text"
                                    value={search}
                                    onChange={handleSearchChange}
                                    onKeyUp={handleSearchSort}
                                    placeholder="Staff ID, Name, PETRA ID..."
                                    className="w-full sm:w-64"
                                />
                            </div>

                            <div className="flex items-center gap-3">
                                <Label htmlFor="sort">Sort By:</Label>
                                <Select value={sort} onValueChange={handleSortChange}>
                                    <SelectTrigger id="sort" className="w-full sm:w-48">
                                        <SelectValue placeholder="Sort by..." />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="staff_id:asc">Staff ID (Asc)</SelectItem>
                                        <SelectItem value="staff_id:desc">Staff ID (Desc)</SelectItem>
                                        <SelectItem value="lastname:asc">Last Name (Asc)</SelectItem>
                                        <SelectItem value="lastname:desc">Last Name (Desc)</SelectItem>
                                        <SelectItem value="shop:asc">Shop (Asc)</SelectItem>
                                        <SelectItem value="shop:desc">Shop (Desc)</SelectItem>
                                        <SelectItem value="petra_id:asc">PETRA ID (Asc)</SelectItem>
                                        <SelectItem value="petra_id:desc">PETRA ID (Desc)</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <Button
                                onClick={handleExport}
                                className="bg-purple-600 hover:bg-purple-700 text-white"
                            >
                                <Download className="h-4 w-4 mr-2" />
                                Export to Excel
                            </Button>
                        </div>
                    </div>

                    {/* Main Card */}
                    <Card>
                        <CardHeader>
                            <CardTitle>
                                PZ Cussons PETRA Payment Summary for {formattedMonth}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="mb-4 text-sm text-muted-foreground">
                                Showing {records.data.length} of {searchCount ?? totalRecords} records
                                {searchCount !== null && ` (filtered from ${totalRecords})`}
                            </div>

                            {records.data.length === 0 ? (
                                <p className="text-center text-gray-500 py-10">
                                    No records found for the selected month.
                                </p>
                            ) : (
                                <>
                                    {/* Mobile View - Cards */}
                                    <div className="block md:hidden space-y-4">
                                        {records.data.map((record, index) => (
                                            <div
                                                key={index}
                                                className="border rounded-lg p-5 bg-card shadow-sm"
                                            >
                                                <div className="font-semibold text-lg">
                                                    {record.lastname}, {record.firstname}{" "}
                                                    {record.othername && `- ${record.othername}`}
                                                </div>
                                                <div className="mt-3 space-y-1 text-sm">
                                                    <div>
                                                        <span className="font-medium">Staff ID:</span>{" "}
                                                        {record.staff_id}
                                                    </div>
                                                    <div>
                                                        <span className="font-medium">Shop:</span>{" "}
                                                        {record.shop}
                                                    </div>
                                                    <div>
                                                        <span className="font-medium">PETRA ID:</span>{" "}
                                                        {record.petra_id || "—"}
                                                    </div>
                                                    <div>
                                                        <span className="font-medium">Ghana Card:</span>{" "}
                                                        {record.ghana_card || "—"}
                                                    </div>
                                                    <div>
                                                        <span className="font-medium">Basic Salary:</span>{" "}
                                                        {record.basic_salary?.toLocaleString() || "N/A"}
                                                    </div>
                                                    <div className="font-bold text-green-600">
                                                        PETRA Amount:{" "}
                                                        {record.petra_amount?.toLocaleString() || "N/A"}
                                                    </div>
                                                </div>
                                            </div>
                                        ))}
                                    </div>

                                    {/* Desktop View - Table */}
                                    <div className="hidden md:block overflow-x-auto">
                                        <Table>
                                            <TableHeader>
                                                <TableRow>
                                                    <TableHead>
                                                        <Button
                                                            variant="ghost"
                                                            onClick={() =>
                                                                handleSortChange(
                                                                    sort.startsWith("staff_id")
                                                                        ? sort.includes("asc")
                                                                            ? "staff_id:desc"
                                                                            : "staff_id:asc"
                                                                        : "staff_id:asc"
                                                                )
                                                            }
                                                        >
                                                            Staff ID{" "}
                                                            {sort === "staff_id:asc" && "↑"}
                                                            {sort === "staff_id:desc" && "↓"}
                                                        </Button>
                                                    </TableHead>
                                                    <TableHead>
                                                        <Button
                                                            variant="ghost"
                                                            onClick={() =>
                                                                handleSortChange(
                                                                    sort.startsWith("shop")
                                                                        ? sort.includes("asc")
                                                                            ? "shop:desc"
                                                                            : "shop:asc"
                                                                        : "shop:asc"
                                                                )
                                                            }
                                                        >
                                                            Shop{" "}
                                                            {sort === "shop:asc" && "↑"}
                                                            {sort === "shop:desc" && "↓"}
                                                        </Button>
                                                    </TableHead>
                                                    <TableHead>
                                                        <Button
                                                            variant="ghost"
                                                            onClick={() =>
                                                                handleSortChange(
                                                                    sort.startsWith("lastname")
                                                                        ? sort.includes("asc")
                                                                            ? "lastname:desc"
                                                                            : "lastname:asc"
                                                                        : "lastname:asc"
                                                                )
                                                            }
                                                        >
                                                            Last Name{" "}
                                                            {sort === "lastname:asc" && "↑"}
                                                            {sort === "lastname:desc" && "↓"}
                                                        </Button>
                                                    </TableHead>
                                                    <TableHead>First Name</TableHead>
                                                    <TableHead>Other Name</TableHead>
                                                    <TableHead>
                                                        <Button
                                                            variant="ghost"
                                                            onClick={() =>
                                                                handleSortChange(
                                                                    sort.startsWith("petra_id")
                                                                        ? sort.includes("asc")
                                                                            ? "petra_id:desc"
                                                                            : "petra_id:asc"
                                                                        : "petra_id:asc"
                                                                )
                                                            }
                                                        >
                                                            PETRA ID{" "}
                                                            {sort === "petra_id:asc" && "↑"}
                                                            {sort === "petra_id:desc" && "↓"}
                                                        </Button>
                                                    </TableHead>
                                                    <TableHead>Ghana Card</TableHead>
                                                    <TableHead>Basic Salary</TableHead>
                                                    <TableHead>PETRA Amount</TableHead>
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody>
                                                {records.data.map((record, index) => (
                                                    <TableRow key={index}>
                                                        <TableCell className="font-medium">
                                                            {record.staff_id}
                                                        </TableCell>
                                                        <TableCell>{record.shop}</TableCell>
                                                        <TableCell>{record.lastname}</TableCell>
                                                        <TableCell>{record.firstname}</TableCell>
                                                        <TableCell>{record.othername || "—"}</TableCell>
                                                        <TableCell>{record.petra_id || "—"}</TableCell>
                                                        <TableCell>{record.ghana_card || "—"}</TableCell>
                                                        <TableCell>
                                                            {record.basic_salary?.toLocaleString() || "N/A"}
                                                        </TableCell>
                                                        <TableCell className="font-bold text-green-600">
                                                            {record.petra_amount?.toLocaleString() || "N/A"}
                                                        </TableCell>
                                                    </TableRow>
                                                ))}
                                            </TableBody>
                                        </Table>
                                    </div>
                                </>
                            )}

                            {/* Pagination */}
                            {records.links && records.links.length > 3 && (
                                <div className="flex justify-center gap-2 mt-8 flex-wrap">
                                    {records.links.map((link, index) => (
                                        <Button
                                            key={index}
                                            variant={link.active ? "default" : "outline"}
                                            size="sm"
                                            disabled={!link.url}
                                            asChild={!!link.url}
                                        >
                                            {link.url ? (
                                                <a
                                                    href={link.url}
                                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                                />
                                            ) : (
                                                <span
                                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                                />
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
