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

interface PayrollRecord {
  staffid: string;
  name: string;
  shop: string | null;
  department: string | null;
  bank: string | null;
  account: string | null;
  code: string | null;
  branch: string | null;
  netpay: number | null;
}

interface Props {
  records: {
    data: PayrollRecord[];
    current_page: number;
    last_page: number;
    total: number;
    links: Array<{ url: string | null; label: string; active: boolean }>;
  };
  selectedMonth: string;
  formattedMonth: string;
  totalRecords: number;
  searchCount: number | null;
  departments: Record<string, string>;
  selectedDepartment: string;
  availableDepartments: string[];
  flash?: {
    success?: string;
    error?: string;
  };
}

const breadcrumbs = [
  { title: "Pz", href: "/pzpay" },
];

export default function PzPay({
  records,
  selectedMonth,
  formattedMonth,
  totalRecords,
  searchCount,
  departments = {},
  selectedDepartment = "",
  availableDepartments = [],
  flash,
}: Props) {
  const [isBulkUploadOpen, setIsBulkUploadOpen] = useState(false);
  const [showToast, setShowToast] = useState(false);
  const [toastMessage, setToastMessage] = useState("");
  const [toastType, setToastType] = useState<"success" | "error">("success");
  const [search, setSearch] = useState("");
  const [department, setDepartment] = useState(selectedDepartment || "");
  const [sort, setSort] = useState("staffid:asc");

  const { data, setData, post, processing, errors, reset } = useForm({
    month: selectedMonth,
    department: "" as string,
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

  /* ------------------------------------------------------------------
   * Build the query string for every navigation call, preserving the
   * current filter/sort/search state. `overrides` replaces base values;
   * set a value to undefined to remove it.
   * ---------------------------------------------------------------- */
  const buildQuery = (overrides: Record<string, string | undefined> = {}) => {
    const [col, dir] = sort.split(":");
    const q: Record<string, string | undefined> = {
      month: data.month,
      sort: col,
      order: dir,
      search: search || undefined,
      department: department || undefined,
      ...overrides,
    };
    Object.keys(q).forEach((k) => q[k] === undefined && delete q[k]);
    return q;
  };

  const handleSearchSort = () => {
    router.get(route("pzpay.index"), buildQuery(), {
      preserveState: true,
      preserveScroll: true,
    });
  };

  const handleMonthChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const newMonth = e.target.value;
    setData("month", newMonth);
    router.get(route("pzpay.index"), buildQuery({ month: newMonth }), {
      preserveState: true,
      preserveScroll: true,
    });
  };

  const handleSearchChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    setSearch(e.target.value);
  };

  const handleDepartmentChange = (value: string) => {
    const newValue = value === "__all__" ? "" : value;
    setDepartment(newValue);
    router.get(
      route("pzpay.index"),
      buildQuery({ department: newValue || undefined }),
      { preserveState: true, preserveScroll: true }
    );
  };

  const handleSortChange = (value: string) => {
    setSort(value);
    const [col, dir] = value.split(":");
    router.get(route("pzpay.index"), buildQuery({ sort: col, order: dir }), {
      preserveState: true,
      preserveScroll: true,
    });
  };

  const handleDownloadTemplate = () => {
    if (!data.department) {
      setToastMessage("Please select a department first before downloading the template.");
      setToastType("error");
      setShowToast(true);
      return;
    }
    const link = document.createElement("a");
    link.href = route("pzpay.download-template", { department: data.department });
    link.download = `pz_template_${data.department}_${data.month}.xlsx`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  };

  const handleBulkUpload = (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    if (!data.department) {
      setToastMessage("Please select a department before uploading.");
      setToastType("error");
      setShowToast(true);
      return;
    }
    if (!data.file) {
      setToastMessage("Please choose a file to upload.");
      setToastType("error");
      setShowToast(true);
      return;
    }
    post(route("pzpay.upload"), {
      forceFormData: true,
      preserveState: true,
      onSuccess: () => {
        setIsBulkUploadOpen(false);
        reset("department", "file");
      },
    });
  };

  const handleDelete = () => {
    router.post(route("pzpay.delete"), { month: data.month }, {
      onSuccess: () => {
        setSearch("");
        setDepartment("");
        setSort("staffid:asc");
        router.get(route("pzpay.index"), { month: data.month }, {
          preserveState: true,
          preserveScroll: true,
        });
      },
    });
  };

  const handleExport = async () => {
    try {
      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute("content");
      if (!csrfToken) throw new Error("CSRF token missing");

      const [col, dir] = sort.split(":");

      const response = await fetch(
        route("pzpay.export", {
          month: data.month,
          search: search || undefined,
          sort: col,
          order: dir,
          department: department || undefined,
        }),
        {
          method: "GET",
          headers: {
            Accept: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
            "X-CSRF-TOKEN": csrfToken,
          },
        }
      );

      if (!response.ok) {
        const text = await response.text();
        let msg = "Export failed";
        try {
          const json = JSON.parse(text);
          msg = json.error || msg;
        } catch {}
        throw new Error(msg);
      }

      const blob = await response.blob();
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement("a");
      a.href = url;
      a.download = `pz_pay_${data.month}${department ? "_" + department : ""}.xlsx`;
      a.click();
      window.URL.revokeObjectURL(url);

      setToastMessage("Excel exported successfully!");
      setToastType("success");
      setShowToast(true);
    } catch (err: any) {
      setToastMessage(err.message || "Export failed");
      setToastType("error");
      setShowToast(true);
    }
  };

  return (
    <ErrorBoundary>
      <AppLayout breadcrumbs={breadcrumbs}>
        <Head title="Pz" />
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
              <h1 className="text-2xl font-bold">Pz</h1>
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

                <Dialog
                  open={isBulkUploadOpen}
                  onOpenChange={(open) => {
                    setIsBulkUploadOpen(open);
                    if (!open) reset("department", "file");
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
                      <DialogTitle>Upload Pz Data</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={handleBulkUpload}>
                      <div className="grid gap-4 py-4">
                        <div className="grid gap-2">
                          <Label htmlFor="department">
                            Department <span className="text-red-500">*</span>
                          </Label>
                          <Select
                            value={data.department}
                            onValueChange={(value) => setData("department", value)}
                          >
                            <SelectTrigger id="department">
                              <SelectValue placeholder="Select department…" />
                            </SelectTrigger>
                            <SelectContent>
                              {Object.entries(departments).map(([value, label]) => (
                                <SelectItem key={value} value={value}>
                                  {label}
                                </SelectItem>
                              ))}
                            </SelectContent>
                          </Select>
                          {errors.department && (
                            <p className="text-red-500 text-sm">{errors.department}</p>
                          )}
                          <p className="text-xs text-muted-foreground">
                            Each department has its own column layout. Pick the right one
                            so the file is read correctly.
                          </p>
                        </div>

                        <Button
                          type="button"
                          variant="outline"
                          onClick={handleDownloadTemplate}
                          disabled={!data.department}
                          className="w-full"
                        >
                          <Download className="h-4 w-4 mr-2" />
                          Download Template for Selected Department
                        </Button>

                        <div className="grid gap-2">
                          <Label htmlFor="file">
                            Upload Excel File <span className="text-red-500">*</span>
                          </Label>
                          <Input
                            id="file"
                            type="file"
                            accept=".xlsx,.xls,.csv,.ods"
                            onChange={(e) => {
                              const file = e.target.files?.[0];
                              if (file) setData("file", file);
                            }}
                          />
                          {errors.file && (
                            <p className="text-red-500 text-sm">{errors.file}</p>
                          )}
                        </div>
                      </div>

                      <DialogFooter className="flex flex-col sm:flex-row sm:justify-end gap-2">
                        <Button
                          type="submit"
                          disabled={processing || !data.file || !data.department}
                        >
                          {processing ? "Uploading…" : "Upload"}
                        </Button>
                      </DialogFooter>
                    </form>
                  </DialogContent>
                </Dialog>
              </div>
            </div>

            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-end gap-4 flex-wrap">
              {/* ── Department filter ─────────────────────────────── */}
              {availableDepartments.length > 0 && (
                <div className="flex items-center gap-2">
                  <Label htmlFor="department-filter">Department:</Label>
                  <Select
                    value={department || "__all__"}
                    onValueChange={handleDepartmentChange}
                  >
                    <SelectTrigger id="department-filter" className="w-full sm:w-52">
                      <SelectValue placeholder="All Departments" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="__all__">All Departments</SelectItem>
                      {availableDepartments.map((deptKey) => (
                        <SelectItem key={deptKey} value={deptKey}>
                          {departments[deptKey] ?? deptKey}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
              )}

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
                    <SelectItem value="staffid:asc">Staff ID (Asc)</SelectItem>
                    <SelectItem value="staffid:desc">Staff ID (Desc)</SelectItem>
                    <SelectItem value="name:asc">Name (Asc)</SelectItem>
                    <SelectItem value="name:desc">Name (Desc)</SelectItem>
                    <SelectItem value="department:asc">Payroll Dept (Asc)</SelectItem>
                    <SelectItem value="department:desc">Payroll Dept (Desc)</SelectItem>
                    <SelectItem value="shop:asc">Department/Shop (Asc)</SelectItem>
                    <SelectItem value="shop:desc">Department/Shop (Desc)</SelectItem>
                    <SelectItem value="bank:asc">Bank Name (Asc)</SelectItem>
                    <SelectItem value="bank:desc">Bank Name (Desc)</SelectItem>
                  </SelectContent>
                </Select>
              </div>

              <Button
                variant="destructive"
                onClick={handleDelete}
                disabled={processing}
                className="w-full sm:w-auto"
              >
                Delete Records
              </Button>
              <Button
                onClick={handleExport}
                className="w-full sm:w-auto bg-purple-500 hover:bg-purple-600"
              >
                <Download className="h-4 w-4 mr-2" />
                Export to Excel
              </Button>
            </div>
          </div>

          <Card>
            <CardHeader>
              <CardTitle>Pz Salary Summary for {formattedMonth}</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="mb-4">
                Showing {records.data.length} of {searchCount ?? totalRecords} records
                {searchCount !== null && ` (filtered from ${totalRecords})`}
              </div>

              {records.data.length > 0 ? (
                <div className="block md:hidden space-y-4">
                  {records.data.map((record, index) => (
                    <div
                      key={`${record.staffid}-${index}`}
                      className="border rounded-lg p-4 bg-background"
                    >
                      <div className="flex flex-col gap-2">
                        <div className="font-semibold">{record.name}</div>
                        <div className="text-sm text-muted-foreground">
                          Staff ID: {record.staffid}
                        </div>
                        <div className="text-sm text-muted-foreground">
                          Payroll Dept:{" "}
                          {record.department
                            ? departments[record.department] ?? record.department
                            : ""}
                        </div>
                        <div className="text-sm text-muted-foreground">
                          Department/Shop: {record.shop || ""}
                        </div>
                        <div className="text-sm text-muted-foreground">
                          Bank: {record.bank || ""}
                        </div>
                        <div className="text-sm text-muted-foreground">
                          Account: {record.account || ""}
                        </div>
                        <div className="text-sm text-muted-foreground">
                          Branch Code: {record.code || ""}
                        </div>
                        <div className="text-sm text-muted-foreground">
                          Branch: {record.branch || ""}
                        </div>
                        <div className="text-sm text-muted-foreground">
                          Net Pay: {record.netpay != null ? record.netpay : ""}
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
              ) : (
                <p className="text-center text-gray-500">
                  No records found for the selected month.
                </p>
              )}

              {records.data.length > 0 && (
                <div className="hidden md:block">
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>
                          <Button
                            variant="ghost"
                            onClick={() =>
                              handleSortChange(
                                `staffid:${sort === "staffid:asc" ? "desc" : "asc"}`
                              )
                            }
                          >
                            Staff ID{" "}
                            {sort === "staffid:asc"
                              ? "↑"
                              : sort === "staffid:desc"
                              ? "↓"
                              : ""}
                          </Button>
                        </TableHead>
                        <TableHead>
                          <Button
                            variant="ghost"
                            onClick={() =>
                              handleSortChange(
                                `name:${sort === "name:asc" ? "desc" : "asc"}`
                              )
                            }
                          >
                            Name{" "}
                            {sort === "name:asc"
                              ? "↑"
                              : sort === "name:desc"
                              ? "↓"
                              : ""}
                          </Button>
                        </TableHead>
                        <TableHead>
                          <Button
                            variant="ghost"
                            onClick={() =>
                              handleSortChange(
                                `department:${sort === "department:asc" ? "desc" : "asc"}`
                              )
                            }
                          >
                            Payroll Dept{" "}
                            {sort === "department:asc"
                              ? "↑"
                              : sort === "department:desc"
                              ? "↓"
                              : ""}
                          </Button>
                        </TableHead>
                        <TableHead>
                          <Button
                            variant="ghost"
                            onClick={() =>
                              handleSortChange(
                                `shop:${sort === "shop:asc" ? "desc" : "asc"}`
                              )
                            }
                          >
                            Dept/Shop{" "}
                            {sort === "shop:asc"
                              ? "↑"
                              : sort === "shop:desc"
                              ? "↓"
                              : ""}
                          </Button>
                        </TableHead>
                        <TableHead>
                          <Button
                            variant="ghost"
                            onClick={() =>
                              handleSortChange(
                                `bank:${sort === "bank:asc" ? "desc" : "asc"}`
                              )
                            }
                          >
                            Bank Name{" "}
                            {sort === "bank:asc"
                              ? "↑"
                              : sort === "bank:desc"
                              ? "↓"
                              : ""}
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
                        <TableRow key={`${record.staffid}-${index}`}>
                          <TableCell>{record.staffid}</TableCell>
                          <TableCell>{record.name}</TableCell>
                          <TableCell>
                            {record.department
                              ? departments[record.department] ?? record.department
                              : ""}
                          </TableCell>
                          <TableCell>{record.shop || ""}</TableCell>
                          <TableCell>{record.bank || ""}</TableCell>
                          <TableCell>{record.account || ""}</TableCell>
                          <TableCell>{record.code || ""}</TableCell>
                          <TableCell>{record.branch || ""}</TableCell>
                          <TableCell>
                            {record.netpay != null ? record.netpay : ""}
                          </TableCell>
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
                            data={{
                              month: data.month,
                              sort: sort.split(":")[0],
                              order: sort.split(":")[1],
                              ...(search ? { search } : {}),
                              ...(department ? { department } : {}),
                            }}
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