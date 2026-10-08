import { Head, router } from "@inertiajs/react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Download } from "lucide-react";
import AppLayout from "@/layouts/app-layout";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { useState, useEffect } from "react";
import { useForm } from "@inertiajs/react";
import ErrorBoundary from "@/components/ErrorBoundary";
import { CheckCircle, XCircle } from "lucide-react";
import * as XLSX from "xlsx";

interface Record {
    StaffID: string;
    shop: string;
    lastname: string;
    firstname: string;
    othername: string;
    ssnit_number: string;
    gh_card_number: string;
    basicsalary: number | null;
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
    { title: "Ofioffload SSNIT Pay", href: "/ofioffloadssnitpay" },
];

export default function OfioffloadssnitPay({ records, selectedMonth, formattedMonth, totalRecords, searchCount, flash }: Props) {
    const [showToast, setShowToast] = useState(false);
    const [toastMessage, setToastMessage] = useState("");
    const [toastType, setToastType] = useState<"success" | "error">("success");
    const [search, setSearch] = useState("");
    const [sort, setSort] = useState("id:asc");

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
            route('ofioffloadssnitpay.index'),
            { month: data.month, search, sort: sortColumn, order: sortOrder },
            { preserveState: true, preserveScroll: true }
        );
    };

    const handleMonthChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        setData("month", e.target.value);
        const [sortColumn, sortOrder] = sort.split(':');
        router.get(
            route('ofioffloadssnitpay.index'),
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
            route('ofioffloadssnitpay.index'),
            { month: data.month, search, sort: sortColumn, order: sortOrder },
            { preserveState: true, preserveScroll: true }
        );
    };

    const handleExport = async () => {
        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const [sortColumn, sortOrder] = sort.split(':');

            const response = await fetch(
                route('ofioffloadssnitpay.index', { month: data.month, search, sort: sortColumn, order: sortOrder }),
                {
                    method: 'GET',
                    headers: {
                        ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                    },
                }
            );

            if (!response.ok) {
                const text = await response.text();
                let errorMessage = `HTTP ${response.status}: Failed to export data`;
                try {
                    const errorData = JSON.parse(text);
                    errorMessage = errorData.error || errorMessage;
                } catch {
                    errorMessage = text ? text.substring(0, 100) : errorMessage;
                }
                setToastMessage(errorMessage);
                setToastType('error');
                setShowToast(true);
                return;
            }

            const blob = await response.blob();
            const downloadUrl = window.URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = downloadUrl;
            link.download = `Ofioffloadssnit_${data.month}.xlsx`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            window.URL.revokeObjectURL(downloadUrl);

            setToastMessage('Export successful!');
            setToastType('success');
            setShowToast(true);
        } catch (error) {
            console.error('Export error:', error);
            setToastMessage('Error exporting data: ' + (error instanceof Error ? error.message : 'Unknown error'));
            setToastType('error');
            setShowToast(true);
        }
    };

    return (
        <ErrorBoundary>
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Ofioffload SSNIT Pay" />
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
                            <h1 className="text-2xl font-bold">Ofioffload SSNIT Pay</h1>
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
                        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-end gap-4">
                            <div className="flex items-center gap-2">
                                <Label htmlFor="search">Search:</Label>
                                <Input
                                    id="search"
                                    type="text"
                                    value={search}
                                    onChange={handleSearchChange}
                                    onKeyUp={handleSearchSort}
                                    placeholder="Search by Staff ID, Name, SSNIT Number..."
                                    className="w-full sm:w-48"
                                />
                            </div>
                            <div className="flex items-center gap-2">
                                <Label htmlFor="sort">Sort By:</Label>
                                <Select value={sort} onValueChange={handleSortChange}>
                                    <SelectTrigger id="sort" className="w-full sm:w-48">
                                        <SelectValue placeholder="Sort By" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="StaffID:asc">Staff ID (Asc)</SelectItem>
                                        <SelectItem value="StaffID:desc">Staff ID (Desc)</SelectItem>
                                        <SelectItem value="lastname:asc">Last Name (Asc)</SelectItem>
                                        <SelectItem value="lastname:desc">Last Name (Desc)</SelectItem>
                                        <SelectItem value="shop:asc">Shop (Asc)</SelectItem>
                                        <SelectItem value="shop:desc">Shop (Desc)</SelectItem>
                                        <SelectItem value="ssnit_number:asc">SSNIT Number (Asc)</SelectItem>
                                        <SelectItem value="ssnit_number:desc">SSNIT Number (Desc)</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <Button onClick={handleExport} className="w-full sm:w-auto bg-purple-500 hover:bg-purple-600">
                                <Download className="h-4 w-4 mr-2" />
                                Export to Excel
                            </Button>
                        </div>
                    </div>
                    <Card>
                        <CardHeader>
                            <CardTitle>Ofioffload SSNIT Payment Summary for {formattedMonth}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="mb-4">
                                Showing {records.data.length} of {searchCount ?? totalRecords} records
                                {searchCount !== null && ` (filtered from ${totalRecords})`}
                            </div>
                            {records.data.length > 0 ? (
                                <div className="block md:hidden space-y-4">
                                    {records.data.map((record, index) => (
                                        <div key={`${record.StaffID}-${index}`} className="border rounded-lg p-4 bg-background">
                                            <div className="flex flex-col gap-2">
                                                <div className="font-semibold">{record.lastname}, {record.firstname}</div>
                                                <div className="text-sm text-muted-foreground">Staff ID: {record.StaffID}</div>
                                                <div className="text-sm text-muted-foreground">Shop: {record.shop}</div>
                                                <div className="text-sm text-muted-foreground">Other Name: {record.othername}</div>
                                                <div className="text-sm text-muted-foreground">SSNIT Number: {record.ssnit_number}</div>
                                                <div className="text-sm text-muted-foreground">GH Card Number: {record.gh_card_number}</div>
                                                <div className="text-sm text-muted-foreground">Basic: {record.basicsalary != null ? record.basicsalary : "N/A"}</div>
                                                <div className="text-sm text-muted-foreground">SSNIT Amount: {record.ssnit_amount != null ? record.ssnit_amount : "N/A"}</div>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <p className="text-center text-gray-500">No records found for the selected month.</p>
                            )}
                            {records.data.length > 0 && (
                                <div className="hidden md:block">
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>
                                                    <Button variant="ghost" onClick={() => handleSortChange(`StaffID:${sort === "StaffID:asc" ? "desc" : "asc"}`)}>
                                                        Staff ID {sort === "StaffID:asc" ? "↑" : sort === "StaffID:desc" ? "↓" : ""}
                                                    </Button>
                                                </TableHead>
                                                <TableHead>
                                                    <Button variant="ghost" onClick={() => handleSortChange(`shop:${sort === "shop:asc" ? "desc" : "asc"}`)}>
                                                        Shop {sort === "shop:asc" ? "↑" : sort === "shop:desc" ? "↓" : ""}
                                                    </Button>
                                                </TableHead>
                                                <TableHead>
                                                    <Button variant="ghost" onClick={() => handleSortChange(`lastname:${sort === "lastname:asc" ? "desc" : "asc"}`)}>
                                                        Last Name {sort === "lastname:asc" ? "↑" : sort === "lastname:desc" ? "↓" : ""}
                                                    </Button>
                                                </TableHead>
                                                <TableHead>First Name</TableHead>
                                                <TableHead>Other Name</TableHead>
                                                <TableHead>
                                                    <Button variant="ghost" onClick={() => handleSortChange(`ssnit_number:${sort === "ssnit_number:asc" ? "desc" : "asc"}`)}>
                                                        SSNIT Number {sort === "ssnit_number:asc" ? "↑" : sort === "ssnit_number:desc" ? "↓" : ""}
                                                    </Button>
                                                </TableHead>
                                                <TableHead>GH Card Number</TableHead>
                                                <TableHead>Basic</TableHead>
                                                <TableHead>SSNIT Amount</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {records.data.map((record, index) => (
                                                <TableRow key={`${record.StaffID}-${index}`}>
                                                    <TableCell>{record.StaffID}</TableCell>
                                                    <TableCell>{record.shop}</TableCell>
                                                    <TableCell>{record.lastname}</TableCell>
                                                    <TableCell>{record.firstname}</TableCell>
                                                    <TableCell>{record.othername}</TableCell>
                                                    <TableCell>{record.ssnit_number}</TableCell>
                                                    <TableCell>{record.gh_card_number}</TableCell>
                                                    <TableCell>{record.basicsalary != null ? record.basicsalary : "N/A"}</TableCell>
                                                    <TableCell>{record.ssnit_amount != null ? record.ssnit_amount : "N/A"}</TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                </div>
                            )}
                            {records.data.length > 0 && (
                                <div className="flex justify-between items-center mt-4">
                                    <div>
                                        Showing {records.data.length} of {searchCount ?? totalRecords} records
                                        {searchCount !== null && ` (filtered from ${totalRecords})`}
                                    </div>
                                    <div className="flex gap-2">
                                        {records.links.map((link, index) => (
                                            <Button
                                                key={index}
                                                variant={link.active ? "default" : "outline"}
                                                disabled={!link.url}
                                                asChild={!!link.url}
                                            >
                                                {link.url ? (
                                                    <a
                                                        href={link.url}
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
