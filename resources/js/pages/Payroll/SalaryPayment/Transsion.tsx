// resources/js/pages/Payroll/SalaryPayment/Transsion.tsx
import { Head, Link } from "@inertiajs/react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Download, Upload, Loader2 } from "lucide-react";
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
import { useState, useEffect, useMemo } from "react";
import { useForm } from "@inertiajs/react";
import ErrorBoundary from "@/components/ErrorBoundary";
import { CheckCircle, XCircle } from "lucide-react";

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
  short_name: string;
  client_dbname: string;
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
  };
}

export default function TranssionSalaryPayment({
  client,
  records: initialRecords,
  selectedMonth,
  formattedMonth,
  totalRecords: initialTotal,
  flash,
}: Props) {
  const [isBulkUploadOpen, setIsBulkUploadOpen] = useState(false);
  const [showToast, setShowToast] = useState(false);
  const [toastMessage, setToastMessage] = useState("");
  const [toastType, setToastType] = useState<"success" | "error">("success");
  const [search, setSearch] = useState("");
  const [sort, setSort] = useState("staffida");
  const [order, setOrder] = useState<"asc" | "desc">("asc");
  const [currentPage, setCurrentPage] = useState(1);
  const [isExporting, setIsExporting] = useState(false);

  const perPage = 25;

  const { data, setData, post, processing, errors, reset } = useForm({
    month: selectedMonth,
    file: null as File | null,
  });

  const routePrefix = 'salary-payment.transsion';

  const breadcrumbs = [
    { title: "Payroll", href: "/payroll" },
    { title: "Salary Payment", href: "/salary-payment" },
    { title: "Good Ease", href: "#" },
  ];

  // Toast handling
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
      const timer = setTimeout(() => setShowToast(false), toastType === "error" ? 10000 : 3000);
      return () => clearTimeout(timer);
    }
  }, [showToast, toastType]);

  // CLIENT-SIDE: Search, Sort, Pagination → INSTANT
  const { filteredData, totalResults } = useMemo(() => {
    let filtered = [...initialRecords.data];

    // Search
    if (search) {
      const term = search.toLowerCase();
      filtered = filtered.filter(r =>
        r.staffida.toLowerCase().includes(term) ||
        r.name.toLowerCase().includes(term) ||
        (r.bank && r.bank.toLowerCase().includes(term)) ||
        (r.shop && r.shop.toLowerCase().includes(term)) ||
        (r.account && r.account.includes(term))
      );
    }

    // Sort
    filtered.sort((a, b) => {
      let aVal: any = a[sort as keyof Record] ?? "";
      let bVal: any = b[sort as keyof Record] ?? "";

      if (sort === "netpay") {
        aVal = a.netpay ?? 0;
        bVal = b.netpay ?? 0;
      } else {
        aVal = aVal.toString().toLowerCase();
        bVal = bVal.toString().toLowerCase();
      }

      if (order === "asc") {
        return aVal > bVal ? 1 : -1;
      }
      return aVal < bVal ? 1 : -1;
    });

    const total = filtered.length;

    // Sync URL
    const params = new URLSearchParams();
    if (search) params.set("search", search);
    if (sort !== "staffida") params.set("sort", sort);
    if (order !== "asc") params.set("order", order);
    if (currentPage > 1) params.set("page", currentPage.toString());
    window.history.replaceState({}, "", `?${params.toString()}`);

    return { filteredData: filtered, totalResults: total };
  }, [initialRecords.data, search, sort, order, currentPage]);

  // Paginated slice
  const paginatedData = useMemo(() => {
    const start = (currentPage - 1) * perPage;
    return filteredData.slice(start, start + perPage);
  }, [filteredData, currentPage]);

  const totalPages = Math.ceil(totalResults / perPage);

  const handleSearchChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    setSearch(e.target.value);
    setCurrentPage(1);
  };

  const handleSort = (column: keyof Record) => {
    if (sort === column) {
      setOrder(order === "asc" ? "desc" : "asc");
    } else {
      setSort(column);
      setOrder("asc");
    }
    setCurrentPage(1);
  };

  const handleMonthChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const newMonth = e.target.value;
    setData("month", newMonth);
    window.location.href = route(`${routePrefix}.index`, { month: newMonth });
  };

  // EXPORT — WITH CURRENT FILTERS
  const handleExport = () => {
    setIsExporting(true);
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    fetch(route('salary-payment.transsion.export'), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'X-CSRF-TOKEN': token || '',
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: JSON.stringify({
        month: data.month,
        search: search || undefined,
        sort,
        order,
      }),
    })
      .then(res => {
        if (!res.ok) return res.json().then(err => { throw new Error(err.error || 'Export failed'); });
        return res.blob();
      })
      .then(blob => {
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `Good_Ease_${data.month}_Salary.xlsx`;
        a.click();
        window.URL.revokeObjectURL(url);
        setToastMessage("Exported successfully! 🎉");
        setToastType("success");
        setShowToast(true);
      })
      .catch(err => {
        console.error(err);
        setToastMessage("Export failed: " + err.message);
        setToastType("error");
        setShowToast(true);
      })
      .finally(() => setIsExporting(false));
  };

  const handleDownloadTemplate = () => {
    window.location.href = route(`${routePrefix}.template`);
  };

  const handleBulkUpload = (e: React.FormEvent) => {
    e.preventDefault();
    if (!data.file) return;
    post(route(`${routePrefix}.upload`), {
      forceFormData: true,
      onSuccess: () => {
        setIsBulkUploadOpen(false);
        reset('file');
        setToastMessage("Upload successful!");
        setToastType("success");
        setShowToast(true);
      },
    });
  };

  const handleDelete = () => {
    if (!confirm("Delete ALL Good Ease records?")) return;
    post(route(`${routePrefix}.delete`), {
      onSuccess: () => {
        window.location.reload();
      },
    });
  };

  return (
    <ErrorBoundary>
      <AppLayout breadcrumbs={breadcrumbs}>
        <Head title="Good Ease - Salary Payment" />
        <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-6 bg-gray-50">

          {/* Toast */}
          {showToast && (
            <div className={`fixed top-6 right-6 z-50 flex items-center gap-3 rounded-xl p-5 shadow-2xl min-w-80 border-l-4 text-white ${
              toastType === "success" ? "bg-green-600 border-green-400" : "bg-red-600 border-red-400"
            } animate-in slide-in-from-top-10`}>
              {toastType === "success" ? <CheckCircle className="h-6 w-6" /> : <XCircle className="h-6 w-6" />}
              <div>
                <p className="font-bold">Good Ease</p>
                <p className="text-sm">{toastMessage}</p>
              </div>
              <Button variant="ghost" size="sm" onClick={() => setShowToast(false)} className="text-white">
                <XCircle className="h-5 w-5" />
              </Button>
            </div>
          )}

          {/* Header */}
          <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
            <div>
              <h1 className="text-3xl font-bold flex items-center gap-3">
                <div className="w-12 h-12 rounded-lg" style={{ backgroundColor: client.color }} />
                Good Ease Salary
              </h1>
              <p className="text-gray-600 mt-1">Upload and download salary data for payment</p>
            </div>

            <div className="flex flex-wrap gap-3">
              <div className="flex items-center gap-3">
                <Label htmlFor="month">Month:</Label>
                <Input id="month" type="month" value={data.month} onChange={handleMonthChange} className="w-48" />
              </div>
              <Button onClick={handleDownloadTemplate} className="bg-blue-600 hover:bg-blue-700">
                <Download className="h-4 w-4 mr-2" /> Template
              </Button>
              <Dialog open={isBulkUploadOpen} onOpenChange={setIsBulkUploadOpen}>
                <DialogTrigger asChild>
                  <Button className="bg-green-600 hover:bg-green-700">
                    <Upload className="h-4 w-4 mr-2" /> Upload
                  </Button>
                </DialogTrigger>
                <DialogContent>
                  <DialogHeader><DialogTitle>Upload Good Ease Data</DialogTitle></DialogHeader>
                  <form onSubmit={handleBulkUpload}>
                    <div className="grid gap-4 py-4">
                      <Input type="file" accept=".xlsx,.xls,.csv,.ods" onChange={(e) => e.target.files?.[0] && setData("file", e.target.files[0])} />
                      {errors.file && <p className="text-red-500 text-sm">{errors.file}</p>}
                    </div>
                    <DialogFooter>
                      <Button type="submit" disabled={processing || !data.file}>
                        {processing ? "Uploading..." : "Upload"}
                      </Button>
                    </DialogFooter>
                  </form>
                </DialogContent>
              </Dialog>
            </div>
          </div>

          {/* Search + Actions */}
          <div className="bg-white p-5 rounded-xl shadow-md">
            <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
              <div className="flex items-center gap-3">
                <Label htmlFor="search">Search:</Label>
                <div className="relative">
                  <Input
                    id="search"
                    value={search}
                    onChange={handleSearchChange}
                    placeholder="Type anything → instant results"
                    className="w-96 pr-10"
                  />
                  {search && <Loader2 className="absolute right-3 top-3 h-4 w-4 animate-spin text-blue-600" />}
                </div>
              </div>

              <div className="flex gap-3">
                <Button variant="destructive" onClick={handleDelete}>Delete All</Button>
                <Button onClick={handleExport} disabled={isExporting} className="bg-purple-600 hover:bg-purple-700">
                  {isExporting ? (
                    <>
                      <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                      Exporting...
                    </>
                  ) : (
                    <>
                      <Download className="h-4 w-4 mr-2" />
                      Export Excel
                    </>
                  )}
                </Button>
              </div>
            </div>
          </div>

          {/* Table Card */}
          <Card className="border-2" style={{ borderColor: client.color }}>
            <CardHeader>
              <CardTitle>Good Ease Salary — {formattedMonth}</CardTitle>
              <p className="text-sm text-gray-600">
                {search
                  ? `⚡ Found ${totalResults.toLocaleString()} results instantly`
                  : `Total: ${initialTotal.toLocaleString()} records`
                }
              </p>
            </CardHeader>
            <CardContent>
              {paginatedData.length > 0 ? (
                <>
                  <div className="overflow-x-auto">
                    <Table>
                      <TableHeader>
                        <TableRow className="bg-gray-50">
                          <TableHead><Button variant="ghost" onClick={() => handleSort("staffida")}>Staff ID {sort === "staffida" && (order === "asc" ? "↑" : "↓")}</Button></TableHead>
                          <TableHead><Button variant="ghost" onClick={() => handleSort("name")}>Name {sort === "name" && (order === "asc" ? "↑" : "↓")}</Button></TableHead>
                          <TableHead><Button variant="ghost" onClick={() => handleSort("shop")}>Department {sort === "shop" && (order === "asc" ? "↑" : "↓")}</Button></TableHead>
                          <TableHead><Button variant="ghost" onClick={() => handleSort("bank")}>Bank {sort === "bank" && (order === "asc" ? "↑" : "↓")}</Button></TableHead>
                          <TableHead>Account</TableHead>
                          <TableHead>Branch Code</TableHead>
                          <TableHead>Branch</TableHead>
                          <TableHead className="text-right"><Button variant="ghost" onClick={() => handleSort("netpay")}>Net Pay {sort === "netpay" && (order === "asc" ? "↑" : "↓")}</Button></TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {paginatedData.map((r) => (
                          <TableRow key={r.staffida} className="hover:bg-gray-50">
                            <TableCell className="font-mono">{r.staffida}</TableCell>
                            <TableCell className="font-medium">{r.name}</TableCell>
                            <TableCell>{r.shop || "—"}</TableCell>
                            <TableCell>{r.bank || "—"}</TableCell>
                            <TableCell className="font-mono">{r.account || "—"}</TableCell>
                            <TableCell>{r.code || "—"}</TableCell>
                            <TableCell>{r.branch || "—"}</TableCell>
                            <TableCell className="text-right font-semibold">
                              {r.netpay ? Number(r.netpay).toLocaleString('en-GH', { minimumFractionDigits: 2 }) : "—"}
                            </TableCell>
                          </TableRow>
                        ))}
                      </TableBody>
                    </Table>
                  </div>

                  {/* Client-side Pagination */}
                  {totalPages > 1 && (
                    <div className="flex justify-center mt-6 gap-2 flex-wrap">
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => setCurrentPage(p => Math.max(1, p - 1))}
                        disabled={currentPage === 1}
                      >
                        ← Prev
                      </Button>
                      {Array.from({ length: Math.min(10, totalPages) }, (_, i) => {
                        let pageNum;
                        if (totalPages <= 10) {
                          pageNum = i + 1;
                        } else if (currentPage <= 6) {
                          pageNum = i + 1;
                        } else if (currentPage > totalPages - 5) {
                          pageNum = totalPages - 9 + i;
                        } else {
                          pageNum = currentPage - 5 + i;
                        }
                        return (
                          <Button
                            key={pageNum}
                            variant={currentPage === pageNum ? "default" : "outline"}
                            size="sm"
                            onClick={() => setCurrentPage(pageNum)}
                          >
                            {pageNum}
                          </Button>
                        );
                      })}
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => setCurrentPage(p => Math.min(totalPages, p + 1))}
                        disabled={currentPage === totalPages}
                      >
                        Next →
                      </Button>
                    </div>
                  )}
                </>
              ) : (
                <div className="text-center py-20">
                  <p className="text-xl text-gray-500">No records found</p>
                </div>
              )}
            </CardContent>
          </Card>
        </div>
      </AppLayout>
    </ErrorBoundary>
  );
}
