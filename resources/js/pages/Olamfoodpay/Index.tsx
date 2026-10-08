import { Head, router, Link } from "@inertiajs/react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Download, Upload, CheckCircle, XCircle } from "lucide-react";
import AppLayout from "@/layouts/app-layout";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger, DialogFooter } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { useState, useEffect } from "react";
import { useForm } from "@inertiajs/react";
import ErrorBoundary from "@/components/ErrorBoundary";

interface Record {
  staffid: string;
  name: string;
  bank: string | null;
  account: string | null;
  code: string | null;
  branch: string | null;
  basic: number | null;
  gross: number | null;
  netpay: number | null;
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
  { title: "Olam Food Salary Payment", href: "/olamfoodpay" },
];

export default function OlamfoodPay({ records, selectedMonth, formattedMonth, totalRecords, searchCount, flash }: Props) {
  const [isBulkUploadOpen, setIsBulkUploadOpen] = useState(false);
  const [showToast, setShowToast] = useState(false);
  const [toastMessage, setToastMessage] = useState("");
  const [toastType, setToastType] = useState<"success" | "error">("success");
  const [search, setSearch] = useState("");
  const [sort, setSort] = useState("staffid:asc");

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
      route('olamfoodpay.index'),
      { month: data.month, search, sort: sortColumn, order: sortOrder },
      { preserveState: true, preserveScroll: true }
    );
  };

  const handleMonthChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    setData("month", e.target.value);
    const [sortColumn, sortOrder] = sort.split(':');
    router.get(
      route('olamfoodpay.index'),
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
      route('olamfoodpay.index'),
      { month: data.month, search, sort: sortColumn, order: sortOrder },
      { preserveState: true, preserveScroll: true }
    );
  };

  const handleDownloadTemplate = () => {
    const link = document.createElement("a");
    link.href = route("olamfoodpay.download-template");
    link.download = "olamfood_template.xlsx";
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  };

  const handleBulkUpload = (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    if (data.file) {
      post(route("olamfoodpay.upload"), {
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
    if (!confirm("Are you sure you want to delete all records for this month?")) return;
    post(route("olamfoodpay.delete"), {
      onSuccess: () => {
        setSearch("");
        setSort("staffid:asc");
        handleSearchSort();
      },
    });
  };

  const handleExport = async () => {
    try {
      const [col, dir] = sort.split(':');
      const exportUrl = route('olamfoodpay.export', {
        month: data.month,
        search: search || undefined,
        sort: col,
        order: dir,
      });
      window.location.href = exportUrl;
    } catch (err: any) {
      setToastMessage(err.message || 'Export failed');
      setToastType('error');
      setShowToast(true);
    }
  };

  const recordsData = records?.data ?? [];

  return (
    <ErrorBoundary>
      <AppLayout breadcrumbs={breadcrumbs}>
        <Head title="Olam Food Salary Payment" />
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
              <h1 className="text-2xl font-bold">Olam Food Salary Payment</h1>
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
                      <DialogTitle>Upload Olam Food Payroll File</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={handleBulkUpload}>
                      <div className="grid gap-4 py-4">
                        <div className="grid gap-2">
                          <Label htmlFor="file">Payroll Excel File</Label>
                          <Input
                            id="file"
                            type="file"
                            accept=".xlsx,.xls"
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

            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
              <div className="flex items-center gap-2">
                <Label htmlFor="search">Search:</Label>
                <Input
                  id="search"
                  type="text"
                  value={search}
                  onChange={handleSearchChange}
                  onKeyUp={handleSearchSort}
                  placeholder="Search by Staff ID, Name, Bank..."
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
                      <SelectItem value="staffid:asc">Staff ID (Asc)</SelectItem>
                      <SelectItem value="staffid:desc">Staff ID (Desc)</SelectItem>
                      <SelectItem value="name:asc">Name (Asc)</SelectItem>
                      <SelectItem value="name:desc">Name (Desc)</SelectItem>
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
          </div>

          <Card>
            <CardHeader>
              <CardTitle>Employee Payroll for {formattedMonth}</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="mb-4 text-sm text-muted-foreground">
                Showing {recordsData.length} of {searchCount ?? totalRecords ?? 0} records
                {searchCount !== null && ` (filtered from ${totalRecords ?? 0})`}
              </div>

              {recordsData.length > 0 ? (
                <>
                  <div className="block md:hidden space-y-4">
                    {recordsData.map((record, index) => (
                      <div key={`${record.staffid}-${index}`} className="border rounded-lg p-4 bg-background">
                        <div className="flex flex-col gap-2">
                          <div className="font-semibold">{record.name}</div>
                          <div className="text-sm text-muted-foreground">Staff ID: {record.staffid}</div>
                          <div className="text-sm text-muted-foreground">Bank: {record.bank || "N/A"}</div>
                          <div className="text-sm text-muted-foreground">Account: {record.account || "N/A"}</div>
                          <div className="text-sm text-muted-foreground">Branch Code: {record.code || "N/A"}</div>
                          <div className="text-sm text-muted-foreground">Branch: {record.branch || "N/A"}</div>
                          <div className="text-sm text-muted-foreground font-medium">
                            Net Pay: {record.netpay != null ? Number(record.netpay).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : "N/A"}
                          </div>
                        </div>
                      </div>
                    ))}
                  </div>

                  <div className="hidden md:block overflow-x-auto">
                    <Table>
                      <TableHeader>
                        <TableRow>
                          <TableHead>Staff ID</TableHead>
                          <TableHead>Name</TableHead>
                          <TableHead>Bank Name</TableHead>
                          <TableHead>Account No.</TableHead>
                          <TableHead>Branch Code</TableHead>
                          <TableHead>Branch</TableHead>
                          <TableHead className="text-right">Basic</TableHead>
                          <TableHead className="text-right">Gross</TableHead>
                          <TableHead className="text-right">Net Pay</TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {recordsData.map((record, index) => (
                          <TableRow key={`${record.staffid}-${index}`}>
                            <TableCell className="font-medium">{record.staffid}</TableCell>
                            <TableCell>{record.name}</TableCell>
                            <TableCell>{record.bank || "N/A"}</TableCell>
                            <TableCell>{record.account || "N/A"}</TableCell>
                            <TableCell>{record.code || "N/A"}</TableCell>
                            <TableCell>{record.branch || "N/A"}</TableCell>
                            <TableCell className="text-right">
                              {record.basic != null ? Number(record.basic).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : "N/A"}
                            </TableCell>
                            <TableCell className="text-right">
                              {record.gross != null ? Number(record.gross).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : "N/A"}
                            </TableCell>
                            <TableCell className="text-right font-semibold">
                              {record.netpay != null ? Number(record.netpay).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : "N/A"}
                            </TableCell>
                          </TableRow>
                        ))}
                      </TableBody>
                    </Table>
                  </div>
                </>
              ) : (
                <div className="text-center py-10 text-muted-foreground">
                  No records found for {formattedMonth}. Please upload data using the "Upload File" button above.
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
