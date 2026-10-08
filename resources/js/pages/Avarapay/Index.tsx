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

interface Record {
  STAFFID: string;
  NAME: string;
  SHOP: string | null;
  BANK_BD: string | null;
  ACCOUNT: string | null;
  CODE: string | null;
  BRANCH_BD: string | null;
  TAKE_HOME_PAY_GHC: number | null;
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
  { title: "Salary Payment", href: "#" },
  { title: "Avara", href: "/avarapay" },
];

export default function AvaraPay({ records, selectedMonth, formattedMonth, totalRecords, searchCount, flash }: Props) {
  const [isBulkUploadOpen, setIsBulkUploadOpen] = useState(false);
  const [showToast, setShowToast] = useState(false);
  const [toastMessage, setToastMessage] = useState("");
  const [toastType, setToastType] = useState<"success" | "error">("success");
  const [search, setSearch] = useState("");
  const [sort, setSort] = useState("STAFFID:asc");

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
      route('avarapay.index'),
      { month: data.month, search, sort: sortColumn, order: sortOrder },
      { preserveState: true, preserveScroll: true }
    );
  };

  const handleMonthChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    setData("month", e.target.value);
    const [sortColumn, sortOrder] = sort.split(':');
    router.get(
      route('avarapay.index'),
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
      route('avarapay.index'),
      { month: data.month, search, sort: sortColumn, order: sortOrder },
      { preserveState: true, preserveScroll: true }
    );
  };

  const handleDownloadTemplate = () => {
    const link = document.createElement("a");
    link.href = route("avarapay.download-template");
    link.download = `avara_payroll_template.xlsx`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  };

  const handleBulkUpload = (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    if (data.file) {
      post(route("avarapay.upload"), {
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
    if (confirm(`Are you sure you want to delete all Avara payroll records for ${formattedMonth}?`)) {
      post(route("avarapay.delete"), {
        data: { month: data.month },
        onSuccess: () => {
          setSearch("");
          setSort("STAFFID:asc");
          handleSearchSort();
        },
      });
    }
  };

  const handleExport = async () => {
    try {
      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
      if (!csrfToken) throw new Error('CSRF token missing');

      const [col, dir] = sort.split(':');

      const response = await fetch(route('avarapay.export', {
        month: data.month,
        search: search || undefined,
        sort: col,
        order: dir,
      }), {
        method: 'GET',
        headers: {
          'Accept': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
          'X-CSRF-TOKEN': csrfToken,
        },
      });

      if (!response.ok) throw new Error('Export failed');

      const blob = await response.blob();
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `avara_pay_${data.month}.xlsx`;
      a.click();
      window.URL.revokeObjectURL(url);

      setToastMessage('Export successful!');
      setToastType('success');
      setShowToast(true);
    } catch (err) {
      setToastMessage('Export failed');
      setToastType('error');
      setShowToast(true);
    }
  };

  return (
    <ErrorBoundary>
      <AppLayout breadcrumbs={breadcrumbs}>
        <Head title="Avara Salary Payment" />
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
              <h1 className="text-2xl font-bold">Avara RC Limited - Salary Payment</h1>
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
                      <DialogTitle>Upload Avara Payroll Data</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={handleBulkUpload}>
                      <div className="grid gap-4 py-4">
                        <div className="grid gap-2">
                          <Label htmlFor="file">Upload Excel File</Label>
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
                    <SelectItem value="STAFFID:asc">Staff ID (Asc)</SelectItem>
                    <SelectItem value="STAFFID:desc">Staff ID (Desc)</SelectItem>
                    <SelectItem value="NAME:asc">Name (Asc)</SelectItem>
                    <SelectItem value="NAME:desc">Name (Desc)</SelectItem>
                    <SelectItem value="SHOP:asc">Shop/Dept (Asc)</SelectItem>
                    <SelectItem value="SHOP:desc">Shop/Dept (Desc)</SelectItem>
                    <SelectItem value="BANK:asc">Bank Name (Asc)</SelectItem>
                    <SelectItem value="BANK:desc">Bank Name (Desc)</SelectItem>
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
              <CardTitle>Avara Salary Summary for {formattedMonth}</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="mb-4">
                Showing {records.data.length} of {searchCount ?? totalRecords} records
                {searchCount !== null && ` (filtered from ${totalRecords})`}
              </div>
              {records.data.length > 0 ? (
                <div className="block md:hidden space-y-4">
                  {records.data.map((record, index) => (
                    <div key={`${record.STAFFID}-${index}`} className="border rounded-lg p-4 bg-background">
                      <div className="flex flex-col gap-2">
                        <div className="font-semibold">{record.NAME}</div>
                        <div className="text-sm text-muted-foreground">Staff ID: {record.STAFFID}</div>
                        <div className="text-sm text-muted-foreground">Department: {record.SHOP || "Avara"}</div>
                        <div className="text-sm text-muted-foreground">Bank: {record.BANK_BD || ""}</div>
                        <div className="text-sm text-muted-foreground">Account: {record.ACCOUNT || ""}</div>
                        <div className="text-sm text-muted-foreground">Branch Code: {record.CODE || ""}</div>
                        <div className="text-sm text-muted-foreground">Branch: {record.BRANCH_BD || ""}</div>
                        <div className="text-sm text-muted-foreground">Take Home Pay: {record.TAKE_HOME_PAY_GHC != null ? record.TAKE_HOME_PAY_GHC : ""}</div>
                      </div>
                    </div>
                  ))}
                </div>
              ) : (
                <p className="text-center text-gray-500">No records found for the selected month.</p>
              )}
              {records.data.length > 0 && (
                <div className="hidden md:block overflow-x-auto">
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>
                          <Button variant="ghost" onClick={() => handleSortChange(`STAFFID:${sort === "STAFFID:asc" ? "desc" : "asc"}`)}>
                            Staff ID {sort === "STAFFID:asc" ? "↑" : sort === "STAFFID:desc" ? "↓" : ""}
                          </Button>
                        </TableHead>
                        <TableHead>
                          <Button variant="ghost" onClick={() => handleSortChange(`NAME:${sort === "NAME:asc" ? "desc" : "asc"}`)}>
                            Name {sort === "NAME:asc" ? "↑" : sort === "NAME:desc" ? "↓" : ""}
                          </Button>
                        </TableHead>
                        <TableHead>
                          <Button variant="ghost" onClick={() => handleSortChange(`SHOP:${sort === "SHOP:asc" ? "desc" : "asc"}`)}>
                            Department {sort === "SHOP:asc" ? "↑" : sort === "SHOP:desc" ? "↓" : ""}
                          </Button>
                        </TableHead>
                        <TableHead>
                          <Button variant="ghost" onClick={() => handleSortChange(`BANK:${sort === "BANK:asc" ? "desc" : "asc"}`)}>
                            Bank Name {sort === "BANK:asc" ? "↑" : sort === "BANK:desc" ? "↓" : ""}
                          </Button>
                        </TableHead>
                        <TableHead>Account</TableHead>
                        <TableHead>Branch Code</TableHead>
                        <TableHead>Branch</TableHead>
                        <TableHead>Net Pay (GHC)</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {records.data.map((record, index) => (
                        <TableRow key={`${record.STAFFID}-${index}`}>
                          <TableCell>{record.STAFFID}</TableCell>
                          <TableCell>{record.NAME}</TableCell>
                          <TableCell>{record.SHOP || "Avara"}</TableCell>
                          <TableCell>{record.BANK_BD || ""}</TableCell>
                          <TableCell>{record.ACCOUNT || ""}</TableCell>
                          <TableCell>{record.CODE || ""}</TableCell>
                          <TableCell>{record.BRANCH_BD || ""}</TableCell>
                          <TableCell>{record.TAKE_HOME_PAY_GHC != null ? record.TAKE_HOME_PAY_GHC : ""}</TableCell>
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
