import { Head, router, Link } from "@inertiajs/react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Download, CheckCircle, XCircle } from "lucide-react";
import AppLayout from "@/layouts/app-layout";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { useState, useEffect } from "react";
import { useForm } from "@inertiajs/react";
import ErrorBoundary from "@/components/ErrorBoundary";

interface Record {
    staff_id: string;
    shop: string;
    lastname: string;
    firstname: string;
    othername: string;
    ssnit_number: string;
    gh_card_number: string;
    basic: number | null;
    ssnit_amount: number | null;
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
    { title: "Xiaomi SSNIT Pay", href: "/xiaomissnitpay" },
];

export default function XiaomiSsnitPay({ records, selectedMonth, formattedMonth, totalRecords, searchCount, flash }: Props) {
    const [showToast, setShowToast] = useState(false);
    const [toastMessage, setToastMessage] = useState("");
    const [toastType, setToastType] = useState<"success" | "error">("success");
    const [search, setSearch] = useState("");
    const [sort, setSort] = useState("staff_id:asc");

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
            const timeout = toastType === "error" ? 60000 : 3000;
            const timer = setTimeout(() => setShowToast(false), timeout);
            return () => clearTimeout(timer);
        }
    }, [showToast, toastType]);

    const handleSearchSort = () => {
        const [sortColumn, sortOrder] = sort.split(':');
        router.get(
            route('xiaomissnitpay.index'),
            { month: data.month, search, sort: sortColumn, order: sortOrder },
            { preserveState: true, preserveScroll: true }
        );
    };

    const handleMonthChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        setData("month", e.target.value);
        const [sortColumn, sortOrder] = sort.split(':');
        router.get(
            route('xiaomissnitpay.index'),
            { month: e.target.value, search, sort: sortColumn, order: sortOrder },
            { preserveState: true, preserveScroll: true }
        );
    };

    const handleSearchChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        setSearch(e.target.value);
    };

    const handleSortChange = (value: string) => {
        setSort(value);
        const [sortColumn, sortOrder] = value.split(':');
        router.get(
            route('xiaomissnitpay.index'),
            { month: data.month, search, sort: sortColumn, order: sortOrder },
            { preserveState: true, preserveScroll: true }
        );
    };

    const handleExport = () => {
        const [col, dir] = sort.split(':');
        window.location.href = route('xiaomissnitpay.export', {
            month: data.month,
            search: search || undefined,
            sort: col,
            order: dir,
        });
    };

    const recordsData = records?.data ?? [];

    return (
        <ErrorBoundary>
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Xiaomi SSNIT Pay" />
                <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                    {showToast && (
                        <div
                            className={`fixed top-4 right-4 z-50 flex items-center gap-2 rounded-lg p-4 shadow-lg w-full sm:w-auto max-w-sm ${
                                toastType === "success" ? "bg-green-500 text-white" : "bg-red-500 text-white"
                            } animate-in fade-in slide-in-from-top-5`}
                        >
                            {toastType === "success" ? <CheckCircle className="h-5 w-5" /> : <XCircle className="h-5 w-5" />}
                            <span>{toastMessage}</span>
                            {toastType === "error" && (
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    className="ml-2 p-1 text-white hover:text-white hover:bg-red-600"
                                    onClick={() => setShowToast(false)}
                                >
                                    <XCircle className="h-4 w-4" />
                                </Button>
                            )}
                        </div>
                    )}

                    <div className="flex flex-col gap-4">
                        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                            <h1 className="text-2xl font-bold">Xiaomi SSNIT Payment</h1>
                            <div className="flex flex-col sm:flex-row gap-4">
                                <div className="flex items-center gap-2">
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
                        </div>

                        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                            <div className="flex items-center gap-2">
                                <Label htmlFor="search">Search:</Label>
                                <Input
                                    id="search"
                                    type="text"
                                    value={search}
                                    onChange={handleSearchChange}
                                    onKeyUp={handleSearchSort}
                                    placeholder="Search by ID, Name, SSNIT..."
                                    className="w-full sm:w-64"
                                />
                            </div>

                            <div className="flex flex-wrap items-center gap-3">
                                <div className="flex items-center gap-2">
                                    <Label htmlFor="sort">Sort By:</Label>
                                    <Select value={sort} onValueChange={handleSortChange}>
                                        <SelectTrigger id="sort" className="w-full sm:w-48">
                                            <SelectValue placeholder="Sort By" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="staff_id:asc">Staff ID (Asc)</SelectItem>
                                            <SelectItem value="staff_id:desc">Staff ID (Desc)</SelectItem>
                                            <SelectItem value="lastname:asc">Last Name (Asc)</SelectItem>
                                            <SelectItem value="lastname:desc">Last Name (Desc)</SelectItem>
                                            <SelectItem value="ssnit_number:asc">SSNIT Number (Asc)</SelectItem>
                                            <SelectItem value="ssnit_number:desc">SSNIT Number (Desc)</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <Button onClick={handleExport} className="w-full sm:w-auto bg-purple-500 hover:bg-purple-600">
                                    <Download className="h-4 w-4 mr-2" />
                                    Export SSNIT
                                </Button>
                            </div>
                        </div>
                    </div>

                    <Card>
                        <CardHeader>
                            <CardTitle>SSNIT Details for {formattedMonth}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="mb-4 text-sm text-muted-foreground">
                                Showing {recordsData.length} of {searchCount ?? totalRecords ?? 0} records
                                {searchCount !== null && ` (filtered from ${totalRecords ?? 0})`}
                            </div>

                            {recordsData.length > 0 ? (
                                <div className="overflow-x-auto">
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>Staff ID</TableHead>
                                                <TableHead>Full Name</TableHead>
                                                <TableHead>SSNIT Number</TableHead>
                                                <TableHead>Ghana Card No.</TableHead>
                                                <TableHead className="text-right">Basic Salary</TableHead>
                                                <TableHead className="text-right">SSNIT (13.5%)</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {recordsData.map((record, index) => (
                                                <TableRow key={`${record.staff_id}-${index}`}>
                                                    <TableCell className="font-medium">{record.staff_id}</TableCell>
                                                    <TableCell>{`${record.lastname} ${record.firstname} ${record.othername}`.trim()}</TableCell>
                                                    <TableCell>{record.ssnit_number || "N/A"}</TableCell>
                                                    <TableCell>{record.gh_card_number || "N/A"}</TableCell>
                                                    <TableCell className="text-right">
                                                        {record.basic != null ? Number(record.basic).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : "N/A"}
                                                    </TableCell>
                                                    <TableCell className="text-right font-semibold">
                                                        {record.ssnit_amount != null ? Number(record.ssnit_amount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : "N/A"}
                                                    </TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                </div>
                            ) : (
                                <div className="text-center py-10 text-muted-foreground">
                                    No records found for {formattedMonth}.
                                </div>
                            )}

                            {records?.links && records.links.length > 3 && (
                                <div className="flex justify-between items-center mt-4">
                                    <div className="text-sm text-muted-foreground">
                                        Showing {recordsData.length} of {searchCount ?? totalRecords ?? 0} records
                                    </div>
                                    <div className="flex gap-2">
                                        {records.links.map((link, index) => (
                                            <Button
                                                key={index}
                                                variant={link.active ? "default" : "outline"}
                                                disabled={!link.url}
                                                asChild={!!link.url}
                                                size="sm"
                                            >
                                                {link.url ? (
                                                    <Link
                                                        href={link.url}
                                                        preserveState
                                                        preserveScroll
                                                        data={{ month: data.month, search, sort: sort.split(':')[0], order: sort.split(':')[1] }}
                                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                                    />
                                                ) : (
                                                    <span dangerouslySetInnerHTML={{ __html: link.label }} />
                                                )}
                                            </Button>
                                        ))}
                                    </div>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </AppLayout>
        </ErrorBoundary>
    );
}
