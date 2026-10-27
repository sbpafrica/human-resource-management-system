// resources/js/pages/Payroll/SalaryPayment/Melcom.tsx
import { Head, router } from "@inertiajs/react";
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
import { useState, useEffect } from "react";
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

interface Props {
  client: { short_name: string; color: string };
  records: {
    data: Record[];
    current_page: number;
    last_page: number;
    total: number;
    per_page: number;
    links: Array<{ url: string | null; label: string; active: boolean }>;
  };
  selectedMonth: string;
  formattedMonth: string;
  totalRecords: number;
  searchCount: number | null;
  flash?: { success?: string; error?: string };
  query?: {
    search?: string | null;
    sort?: string | null;
    order?: string | null;
  };
}

export default function MelcomSalaryPayment({
  client,
  records,
  selectedMonth,
  formattedMonth,
  totalRecords,
  searchCount,
  flash,
  query,
}: Props) {
  const [isBulkUploadOpen, setIsBulkUploadOpen] = useState(false);
  const [showToast, setShowToast] = useState(false);
  const [toastMessage, setToastMessage] = useState("");
  const [toastType, setToastType] = useState<"success" | "error">("success");
  const [isExporting, setIsExporting] = useState(false);
  const [isSearching, setIsSearching] = useState(false);

  // THE FINAL FIX — 100% SAFE NO MATTER WHAT COMES FROM SERVER
  const safeQuery = query ?? {};
  const [search, setSearch] = useState((safeQuery.search ?? "").toString());
  const [sort, setSort] = useState((safeQuery.sort ?? "staffida").toString());
  const [order, setOrder] = useState<"asc" | "desc">(
    safeQuery.order === "desc" ? "desc" : "asc"
  );

  const { data, setData, post, processing, errors, reset } = useForm({
    month: selectedMonth,
    file: null as File | null,
  });

  // DEBOUNCED SEARCH
  useEffect(() => {
    const timer = setTimeout(() => {
      router.get(
        route("salary-payment.melcom.index"),
        {
          search: search || undefined,
          sort,
          order,
          month: selectedMonth,
        },
        {
          preserveState: true,
          preserveScroll: true,
          replace: true,
          onStart: () => setIsSearching(true),
          onFinish: () => setIsSearching(false),
        }
      );
    }, 400);

    return () => clearTimeout(timer);
  }, [search]);

  const handleSort = (column: string) => {
    const newOrder = sort === column && order === "asc" ? "desc" : "asc";
    setSort(column);
    setOrder(newOrder);

    router.get(
      route("salary-payment.melcom.index"),
      {
        search: search || undefined,
        sort: column,
        order: newOrder,
        month: selectedMonth,
      },
      { preserveState: true, preserveScroll: true }
    );
  };

  const handleExport = () => {
    setIsExporting(true);
    const params = new URLSearchParams({
      month: selectedMonth,
      ...(search && { search }),
      sort,
      order,
    });
    window.location.href = `/salary-payment/melcom/export?${params.toString()}`;
    setTimeout(() => setIsExporting(false), 2000);
  };

  // Toast
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
      const timer = setTimeout(() => setShowToast(false), toastType === "error" ? 10000 : 4000);
      return () => clearTimeout(timer);
    }
  }, [showToast, toastType]);

  return (
    <ErrorBoundary>
      <AppLayout breadcrumbs={[
        { title: "Payroll", href: "/payroll" },
        { title: "Salary Payment", href: "/salary-payment" },
        { title: "Melcom", href: "#" },
      ]}>
        <Head title="Melcom - Salary Payment" />
        <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-6 bg-gray-50">

          {/* Toast */}
          {showToast && (
            <div className={`fixed top-6 right-6 z-50 flex items-center gap-3 rounded-xl p-5 shadow-2xl min-w-80 border-l-4 text-white animate-in slide-in-from-top-10 ${toastType === "success" ? "bg-green-600 border-green-800" : "bg-red-600 border-red-800"}`}>
              {toastType === "success" ? <CheckCircle className="h-6 w-6" /> : <XCircle className="h-6 w-6" />}
              <div>
                <p className="font-bold">Melcom</p>
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
                Melcom Salary Payment
              </h1>
              <p className="text-gray-600 mt-1">Upload, search, and export payroll data instantly</p>
            </div>

            <div className="flex flex-wrap gap-3 items-center">
              <div className="flex items-center gap-3">
                <Label htmlFor="month">Month:</Label>
                <Input
                  id="month"
                  type="month"
                  value={selectedMonth}
                  onChange={(e) => window.location.href = route("salary-payment.melcom.index", { month: e.target.value })}
                  className="w-48"
                />
              </div>

              <Button onClick={() => window.location.href = route("salary-payment.melcom.template")}>
                Template
              </Button>

              <Dialog open={isBulkUploadOpen} onOpenChange={setIsBulkUploadOpen}>
                <DialogTrigger asChild>
                  <Button className="bg-green-600 hover:bg-green-700">
                    Upload
                  </Button>
                </DialogTrigger>
                <DialogContent>
                  <DialogHeader><DialogTitle>Upload Melcom Salary Data</DialogTitle></DialogHeader>
                  <form onSubmit={(e) => { e.preventDefault(); if (data.file) post(route("salary-payment.melcom.upload"), { forceFormData: true, onSuccess: () => { setIsBulkUploadOpen(false); reset("file"); } }); }}>
                    <div className="grid gap-4 py-4">
                      <Input type="file" accept=".xlsx,.xls,.csv,.ods" onChange={(e) => e.target.files?.[0] && setData("file", e.target.files[0])} />
                      {errors.file && <p className="text-red-500 text-sm">{errors.file}</p>}
                    </div>
                    <DialogFooter>
                      <Button type="submit" disabled={processing || !data.file}>
                        {processing ? "Uploading..." : "Upload File"}
                      </Button>
                    </DialogFooter>
                  </form>
                </DialogContent>
              </Dialog>
            </div>
          </div>

          {/* Search Bar */}
          <div className="bg-white p-5 rounded-xl shadow-md">
            <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
              <div className="flex items-center gap-3">
                <Label htmlFor="search">Search All Records:</Label>
                <div className="relative">
                  <Input
                    id="search"
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    placeholder="Staff ID, Name, Bank, Shop, Account..."
                    className="w-96 pr-10"
                  />
                  {isSearching && <Loader2 className="absolute right-3 top-3 h-4 w-4 animate-spin text-blue-600" />}
                </div>
              </div>

              <div className="flex gap-3">
                <Button variant="destructive" onClick={() => confirm("Delete ALL records?") && post(route("salary-payment.melcom.delete"))}>
                  Delete All
                </Button>
                <Button onClick={handleExport} disabled={isExporting} className="bg-purple-600 hover:bg-purple-700">
                  {isExporting ? "Exporting..." : <>Export Excel</>}
                </Button>
              </div>
            </div>
          </div>

          {/* Table */}
          <Card className="border-2" style={{ borderColor: client.color }}>
            <CardHeader>
              <CardTitle>Melcom Salary — {formattedMonth}</CardTitle>
              <p className="text-sm text-gray-600">
                {search
                  ? `${(searchCount || records.total).toLocaleString()} record${records.total !== 1 ? "s" : ""} found${isSearching ? " (searching...)" : ""}`
                  : `Total: ${totalRecords.toLocaleString()} records`}
              </p>
            </CardHeader>
            <CardContent>
              {records.data.length > 0 ? (
                <>
                  <div className="overflow-x-auto">
                    <Table>
                      <TableHeader>
                        <TableRow className="bg-gray-50">
                          <TableHead><Button variant="ghost" onClick={() => handleSort("staffida")}>Staff ID {sort === "staffida" && (order === "asc" ? "Up" : "Down")}</Button></TableHead>
                          <TableHead><Button variant="ghost" onClick={() => handleSort("name")}>Name {sort === "name" && (order === "asc" ? "Up" : "Down")}</Button></TableHead>
                          <TableHead><Button variant="ghost" onClick={() => handleSort("shop")}>Department {sort === "shop" && (order === "asc" ? "Up" : "Down")}</Button></TableHead>
                          <TableHead><Button variant="ghost" onClick={() => handleSort("bank")}>Bank {sort === "bank" && (order === "asc" ? "Up" : "Down")}</Button></TableHead>
                          <TableHead>Account</TableHead>
                          <TableHead>Branch Code</TableHead>
                          <TableHead>Branch</TableHead>
                          <TableHead className="text-right"><Button variant="ghost" onClick={() => handleSort("netpay")}>Net Pay {sort === "netpay" && (order === "asc" ? "Up" : "Down")}</Button></TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {records.data.map((r) => (
                          <TableRow key={r.staffida} className="hover:bg-gray-50">
                            <TableCell className="font-mono">{r.staffida}</TableCell>
                            <TableCell className="font-medium">{r.name}</TableCell>
                            <TableCell>{r.shop || "—"}</TableCell>
                            <TableCell>{r.bank || "—"}</TableCell>
                            <TableCell className="font-mono">{r.account || "—"}</TableCell>
                            <TableCell>{r.code || "—"}</TableCell>
                            <TableCell>{r.branch || "—"}</TableCell>
                            <TableCell className="text-right font-semibold">
                              {r.netpay ? Number(r.netpay).toLocaleString("en-GH", { minimumFractionDigits: 2 }) : "—"}
                            </TableCell>
                          </TableRow>
                        ))}
                      </TableBody>
                    </Table>
                  </div>

                  {records.links?.length > 3 && (
                    <div className="flex justify-center mt-8 gap-1 border-t pt-6 flex-wrap">
                      {records.links.map((link, i) => (
                        <Button
                          key={i}
                          variant={link.active ? "default" : "outline"}
                          size="sm"
                          disabled={!link.url}
                          onClick={() => link.url && router.visit(link.url, { preserveState: true, preserveScroll: true })}
                          dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                      ))}
                    </div>
                  )}
                </>
              ) : (
                <div className="text-center py-20 text-gray-500">
                  <p className="text-xl font-medium">No records found for {formattedMonth}</p>
                  <p className="text-sm mt-3">
                    {search ? "Try a different search term" : "Upload salary data to begin"}
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
