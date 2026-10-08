import { Head, router, useForm } from "@inertiajs/react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Plus, Pencil, Trash, CheckCircle, XCircle, Download, Upload, ArrowUpDown, FileSpreadsheet, Trash2 } from "lucide-react";
import AppLayout from "@/layouts/app-layout";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger, DialogFooter, DialogDescription } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { useState, useEffect } from "react";
import ErrorBoundary from "@/components/ErrorBoundary";

interface Record {
  staffid: string;
  fullname: string;
  finalnetpay: number | null;
  bankname: string | null;
  accountnum: string | null;
  employname: string | null;
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
  hasBank: number;
  noBank: number;
  searchCount: number | null;
  search?: string;
  sort?: string;
  order?: string;
  flash?: {
    success?: string;
    error?: string;
  };
}

const breadcrumbs = [
  { title: "Transsion BFA", href: "#" },
  { title: "Payroll Summary", href: "/transsion-bfa" },
];

export default function TranssionBfaPayIndex({
  records,
  selectedMonth,
  formattedMonth,
  totalRecords,
  hasBank,
  noBank,
  searchCount,
  search: initialSearch = "",
  sort: initialSort = "fullname",
  order: initialOrder = "asc",
  flash,
}: Props) {
  const [showToast, setShowToast] = useState(false);
  const [toastMessage, setToastMessage] = useState("");
  const [toastType, setToastType] = useState<"success" | "error">("success");
  const [search, setSearch] = useState(initialSearch);
  const [isUploadOpen, setIsUploadOpen] = useState(false);
  const [isDeleteMonthOpen, setIsDeleteMonthOpen] = useState(false);
  const [isBulkDeleteOpen, setIsBulkDeleteOpen] = useState(false);
  const [selectedIds, setSelectedIds] = useState<string[]>([]);

  const { data, setData } = useForm({
    month: selectedMonth,
  });

  const uploadForm = useForm({
    file: null as File | null,
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
    if (showToast) {
      const timeout = toastType === "error" ? 6000 : 3000;
      const timer = setTimeout(() => setShowToast(false), timeout);
      return () => clearTimeout(timer);
    }
  }, [showToast, toastType]);

  const handleMonthChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    setData("month", e.target.value);
    uploadForm.setData("month", e.target.value);
    setSelectedIds([]);
    router.get(
      route("transsion-bfa.pay.index"),
      { month: e.target.value, search },
      { preserveState: false, preserveScroll: true }
    );
  };

  const handleSearch = () => {
    router.get(
      route("transsion-bfa.pay.index"),
      { month: data.month, search },
      { preserveState: true, preserveScroll: true }
    );
  };

  const handleDownloadTemplate = () => {
    window.location.href = route("transsion-bfa.pay.download-template");
  };

  const handleExport = () => {
    window.location.href = route("transsion-bfa.pay.export", { month: data.month });
  };

  const handleUploadSubmit = (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    if (uploadForm.data.file) {
      uploadForm.post(route("transsion-bfa.pay.upload"), {
        forceFormData: true,
        onSuccess: () => {
          setIsUploadOpen(false);
          uploadForm.reset();
        },
        onError: (err) => {
          setToastMessage(Object.values(err).join(" | "));
          setToastType("error");
          setShowToast(true);
        },
      });
    }
  };

  const handleSelectAll = (checked: boolean) => {
    if (checked && records?.data) {
      setSelectedIds(records.data.map((r) => r.staffid));
    } else {
      setSelectedIds([]);
    }
  };

  const handleSelectRow = (staffid: string, checked: boolean) => {
    if (checked) {
      setSelectedIds((prev) => [...prev, staffid]);
    } else {
      setSelectedIds((prev) => prev.filter((id) => id !== staffid));
    }
  };

  const handleDeleteMonth = () => {
    router.delete(route("transsion-bfa.pay.delete"), {
      data: { month: data.month },
      onSuccess: () => {
        setIsDeleteMonthOpen(false);
        setSelectedIds([]);
      },
      onError: (err) => {
        setToastMessage(Object.values(err).join(" | "));
        setToastType("error");
        setShowToast(true);
      },
    });
  };

  const handleBulkDelete = () => {
    router.post(
      route("transsion-bfa.pay.bulk-delete"),
      {
        month: data.month,
        ids: selectedIds,
      },
      {
        onSuccess: () => {
          setIsBulkDeleteOpen(false);
          setSelectedIds([]);
        },
        onError: (err) => {
          setToastMessage(Object.values(err).join(" | "));
          setToastType("error");
          setShowToast(true);
        },
      }
    );
  };

  const allOnPageSelected =
    records?.data && records.data.length > 0 && records.data.every((r) => selectedIds.includes(r.staffid));

  return (
    <ErrorBoundary>
      <AppLayout breadcrumbs={breadcrumbs}>
        <Head title="Transsion BFA Payroll Summary" />

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
              <h1 className="text-2xl font-bold">Transsion BFA Payroll Summary</h1>
              <p className="text-sm text-muted-foreground mt-1">
                Burkina Faso staff payroll and bank allocation
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

              <Button variant="outline" onClick={handleDownloadTemplate} className="gap-1">
                <FileSpreadsheet className="h-4 w-4" />
                Template
              </Button>

              <Dialog open={isUploadOpen} onOpenChange={setIsUploadOpen}>
                <DialogTrigger asChild>
                  <Button className="gap-1 bg-green-600 hover:bg-green-700">
                    <Upload className="h-4 w-4" />
                    Upload Payroll
                  </Button>
                </DialogTrigger>
                <DialogContent>
                  <DialogHeader>
                    <DialogTitle>Upload Transsion BFA Payroll</DialogTitle>
                  </DialogHeader>
                  <form onSubmit={handleUploadSubmit} className="space-y-4">
                    <div>
                      <Label htmlFor="upload-file">Excel File (.xlsx)</Label>
                      <Input
                        id="upload-file"
                        type="file"
                        accept=".xlsx,.xls"
                        onChange={(e) => {
                          const file = e.target.files?.[0];
                          if (file) uploadForm.setData("file", file);
                        }}
                        required
                        className="mt-1"
                      />
                    </div>
                    <DialogFooter>
                      <Button type="button" variant="outline" onClick={() => setIsUploadOpen(false)}>
                        Cancel
                      </Button>
                      <Button type="submit" disabled={uploadForm.processing || !uploadForm.data.file}>
                        Upload
                      </Button>
                    </DialogFooter>
                  </form>
                </DialogContent>
              </Dialog>

              <Button variant="outline" onClick={handleExport} className="gap-1 bg-blue-50 text-blue-700 border-blue-200 hover:bg-blue-100">
                <Download className="h-4 w-4" />
                Export
              </Button>

              {totalRecords > 0 && (
                <Dialog open={isDeleteMonthOpen} onOpenChange={setIsDeleteMonthOpen}>
                  <DialogTrigger asChild>
                    <Button variant="destructive" className="gap-1">
                      <Trash2 className="h-4 w-4" />
                      Clear Month Data
                    </Button>
                  </DialogTrigger>
                  <DialogContent>
                    <DialogHeader>
                      <DialogTitle>Delete All Records for {formattedMonth}?</DialogTitle>
                      <DialogDescription>
                        This will permanently delete all {totalRecords} payroll records uploaded for {formattedMonth}. This action cannot be undone.
                      </DialogDescription>
                    </DialogHeader>
                    <DialogFooter className="gap-2 sm:gap-0">
                      <Button variant="outline" onClick={() => setIsDeleteMonthOpen(false)}>
                        Cancel
                      </Button>
                      <Button variant="destructive" onClick={handleDeleteMonth}>
                        Yes, Delete All
                      </Button>
                    </DialogFooter>
                  </DialogContent>
                </Dialog>
              )}
            </div>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <Card>
              <CardContent className="p-4">
                <div className="text-sm text-muted-foreground">Total Staff</div>
                <div className="text-2xl font-bold mt-1">{totalRecords}</div>
              </CardContent>
            </Card>
            <Card>
              <CardContent className="p-4">
                <div className="text-sm text-emerald-600 font-medium">With Bank Details</div>
                <div className="text-2xl font-bold mt-1 text-emerald-700">{hasBank}</div>
              </CardContent>
            </Card>
            <Card>
              <CardContent className="p-4">
                <div className="text-sm text-amber-600 font-medium">Missing Bank Details</div>
                <div className="text-2xl font-bold mt-1 text-amber-700">{noBank}</div>
              </CardContent>
            </Card>
          </div>

          <Card>
            <CardHeader className="pb-3">
              <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                  <CardTitle className="flex items-center gap-2">
                    Payroll — {formattedMonth}
                    {selectedIds.length > 0 && (
                      <span className="text-xs font-normal text-muted-foreground">
                        ({selectedIds.length} selected)
                      </span>
                    )}
                  </CardTitle>
                  <p className="text-xs text-muted-foreground mt-1">
                    {searchCount !== null
                      ? `Showing ${searchCount} of ${totalRecords} matching records`
                      : `Total Records: ${totalRecords}`}
                  </p>
                </div>

                <div className="flex items-center gap-2">
                  {selectedIds.length > 0 && (
                    <Dialog open={isBulkDeleteOpen} onOpenChange={setIsBulkDeleteOpen}>
                      <DialogTrigger asChild>
                        <Button variant="destructive" size="sm" className="gap-1">
                          <Trash className="h-4 w-4" />
                          Delete Selected ({selectedIds.length})
                        </Button>
                      </DialogTrigger>
                      <DialogContent>
                        <DialogHeader>
                          <DialogTitle>Delete Selected Records?</DialogTitle>
                          <DialogDescription>
                            Are you sure you want to delete {selectedIds.length} selected record(s) from {formattedMonth}? This action cannot be undone.
                          </DialogDescription>
                        </DialogHeader>
                        <DialogFooter className="gap-2 sm:gap-0">
                          <Button variant="outline" onClick={() => setIsBulkDeleteOpen(false)}>
                            Cancel
                          </Button>
                          <Button variant="destructive" onClick={handleBulkDelete}>
                            Delete {selectedIds.length} Record(s)
                          </Button>
                        </DialogFooter>
                      </DialogContent>
                    </Dialog>
                  )}

                  <Input
                    placeholder="Search by ID, name, bank, account..."
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    onKeyDown={(e) => e.key === "Enter" && handleSearch()}
                    className="w-full sm:w-64"
                  />
                  <Button size="sm" onClick={handleSearch}>
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
                          <TableHead className="w-10 text-center">
                            <input
                              type="checkbox"
                              checked={!!allOnPageSelected}
                              onChange={(e) => handleSelectAll(e.target.checked)}
                              className="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                              title="Select All on Page"
                            />
                          </TableHead>
                          <TableHead className="w-12">No.</TableHead>
                          <TableHead>Staff ID</TableHead>
                          <TableHead>Full Name</TableHead>
                          <TableHead>Bank Name</TableHead>
                          <TableHead>Account Number</TableHead>
                          <TableHead className="text-right">Net Pay</TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {records.data.map((r, idx) => (
                          <TableRow key={r.staffid + idx} className={selectedIds.includes(r.staffid) ? "bg-muted/50" : ""}>
                            <TableCell className="text-center">
                              <input
                                type="checkbox"
                                checked={selectedIds.includes(r.staffid)}
                                onChange={(e) => handleSelectRow(r.staffid, e.target.checked)}
                                className="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                              />
                            </TableCell>
                            <TableCell>{(records.from ?? 1) + idx}</TableCell>
                            <TableCell className="font-mono font-medium">{r.staffid}</TableCell>
                            <TableCell>{r.fullname}</TableCell>
                            <TableCell>{r.bankname || <span className="text-amber-600 font-semibold">No Bank</span>}</TableCell>
                            <TableCell className="font-mono text-xs">{r.accountnum || "—"}</TableCell>
                            <TableCell className="text-right font-mono font-semibold">
                              {r.finalnetpay !== null ? Number(r.finalnetpay).toLocaleString() : "—"}
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
                  <h3 className="mt-2 text-sm font-semibold text-gray-900">No payroll records found</h3>
                  <p className="mt-1 text-sm text-gray-500">
                    No payroll data recorded for Transsion BFA in {formattedMonth}. Click "Upload Payroll" to import records.
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
