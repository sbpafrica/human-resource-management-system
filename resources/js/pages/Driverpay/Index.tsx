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
  employ: string;
  name: string;
  shop: string | null;
  bank: string | null;
  account: string | null;
  code: string | null;
  branch: string | null;
  netpay: number | null;   // this is netpay from Driver table
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
  flash?: { success?: string; error?: string };
}

const breadcrumbs = [{ title: "Driver", href: "/driverpay" }];

export default function DriverPay({ records, selectedMonth, formattedMonth, totalRecords, searchCount, flash }: Props) {
  const [isBulkUploadOpen, setIsBulkUploadOpen] = useState(false);
  const [showToast, setShowToast] = useState(false);
  const [toastMessage, setToastMessage] = useState("");
  const [toastType, setToastType] = useState<"success" | "error">("success");
  const [search, setSearch] = useState("");
  const [sort, setSort] = useState("employ:asc");

  const { data, setData, post, processing, errors, reset } = useForm({
    month: selectedMonth,
    file: null as File | null,
  });
const [isDeleteDialogOpen, setIsDeleteDialogOpen] = useState(false);
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
      const timer = setTimeout(() => setShowToast(false), toastType === "error" ? 60000 : 4000);
      return () => clearTimeout(timer);
    }
  }, [showToast, toastType]);

  const handleSearchSort = () => {
    const [sortColumn, sortOrder] = sort.split(':');
    router.get(route('driverpay.index'), { month: data.month, search, sort: sortColumn, order: sortOrder }, { preserveState: true, preserveScroll: true });
  };

  const handleMonthChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    setData("month", e.target.value);
    const [sortColumn, sortOrder] = sort.split(':');
    router.get(route('driverpay.index'), { month: e.target.value, search, sort: sortColumn, order: sortOrder }, { preserveState: true, preserveScroll: true });
  };

  const handleSearchChange = (e: React.ChangeEvent<HTMLInputElement>) => setSearch(e.target.value);
  const handleSortChange = (value: string) => {
    setSort(value);
    const [sortColumn, sortOrder] = value.split(':');
    router.get(route('driverpay.index'), { month: data.month, search, sort: sortColumn, order: sortOrder }, { preserveState: true, preserveScroll: true });
  };

  const handleDownloadTemplate = () => {
    const link = document.createElement("a");
    link.href = route("driverpay.download-template");
    link.download = "driver_template.xlsx";
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  };

  const handleBulkUpload = (e: React.FormEvent) => {
    e.preventDefault();
    if (data.file) {
      post(route("driverpay.upload"), {
        forceFormData: true,
        onSuccess: () => { setIsBulkUploadOpen(false); reset(); },
      });
    }
  };

 const handleDelete = () => {
  post(route("driverpay.delete"), {
    preserveState: true,
    preserveScroll: true,
    onSuccess: () => {
      setSearch("");
      setSort("employ:asc");
      handleSearchSort();
      setIsDeleteDialogOpen(false);
    },
    onError: () => {
      setIsDeleteDialogOpen(false);
    },
  });
};

 const handleExport = async () => {
  try {
    const csrfToken = document
      .querySelector('meta[name="csrf-token"]')
      ?.getAttribute('content');

    if (!csrfToken) throw new Error('CSRF token missing');

    const [sortColumn, sortOrder] = sort.split(':');

    const response = await fetch(
      route('driverpay.export', {
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
    a.download = `drivers_pay_${data.month}.xlsx`;
    document.body.appendChild(a);
    a.click();
    a.remove();
    window.URL.revokeObjectURL(url);

    setToastMessage('Drivers payroll exported successfully!');
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
            <Head title="Driver Payroll" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">

                {/* Toast */}
                {showToast && (
                    <div className={`fixed top-4 right-4 z-50 flex items-center gap-2 rounded-lg p-4 shadow-lg max-w-sm ${toastType === "success" ? "bg-green-500" : "bg-red-500"} text-white animate-in fade-in slide-in-from-top-5`}>
                        {toastType === "success" ? <CheckCircle className="h-5 w-5" /> : <XCircle className="h-5 w-5" />}
                        <span>{toastMessage}</span>
                        {toastType === "error" && <Button variant="ghost" size="sm" onClick={() => setShowToast(false)}><XCircle className="h-4 w-4" /></Button>}
                    </div>
                )}

                <div className="flex flex-col gap-4">
                    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <h1 className="text-2xl font-bold">Driver Payroll</h1>
                        <div className="flex flex-col sm:flex-row gap-4">
                            <div className="flex items-center gap-2">
                                <Label htmlFor="month">Select Month:</Label>
                                <Input id="month" type="month" value={data.month} onChange={handleMonthChange} min="2020-01" className="w-48" />
                            </div>
                            <Button onClick={handleDownloadTemplate} className="bg-blue-500 hover:bg-blue-600">
                                <Download className="h-4 w-4 mr-2" />Download Template
                            </Button>
                            <Dialog open={isBulkUploadOpen} onOpenChange={(o) => { setIsBulkUploadOpen(o); if (!o) reset(); }}>
                                <DialogTrigger asChild>
                                    <Button className="bg-green-500 hover:bg-green-600">
                                        <Upload className="h-4 w-4 mr-2" />Upload File
                                    </Button>
                                </DialogTrigger>
                                <DialogContent>
                                    <DialogHeader><DialogTitle>Upload Driver Payroll</DialogTitle></DialogHeader>
                                    <form onSubmit={handleBulkUpload}>
                                        <div className="grid gap-4 py-4">
                                            <div className="grid gap-2">
                                                <Label htmlFor="file">Excel File</Label>
                                                <Input id="file" type="file" accept=".xlsx,.xls,.csv,.ods" onChange={(e) => e.target.files?.[0] && setData("file", e.target.files[0])} />
                                                {errors.file && <p className="text-red-500 text-sm">{errors.file}</p>}
                                            </div>
                                        </div>
                                        <DialogFooter>
                                            <Button type="submit" disabled={processing || !data.file}>Upload</Button>
                                        </DialogFooter>
                                    </form>
                                </DialogContent>
                            </Dialog>
                        </div>
                    </div>

                    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-end gap-4">
                        <div className="flex items-center gap-2">
                            <Label htmlFor="search">Search:</Label>
                            <Input id="search" value={search} onChange={handleSearchChange} onKeyUp={handleSearchSort} placeholder="Staff ID, Name, Bank..." className="w-48" />
                        </div>
                        <div className="flex items-center gap-2">
                            <Label htmlFor="sort">Sort By:</Label>
                            <Select value={sort} onValueChange={handleSortChange}>
                                <SelectTrigger className="w-48"><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="employ:asc">Staff ID (Asc)</SelectItem>
                                    <SelectItem value="employ:desc">Staff ID (Desc)</SelectItem>
                                    <SelectItem value="name:asc">Name (Asc)</SelectItem>
                                    <SelectItem value="name:desc">Name (Desc)</SelectItem>
                                    <SelectItem value="shop:asc">Department (Asc)</SelectItem>
                                    <SelectItem value="shop:desc">Department (Desc)</SelectItem>
                                    <SelectItem value="bank:asc">Bank Name (Asc)</SelectItem>
                                    <SelectItem value="bank:desc">Bank Name (Desc)</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <Dialog open={isDeleteDialogOpen} onOpenChange={setIsDeleteDialogOpen}>
  <DialogTrigger asChild>
    <Button variant="destructive" disabled={processing}>
      Delete Records
    </Button>
  </DialogTrigger>
  <DialogContent>
    <DialogHeader>
      <DialogTitle className="text-red-600">Confirm Delete</DialogTitle>
    </DialogHeader>
    <div className="py-6">
      <p className="text-lg">
        Are you sure you want to <strong>permanently delete</strong> all payroll records for
      </p>
      <p className="text-2xl font-bold text-center my-4 text-destructive">
        {formattedMonth}
      </p>
      <p className="text-sm text-red-600 font-medium">
        This action <strong>cannot be undone</strong>.
      </p>
    </div>
    <DialogFooter className="gap-3">
      <Button variant="outline" onClick={() => setIsDeleteDialogOpen(false)}>
        Cancel
      </Button>
      <Button
        variant="destructive"
        onClick={handleDelete}
        disabled={processing}
      >
        {processing ? "Deleting..." : "Yes, Delete All Records"}
      </Button>
    </DialogFooter>
  </DialogContent>
</Dialog>
<Button onClick={handleExport} className="w-full sm:w-auto bg-purple-500 hover:bg-purple-600">
 <Download className="h-4 w-4 mr-2" />
 Export to Excel
 </Button>
                    </div>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Driver Salary Summary – {formattedMonth}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="mb-4">
                            Showing {records.data.length} of {searchCount ?? totalRecords} records
                            {searchCount !== null && ` (filtered from ${totalRecords})`}
                        </div>

                        {records.data.length === 0 ? (
                            <p className="text-center text-gray-500 py-10">No records found for {formattedMonth}.</p>
                        ) : (
                            <>
                                {/* Mobile Cards */}
                                <div className="block md:hidden space-y-4">
                                    {records.data.map((r) => (
                                        <div key={r.employ} className="border rounded-lg p-4 bg-background">
                                            <div className="font-semibold">{r.name}</div>
                                            <div className="text-sm text-muted-foreground">ID: {r.employ}</div>
                                            <div className="text-sm text-muted-foreground">Shop: {r.shop || "N/A"}</div>
                                            <div className="text-sm text-muted-foreground">Bank: {r.bank || "N/A"}</div>
                                            <div className="text-sm text-muted-foreground">Net Pay: {r.netpay?.toLocaleString() || "N/A"}</div>
                                        </div>
                                    ))}
                                </div>

                                {/* Desktop Table */}
                                <div className="hidden md:block overflow-x-auto">
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead><Button variant="ghost" onClick={() => handleSortChange(`employ:${sort.startsWith('employ') && sort.endsWith('asc') ? 'desc' : 'asc'}`)}>Staff ID</Button></TableHead>
                                                <TableHead><Button variant="ghost" onClick={() => handleSortChange(`name:${sort.startsWith('name') && sort.endsWith('asc') ? 'desc' : 'asc'}`)}>Name</Button></TableHead>
                                                <TableHead><Button variant="ghost" onClick={() => handleSortChange(`shop:${sort.startsWith('shop') && sort.endsWith('asc') ? 'desc' : 'asc'}`)}>Department</Button></TableHead>
                                                <TableHead><Button variant="ghost" onClick={() => handleSortChange(`bank:${sort.startsWith('bank') && sort.endsWith('asc') ? 'desc' : 'asc'}`)}>Bank Name</Button></TableHead>
                                                <TableHead>Account</TableHead>
                                                <TableHead>Branch Code</TableHead>
                                                <TableHead>Branch</TableHead>
                                                <TableHead className="text-right">Net Pay</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {records.data.map((r) => (
                                                <TableRow key={r.employ}>
                                                    <TableCell>{r.employ}</TableCell>
                                                    <TableCell>{r.name}</TableCell>
                                                    <TableCell>{r.shop || "N/A"}</TableCell>
                                                    <TableCell>{r.bank || "N/A"}</TableCell>
                                                    <TableCell>{r.account || "N/A"}</TableCell>
                                                    <TableCell>{r.code || "N/A"}</TableCell>
                                                    <TableCell>{r.branch || "N/A"}</TableCell>
                                                    <TableCell className="text-right font-medium">
                                                        {r.netpay ? Number(r.netpay).toLocaleString('en-GH', { minimumFractionDigits: 2 }) : "N/A"}
                                                    </TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                </div>
                            </>
                        )}

                        {records.links && records.data.length > 0 && (
                            <div className="flex justify-center mt-6 gap-2">
                                {records.links.map((link, i) => (
                                    <Button key={i} variant={link.active ? "default" : "outline"} disabled={!link.url} asChild={!!link.url}>
                                        {link.url ? <Link href={link.url} preserveState preserveScroll data={{ month: data.month, search, sort: sort.split(':')[0], order: sort.split(':')[1] }} dangerouslySetInnerHTML={{ __html: link.label }} /> : <span dangerouslySetInnerHTML={{ __html: link.label }} />}
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
