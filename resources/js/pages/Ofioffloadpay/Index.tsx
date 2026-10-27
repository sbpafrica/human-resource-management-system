import { Head, router, Link } from "@inertiajs/react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Download, Upload } from "lucide-react";
import AppLayout from "@/layouts/app-layout";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger, DialogFooter } from "@/components/ui/dialog";
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
 employeename: string;
 shop: string | null;
 bank: string | null;
 account: string | null;
 code: string | null;
 branch: string | null;
 takehomepay: number | null;
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
 { title: "Ofioffload", href: "/ofioffloadpay" },
];

export default function OfioffloadPay({ records, selectedMonth, formattedMonth, totalRecords, searchCount, flash }: Props) {
 const [isBulkUploadOpen, setIsBulkUploadOpen] = useState(false);
 const [showToast, setShowToast] = useState(false);
 const [toastMessage, setToastMessage] = useState("");
 const [toastType, setToastType] = useState<"success" | "error">("success");
 const [search, setSearch] = useState("");
 const [sort, setSort] = useState("StaffID:asc");

 const { data, setData, post, processing, errors, reset } = useForm({
 month: selectedMonth,
 file: null as File | null,
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
 route('ofioffloadpay.index'),
 { month: data.month, search, sort: sortColumn, order: sortOrder },
 { preserveState: true, preserveScroll: true }
 );
 };

 const handleMonthChange = (e: React.ChangeEvent<HTMLInputElement>) => {
 setData("month", e.target.value);
 const [sortColumn, sortOrder] = sort.split(':');
 router.get(
 route('ofioffloadpay.index'),
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
 route('ofioffloadpay.index'),
 { month: data.month, search, sort: sortColumn, order: sortOrder },
 { preserveState: true, preserveScroll: true }
 );
 };

 const handleDownloadTemplate = () => {
 const link = document.createElement("a");
 link.href = route("ofioffloadpay.download-template");
 link.download = "ofioffload_template.xlsx";
 document.body.appendChild(link);
 link.click();
 document.body.removeChild(link);
 };

 const handleBulkUpload = (e: React.FormEvent<HTMLFormElement>) => {
 e.preventDefault();
 if (data.file) {
 post(route("ofioffloadpay.upload"), {
 forceFormData: true,
 preserveState: true,
 onSuccess: () => {
 setIsBulkUploadOpen(false);
 reset();
 },
 });
 }
 };

 const handleDelete = () => {
 post(route("ofioffloadpay.delete"), {
 onSuccess: () => {
 setSearch("");
 setSort("StaffID:asc");
 handleSearchSort();
 },
 });
 };

 // ──────────────────────────────────────────────────────────────────────
//  FINAL WORKING VERSION – BOLD + FONT-SIZE 11 + AUTO-SIZED COLUMNS
// ──────────────────────────────────────────────────────────────────────
const handleExport = async () => {
  try {
    const csrfToken = document
      .querySelector('meta[name="csrf-token"]')
      ?.getAttribute('content');

    if (!csrfToken) throw new Error('CSRF token missing');

    const [sortColumn, sortOrder] = sort.split(':');

    const response = await fetch(
      route('ofioffloadpay.export', {
        month: data.month,
        search: search || undefined,
        sort: sortColumn,
        order: sortOrder,
      }),
      {
        method: 'GET',
        headers: {
          Accept: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
          'X-CSRF-TOKEN': csrfToken,
        },
      }
    );

    if (!response.ok) {
      let errorMsg = 'Export failed';
      try {
        const err = await response.json();
        errorMsg = err.error || errorMsg;
      } catch {}
      throw new Error(errorMsg);
    }

    const blob = await response.blob();
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `olam_offload_pay_${data.month}.xlsx`;
    document.body.appendChild(a);
    a.click();
    a.remove();
    window.URL.revokeObjectURL(url);

    setToastMessage('Olam Offload payroll exported successfully!');
    setToastType('success');
    setShowToast(true);
  } catch (error: any) {
    console.error('Export error:', error);
    setToastMessage(error.message || 'Failed to export data');
    setToastType('error');
    setShowToast(true);
  }
};

 return (

 <ErrorBoundary>
 <AppLayout breadcrumbs={breadcrumbs}>
 <Head title="Ofioffload" />
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
 <h1 className="text-2xl font-bold">Ofioffload</h1>
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
 <Button onClick={handleDownloadTemplate} className="w-full sm:w-auto bg-blue-500 hover:bg-blue-600">
 <Download className="h-4 w-4 mr-2" />
 Download Template
 </Button>
 <Dialog
 open={isBulkUploadOpen}
 onOpenChange={(open) => {
 setIsBulkUploadOpen(open);
 if (!open) reset();
 }}
 >
 <DialogTrigger asChild>
 <Button className="w-full sm:w-auto bg-green-500 hover:bg-green-600">
 <Upload className="h-4 w-4 mr-2" />
 Upload File
 </Button>
 </DialogTrigger>
 <DialogContent className="p-4 sm:max-w-md w-full">
 <DialogHeader>
 <DialogTitle>Upload Ofioffload Data</DialogTitle>
 </DialogHeader>
 <form onSubmit={handleBulkUpload}>
 <div className="grid gap-4 py-4">
 <div className="grid gap-2">
 <Label htmlFor="file">Upload Excel File</Label>
 <Input
 id="file"
 type="file"
 accept=".xlsx,.xls,.csv,.ods"
 onChange={(e) => {
 const file = e.target.files?.[0];
 if (file) setData("file", file);
 }}
 />
 {errors.file && <p className="text-red-500 text-sm">{errors.file}</p>}
 </div>
 </div>
 <DialogFooter className="flex flex-col sm:flex-row sm:justify-end gap-2">
 <Button type="submit" disabled={processing || !data.file}>
 Upload
 </Button>
 </DialogFooter>
 </form>
 </DialogContent>
 </Dialog>
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
 placeholder="Search by Staff ID, Name, or Bank..."
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
 <SelectItem value="employeename:asc">Name (Asc)</SelectItem>
 <SelectItem value="employeename:desc">Name (Desc)</SelectItem>
 <SelectItem value="shop:asc">Department (Asc)</SelectItem>
 <SelectItem value="shop:desc">Department (Desc)</SelectItem>
 <SelectItem value="bank:asc">Bank Name (Asc)</SelectItem>
 <SelectItem value="bank:desc">Bank Name (Desc)</SelectItem>
 </SelectContent>
 </Select>
 </div>
 <Button variant="destructive" onClick={handleDelete} disabled={processing} className="w-full sm:w-auto">
 Delete Records
 </Button>
 <Button onClick={handleExport} className="w-full sm:w-auto bg-purple-500 hover:bg-purple-600">
 <Download className="h-4 w-4 mr-2" />
 Export to Excel
 </Button>
 </div>
 </div>
 <Card>
 <CardHeader>
 <CardTitle>Ofioffload Salary Summary for {formattedMonth}</CardTitle>
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
 <div className="font-semibold">{record.employeename}</div>
 <div className="text-sm text-muted-foreground">Staff ID: {record.StaffID}</div>
 <div className="text-sm text-muted-foreground">Department: {record.shop || "OFI Offload"}</div>
 <div className="text-sm text-muted-foreground">Bank: {record.bank || ""}</div>
 <div className="text-sm text-muted-foreground">Account: {record.account || ""}</div>
 <div className="text-sm text-muted-foreground">Branch Code: {record.code || ""}</div>
 <div className="text-sm text-muted-foreground">Branch: {record.branch || ""}</div>
 <div className="text-sm text-muted-foreground">Net Pay: {record.takehomepay != null ? record.takehomepay : ""}</div>
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
 <Button variant="ghost" onClick={() => handleSortChange(`employeename:${sort === "employeename:asc" ? "desc" : "asc"}`)}>
 Name {sort === "employeename:asc" ? "↑" : sort === "employeename:desc" ? "↓" : ""}
 </Button>
 </TableHead>
 <TableHead>
 <Button variant="ghost" onClick={() => handleSortChange(`shop:${sort === "shop:asc" ? "desc" : "asc"}`)}>
 Department {sort === "shop:asc" ? "↑" : sort === "shop:desc" ? "↓" : ""}
 </Button>
 </TableHead>
 <TableHead>
 <Button variant="ghost" onClick={() => handleSortChange(`bank:${sort === "bank:asc" ? "desc" : "asc"}`)}>
 Bank Name {sort === "bank:asc" ? "↑" : sort === "bank:desc" ? "↓" : ""}
 </Button>
 </TableHead>
 <TableHead>Account</TableHead>
 <TableHead>Branch Code</TableHead>
 <TableHead>Branch</TableHead>
 <TableHead>Net Pay</TableHead>
 </TableRow>
 </TableHeader>
 <TableBody>
 {records.data.map((record, index) => (
 <TableRow key={`${record.StaffID}-${index}`}>
 <TableCell>{record.StaffID}</TableCell>
 <TableCell>{record.employeename}</TableCell>
 <TableCell>{record.shop || "OFI Ofload"}</TableCell>
 <TableCell>{record.bank || ""}</TableCell>
 <TableCell>{record.account || ""}</TableCell>
 <TableCell>{record.code || ""}</TableCell>
 <TableCell>{record.branch || ""}</TableCell>
 <TableCell>{record.takehomepay != null ? record.takehomepay : ""}</TableCell>
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
