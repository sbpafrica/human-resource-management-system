// resources/js/pages/Payroll/SalaryPayment/Index.tsx
import { Head, router, Link, usePage } from "@inertiajs/react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Download, Upload } from "lucide-react";
import AppLayout from "@/layouts/app-layout";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
  DialogFooter,
} from "@/components/ui/dialog";
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
  staffida: string;
  name: string;
  shop: string | null;
  bank: string | null;
  account: string | null;
  code: string | null;
  branch: string | null;
  netpay: number | null;
}

interface ClientProps {
  id: number;
  client_name: string;
  client_dbname: string;
  short_name: string | null;
  color: string;
}

interface Props {
  client: ClientProps;
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
    selectedMonth?: string; // ← NEW: PRESERVE MONTH AFTER UPLOAD/DELETE
  };
}

export default function SalaryPaymentIndex({
  client,
  records,
  selectedMonth,
  formattedMonth,
  totalRecords,
  searchCount,
  flash,
}: Props) {
  const [isBulkUploadOpen, setIsBulkUploadOpen] = useState(false);
  const [showToast, setShowToast] = useState(false);
  const [toastMessage, setToastMessage] = useState("");
  const [toastType, setToastType] = useState<"success" | "error">("success");
  const [search, setSearch] = useState("");
  const [sort, setSort] = useState("staffida:asc");

  // FIXED: PRESERVE MONTH FROM FLASH AFTER UPLOAD/DELETE
  const initialMonth = flash?.selectedMonth || selectedMonth;

  const { data, setData, post, processing, errors, reset } = useForm({
    month: initialMonth,
    file: null as File | null,
  });

  // FIXED: Use CORRECT route prefix
  const routePrefix = 'payroll.salary-payment';

  const breadcrumbs = [
    { title: "Payroll", href: "/payroll" },
    { title: client.short_name || client.client_name, href: `/payroll/${client.client_dbname}/bank-payment` },
    { title: "Salary Payment", href: "#" },
  ];

  useEffect(() => {
    if (flash?.success || flash?.error) {
      setToastMessage(flash.success || flash.error || "");
      setToastType(flash.success ? "success" : "error");
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
      route(`${routePrefix}.index`, { client_dbname: client.client_dbname }),
      { month: data.month, search, sort: sortColumn, order: sortOrder },
      { preserveState: true, preserveScroll: true }
    );
  };

  const handleMonthChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const newMonth = e.target.value;
    setData("month", newMonth);
    const [sortColumn, sortOrder] = sort.split(':');
    router.get(
      route(`${routePrefix}.index`, { client_dbname: client.client_dbname }),
      { month: newMonth, search, sort: sortColumn, order: sortOrder },
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
      route(`${routePrefix}.index`, { client_dbname: client.client_dbname }),
      { month: data.month, search, sort: sortColumn, order: sortOrder },
      { preserveState: true, preserveScroll: true }
    );
  };

  const handleDownloadTemplate = () => {
    window.location.href = route(`${routePrefix}.download-template`, { client_dbname: client.client_dbname });
  };

  const handleBulkUpload = (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    if (data.file) {
      post(route(`${routePrefix}.upload`, { client_dbname: client.client_dbname }), {
        forceFormData: true,
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
          setIsBulkUploadOpen(false);
          reset('file');
          setToastMessage("Upload successful!");
          setToastType("success");
          setShowToast(true);
        },
        onError: () => {
          setToastMessage("Upload failed");
          setToastType("error");
          setShowToast(true);
        },
      });
    }
  };

  const handleDelete = () => {
    post(route(`${routePrefix}.delete`, { client_dbname: client.client_dbname }), {
      preserveState: true,
      preserveScroll: true,
      onSuccess: () => {
        setSearch("");
        setSort("staffida:asc");
        handleSearchSort();
        setToastMessage("Records deleted successfully");
        setToastType("success");
        setShowToast(true);
      },
      onError: () => {
        setToastMessage("Delete failed");
        setToastType("error");
        setShowToast(true);
      },
    });
  };

  const handleExport = async () => {
    try {
      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
      if (!csrfToken) throw new Error('CSRF token not found');

      const [sortColumn, sortOrder] = sort.split(':');

      const response = await fetch(
        route(`${routePrefix}.export`, {
          client_dbname: client.client_dbname,
          month: data.month,
          search: search || undefined,
          sort: sortColumn,
          order: sortOrder,
        }),
        {
          method: 'GET',
          headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken,
          },
        }
      );

      if (!response.ok) {
        const err = await response.json();
        throw new Error(err.error ?? 'Export failed');
      }

      const records: Record[] = await response.json();

      const headers = ['Staff ID', 'Name', 'Department', 'Bank Name', 'Account', 'Branch Code', 'Branch', 'Net Pay'];
      const rows = records.map((r) => [
        r.staffida,
        r.name,
        r.shop ?? 'N/A',
        r.bank ?? 'N/A',
        r.account ?? 'N/A',
        r.code ?? 'N/A',
        r.branch ?? 'N/A',
        r.netpay != null ? Number(r.netpay).toFixed(2) : '0.00',
      ]);

      const wb = XLSX.utils.book_new();
      const ws = XLSX.utils.aoa_to_sheet([headers, ...rows]);

      const range = XLSX.utils.decode_range(ws['!ref']!);
      for (let C = range.s.c; C <= range.e.c; ++C) {
        const addr = XLSX.utils.encode_cell({ r: 0, c: C });
        if (!ws[addr]) continue;
        ws[addr].s = { font: { bold: true }, alignment: { horizontal: 'left' } };
      }

      ws['!cols'] = headers.map(() => ({ wch: 20 }));

      XLSX.utils.book_append_sheet(wb, ws, 'Salary Payment');
      XLSX.writeFile(wb, `${client.short_name || client.client_dbname}_${data.month}_export.xlsx`);
    } catch (error) {
      setToastMessage(error instanceof Error ? error.message : 'Export failed');
      setToastType('error');
      setShowToast(true);
    }
  };

  return (
    <ErrorBoundary>
      <AppLayout breadcrumbs={breadcrumbs}>
        <Head title={`${client.short_name || client.client_name} - Salary Payment`} />
        <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">

          {/* Toast */}
          {showToast && (
            <div className={`fixed top-4 right-4 z-50 flex items-center gap-2 rounded-lg p-4 shadow-lg w-full sm:w-auto max-w-sm ${
              toastType === "success" ? "bg-green-500 text-white" : "bg-red-500 text-white"
            } animate-in fade-in slide-in-from-top-5`}>
              {toastType === "success" ? <CheckCircle className="h-5 w-5" /> : <XCircle className="h-5 w-5" />}
              <span>{toastMessage}</span>
              {toastType === "error" && (
                <Button variant="ghost" size="sm" className="ml-2 p-1 text-white hover:bg-red-600" onClick={() => setShowToast(false)}>
                  <XCircle className="h-4 w-4" />
                </Button>
              )}
            </div>
          )}

          <div className="flex flex-col gap-4">
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
              <h1 className="text-2xl font-bold">{client.short_name || client.client_name} - Salary Payment</h1>
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
                <Button onClick={handleDownloadTemplate} className="bg-blue-500 hover:bg-blue-600">
                  <Download className="h-4 w-4 mr-2" /> Template
                </Button>
                <Dialog open={isBulkUploadOpen} onOpenChange={(open) => { setIsBulkUploadOpen(open); if (!open) reset(); }}>
                  <DialogTrigger asChild>
                    <Button className="bg-green-500 hover:bg-green-600">
                      <Upload className="h-4 w-4 mr-2" /> Upload
                    </Button>
                  </DialogTrigger>
                  <DialogContent>
                    <DialogHeader><DialogTitle>Upload {client.short_name} Data</DialogTitle></DialogHeader>
                    <form onSubmit={handleBulkUpload}>
                      <div className="grid gap-4 py-4">
                        <div className="grid gap-2">
                          <Label htmlFor="uc-file">Excel File</Label>
                          <Input
                            id="uc-file"
                            type="file"
                            accept=".xlsx,.xls"
                            onChange={(e) => e.target.files?.[0] && setData("file", e.target.files[0])}
                          />
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
                <Input
                  id="search"
                  type="text"
                  value={search}
                  onChange={handleSearchChange}
                  onKeyUp={handleSearchSort}
                  placeholder="Staff ID, Name, Bank..."
                  className="w-full sm:w-48"
                />
              </div>
              <div className="flex items-center gap-2">
                <Label htmlFor="sort">Sort By:</Label>
                <Select value={sort} onValueChange={handleSortChange}>
                  <SelectTrigger id="sort" className="w-full sm:w-48">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="staffida:asc">Staff ID (Asc)</SelectItem>
                    <SelectItem value="staffida:desc">Staff ID (Desc)</SelectItem>
                    <SelectItem value="name:asc">Name (Asc)</SelectItem>
                    <SelectItem value="name:desc">Name (Desc)</SelectItem>
                    <SelectItem value="shop:asc">Department (Asc)</SelectItem>
                    <SelectItem value="shop:desc">Department (Desc)</SelectItem>
                    <SelectItem value="bank:asc">Bank (Asc)</SelectItem>
                    <SelectItem value="bank:desc">Bank (Desc)</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <Button variant="destructive" onClick={handleDelete} disabled={processing}>
                Delete Records
              </Button>
              <Button onClick={handleExport} className="bg-purple-500 hover:bg-purple-600">
                <Download className="h-4 w-4 mr-2" /> Export
              </Button>
            </div>
          </div>

          <Card>
            <CardHeader>
              <CardTitle>{client.short_name || client.client_name} Salary Summary - {formattedMonth}</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="mb-4">
                Showing {records.data.length} of {searchCount ?? totalRecords} records
                {searchCount !== null && ` (filtered from ${totalRecords})`}
              </div>

              {records.data.length > 0 ? (
                <>
                  <div className="hidden md:block">
                    <Table>
                      <TableHeader>
                        <TableRow>
                          <TableHead><Button variant="ghost" onClick={() => handleSortChange(`staffida:${sort.includes("staffida") && sort.endsWith("asc") ? "desc" : "asc"}`)}>Staff ID {sort.includes("staffida") && (sort.endsWith("asc") ? "↑" : "↓")}</Button></TableHead>
                          <TableHead><Button variant="ghost" onClick={() => handleSortChange(`name:${sort.includes("name") && sort.endsWith("asc") ? "desc" : "asc"}`)}>Name</Button></TableHead>
                          <TableHead><Button variant="ghost" onClick={() => handleSortChange(`shop:${sort.includes("shop") && sort.endsWith("asc") ? "desc" : "asc"}`)}>Department</Button></TableHead>
                          <TableHead><Button variant="ghost" onClick={() => handleSortChange(`bank:${sort.includes("bank") && sort.endsWith("asc") ? "desc" : "asc"}`)}>Bank</Button></TableHead>
                          <TableHead>Account</TableHead>
                          <TableHead>Branch Code</TableHead>
                          <TableHead>Branch</TableHead>
                          <TableHead>Net Pay</TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {records.data.map((record) => (
                          <TableRow key={record.staffida}>
                            <TableCell>{record.staffida}</TableCell>
                            <TableCell>{record.name}</TableCell>
                            <TableCell>{record.shop || "N/A"}</TableCell>
                            <TableCell>{record.bank || "N/A"}</TableCell>
                            <TableCell>{record.account || "N/A"}</TableCell>
                            <TableCell>{record.code || "N/A"}</TableCell>
                            <TableCell>{record.branch || "N/A"}</TableCell>
                            <TableCell>{record.netpay != null ? Number(record.netpay).toLocaleString('en-GH', { minimumFractionDigits: 2 }) : "N/A"}</TableCell>
                          </TableRow>
                        ))}
                      </TableBody>
                    </Table>
                  </div>

                  <div className="flex justify-center mt-6 gap-2">
                    {records.links.map((link, i) => (
                      <Button key={i} variant={link.active ? "default" : "outline"} size="sm" disabled={!link.url} asChild={!!link.url}>
                        {link.url ? (
                          <Link
                            href={link.url}
                            preserveState
                            preserveScroll
                            data={{
                              month: data.month,
                              search,
                              sort: sort.split(':')[0],
                              order: sort.split(':')[1]
                            }}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                          />
                        ) : (
                          <span dangerouslySetInnerHTML={{ __html: link.label }} />
                        )}
                      </Button>
                    ))}
                  </div>
                </>
              ) : (
                <p className="text-center py-12 text-gray-500">No records found for {formattedMonth}.</p>
              )}
            </CardContent>
          </Card>
        </div>
      </AppLayout>
    </ErrorBoundary>
  );
}
