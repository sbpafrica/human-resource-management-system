import { Head, router, Link } from "@inertiajs/react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Download, Upload, Trash2, CheckCircle, XCircle, Search, FileSpreadsheet, DollarSign, Clock, Users, Building2 } from "lucide-react";
import AppLayout from "@/layouts/app-layout";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter, DialogDescription } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { useState, useEffect } from "react";
import { useForm } from "@inertiajs/react";
import ErrorBoundary from "@/components/ErrorBoundary";

interface Record {
  sbpstaffid: string;
  clock_in_id: string;
  name: string;
  designation: string | null;
  department: string | null;
  startdate: string | null;
  expected_days: number | null;
  actual_days: number | null;
  basic_salary: number | null;
  absent_deduction: number | null;
  snnit_13: number | null;
  eoy: number | null;
  wmk_2: number | null;
  ppe: number | null;
  medical_6: number | null;
  refund: number | null;
  staff_cost: number | null;
  mgt_fee: number | null;
  nhil_getfund_5: number | null;
  covid_1: number | null;
  vat_15: number | null;
  ctc: number | null;
  taxable_income: number | null;
  paye: number | null;
  take_home: number | null;
  basic_salaryover: number | null;
  weekday_hours: number | null;
  num_saturdays: number | null;
  saturday_hours: number | null;
  num_sundays_holidays: number | null;
  sundays_holiday_overtime: number | null;
  overtimerefund: number | null;
  weekdays_overtime: number | null;
  saturdays_overtime: number | null;
  sundays_holidays_overtime: number | null;
  total_overtime: number | null;
}

interface Summary {
  totalBasic: number;
  totalOvertime: number;
  totalTakeHome: number;
  totalCtc: number;
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
  hasData: boolean;
  summary: Summary;
  flash?: {
    success?: string;
    error?: string;
  };
}

const breadcrumbs = [
  { title: "Database", href: "#" },
  { title: "Twillium Accra 2", href: "/twelliumaccra2" },
];

export default function TwelliumAccra2Index({
  records,
  selectedMonth,
  formattedMonth,
  totalRecords,
  searchCount,
  hasData,
  summary,
  flash,
}: Props) {
  const [isUploadOpen, setIsUploadOpen] = useState(false);
  const [isDeleteOpen, setIsDeleteOpen] = useState(false);
  const [showToast, setShowToast] = useState(false);
  const [toastMessage, setToastMessage] = useState("");
  const [toastType, setToastType] = useState<"success" | "error">("success");
  const [search, setSearch] = useState("");
  const [sort, setSort] = useState("name:asc");

  const uploadForm = useForm({
    month: selectedMonth,
    basic_file: null as File | null,
    overtime_file: null as File | null,
  });

  const deleteForm = useForm({
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
      const timeout = toastType === "error" ? 8000 : 4000;
      const timer = setTimeout(() => setShowToast(false), timeout);
      return () => clearTimeout(timer);
    }
  }, [showToast, toastType]);

  const handleMonthChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const newMonth = e.target.value;
    uploadForm.setData("month", newMonth);
    deleteForm.setData("month", newMonth);
    const [sortCol, sortDir] = sort.split(":");
    router.get(
      route("twelliumaccra2.index"),
      { month: newMonth, search, sort: sortCol, order: sortDir },
      { preserveState: true, preserveScroll: true }
    );
  };

  const handleSearchSort = () => {
    const [sortCol, sortDir] = sort.split(":");
    router.get(
      route("twelliumaccra2.index"),
      { month: selectedMonth, search, sort: sortCol, order: sortDir },
      { preserveState: true, preserveScroll: true }
    );
  };

  const handleSortChange = (value: string) => {
    setSort(value);
    const [sortCol, sortDir] = value.split(":");
    router.get(
      route("twelliumaccra2.index"),
      { month: selectedMonth, search, sort: sortCol, order: sortDir },
      { preserveState: true, preserveScroll: true }
    );
  };

  const handleUploadSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    uploadForm.post(route("twelliumaccra2.upload"), {
      onSuccess: () => {
        setIsUploadOpen(false);
        uploadForm.reset("basic_file", "overtime_file");
      },
    });
  };

  const handleDeleteSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    deleteForm.post(route("twelliumaccra2.delete-month"), {
      onSuccess: () => {
        setIsDeleteOpen(false);
      },
    });
  };

  const handleExport = () => {
    window.location.href = route("twelliumaccra2.export", { month: selectedMonth });
  };

  const recordsData = records?.data || [];

  return (
    <ErrorBoundary>
      <AppLayout breadcrumbs={breadcrumbs}>
        <Head title="Twillium Accra 2 — Payroll & Overtime" />

        <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
          {/* Toast Notification */}
          {showToast && (
            <div
              className={`fixed top-4 right-4 z-50 flex items-center gap-2 rounded-lg p-4 text-white shadow-xl max-w-md ${
                toastType === "success" ? "bg-emerald-600" : "bg-red-600"
              } animate-in fade-in slide-in-from-top-4`}
            >
              {toastType === "success" ? <CheckCircle className="h-5 w-5 shrink-0" /> : <XCircle className="h-5 w-5 shrink-0" />}
              <span className="text-sm font-medium">{toastMessage}</span>
              <button onClick={() => setShowToast(false)} className="ml-auto font-bold opacity-80 hover:opacity-100">
                ×
              </button>
            </div>
          )}

          {/* Header Action Bar */}
          <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
              <h1 className="flex items-center gap-2 text-2xl font-bold tracking-tight text-gray-900 dark:text-gray-100">
                <Building2 className="h-6 w-6 text-primary" />
                Twillium Accra 2
              </h1>
              <p className="text-sm text-gray-500 dark:text-gray-400">
                Process monthly basic salary, overtime schedules, and departmental payroll summaries for Twellium Accra.
              </p>
            </div>

            <div className="flex flex-wrap items-center gap-2.5">
              <div className="flex items-center gap-2 bg-white dark:bg-gray-800 border rounded-md px-3 py-1.5 shadow-sm">
                <Label htmlFor="month" className="text-xs font-semibold uppercase text-gray-500">
                  Month:
                </Label>
                <Input
                  id="month"
                  type="month"
                  value={selectedMonth}
                  onChange={handleMonthChange}
                  className="h-8 w-36 border-none p-0 focus-visible:ring-0 text-sm font-medium"
                />
              </div>

              <Button onClick={() => setIsUploadOpen(true)} className="bg-emerald-600 hover:bg-emerald-700 text-white">
                <Upload className="mr-2 h-4 w-4" />
                Upload Files
              </Button>

              <div className="flex gap-1.5">
                <Button
                  variant="outline"
                  size="sm"
                  asChild
                  title="Download Basic Template"
                  className="text-xs"
                >
                  <a href={route("twelliumaccra2.download-template", { type: "basic" })}>
                    <FileSpreadsheet className="mr-1.5 h-3.5 w-3.5 text-blue-600" />
                    Basic Template
                  </a>
                </Button>

                <Button
                  variant="outline"
                  size="sm"
                  asChild
                  title="Download Overtime Template"
                  className="text-xs"
                >
                  <a href={route("twelliumaccra2.download-template", { type: "overtime" })}>
                    <Clock className="mr-1.5 h-3.5 w-3.5 text-orange-600" />
                    Overtime Template
                  </a>
                </Button>
              </div>

              {hasData && (
                <>
                  <Button onClick={handleExport} variant="outline" className="text-primary hover:bg-primary/10">
                    <Download className="mr-2 h-4 w-4" />
                    Export Excel
                  </Button>

                  <Button onClick={() => setIsDeleteOpen(true)} variant="destructive" size="icon" title="Delete this month's data">
                    <Trash2 className="h-4 w-4" />
                  </Button>
                </>
              )}
            </div>
          </div>

          {/* Metric Summary Cards */}
          {hasData && (
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
              <Card className="shadow-sm border-l-4 border-l-blue-500">
                <CardHeader className="flex flex-row items-center justify-between pb-2">
                  <CardTitle className="text-xs font-semibold text-gray-500 uppercase tracking-wider">Total Basic Salary</CardTitle>
                  <DollarSign className="h-4 w-4 text-blue-500" />
                </CardHeader>
                <CardContent>
                  <div className="text-xl font-bold text-gray-900 dark:text-gray-100">
                    GHC {summary.totalBasic.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                  </div>
                  <p className="text-xs text-gray-500 mt-1">{totalRecords} total active employees</p>
                </CardContent>
              </Card>

              <Card className="shadow-sm border-l-4 border-l-orange-500">
                <CardHeader className="flex flex-row items-center justify-between pb-2">
                  <CardTitle className="text-xs font-semibold text-gray-500 uppercase tracking-wider">Total Overtime</CardTitle>
                  <Clock className="h-4 w-4 text-orange-500" />
                </CardHeader>
                <CardContent>
                  <div className="text-xl font-bold text-gray-900 dark:text-gray-100">
                    GHC {summary.totalOvertime.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                  </div>
                  <p className="text-xs text-gray-500 mt-1">Weekday + Weekend + Holiday OT</p>
                </CardContent>
              </Card>

              <Card className="shadow-sm border-l-4 border-l-emerald-500">
                <CardHeader className="flex flex-row items-center justify-between pb-2">
                  <CardTitle className="text-xs font-semibold text-gray-500 uppercase tracking-wider">Total Take Home</CardTitle>
                  <CheckCircle className="h-4 w-4 text-emerald-500" />
                </CardHeader>
                <CardContent>
                  <div className="text-xl font-bold text-emerald-600 dark:text-emerald-400">
                    GHC {summary.totalTakeHome.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                  </div>
                  <p className="text-xs text-gray-500 mt-1">Net salary disbursement</p>
                </CardContent>
              </Card>

              <Card className="shadow-sm border-l-4 border-l-purple-500">
                <CardHeader className="flex flex-row items-center justify-between pb-2">
                  <CardTitle className="text-xs font-semibold text-gray-500 uppercase tracking-wider">Total CTC (Cost to Co.)</CardTitle>
                  <Users className="h-4 w-4 text-purple-500" />
                </CardHeader>
                <CardContent>
                  <div className="text-xl font-bold text-purple-600 dark:text-purple-400">
                    GHC {summary.totalCtc.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                  </div>
                  <p className="text-xs text-gray-500 mt-1">Including fees & statutory levies</p>
                </CardContent>
              </Card>
            </div>
          )}

          {/* Search & Sort Filtering Bar */}
          {hasData && (
            <div className="flex flex-col sm:flex-row items-center justify-between gap-4 bg-white dark:bg-gray-800 p-4 rounded-lg border shadow-sm">
              <div className="relative w-full sm:w-80">
                <Search className="absolute left-3 top-2.5 h-4 w-4 text-gray-400" />
                <Input
                  placeholder="Search Clock In ID, Name, Dept..."
                  value={search}
                  onChange={(e) => setSearch(e.target.value)}
                  onKeyUp={(e) => e.key === "Enter" && handleSearchSort()}
                  className="pl-9"
                />
              </div>

              <div className="flex items-center gap-3 w-full sm:w-auto">
                <div className="flex items-center gap-2">
                  <Label htmlFor="sort" className="text-xs text-gray-500 uppercase shrink-0">
                    Sort:
                  </Label>
                  <Select value={sort} onValueChange={handleSortChange}>
                    <SelectTrigger id="sort" className="w-48">
                      <SelectValue placeholder="Sort By" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="name:asc">Name (A-Z)</SelectItem>
                      <SelectItem value="name:desc">Name (Z-A)</SelectItem>
                      <SelectItem value="clock_in_id:asc">Clock In ID (Asc)</SelectItem>
                      <SelectItem value="department:asc">Department (Asc)</SelectItem>
                      <SelectItem value="basic_salary:desc">Basic Salary (High-Low)</SelectItem>
                      <SelectItem value="total_overtime:desc">Overtime (High-Low)</SelectItem>
                      <SelectItem value="take_home:desc">Take Home (High-Low)</SelectItem>
                    </SelectContent>
                  </Select>
                </div>

                <Button onClick={handleSearchSort} variant="secondary" size="sm">
                  Filter
                </Button>
              </div>
            </div>
          )}

          {/* Main Records Table */}
          <Card className="shadow-sm">
            <CardHeader className="py-4">
              <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                <CardTitle className="text-base font-semibold">
                  Twillium Accra 2 Payroll Records — {formattedMonth}
                </CardTitle>
                <div className="text-xs text-gray-500">
                  {hasData ? (
                    <>
                      Showing {recordsData.length} of {searchCount ?? totalRecords} records
                      {searchCount !== null && ` (filtered from ${totalRecords})`}
                    </>
                  ) : (
                    "No data uploaded yet"
                  )}
                </div>
              </div>
            </CardHeader>

            <CardContent className="p-0">
              {hasData && recordsData.length > 0 ? (
                <div className="overflow-x-auto">
                  <Table>
                    <TableHeader className="bg-gray-50 dark:bg-gray-900/50">
                      <TableRow>
                        <TableHead className="font-semibold text-xs">Clock In ID</TableHead>
                        <TableHead className="font-semibold text-xs">SBP Staff ID</TableHead>
                        <TableHead className="font-semibold text-xs">Employee Name</TableHead>
                        <TableHead className="font-semibold text-xs">Department</TableHead>
                        <TableHead className="font-semibold text-xs">Designation</TableHead>
                        <TableHead className="font-semibold text-xs text-right">Basic Salary</TableHead>
                        <TableHead className="font-semibold text-xs text-right">Total Overtime</TableHead>
                        <TableHead className="font-semibold text-xs text-right">PAYE Tax</TableHead>
                        <TableHead className="font-semibold text-xs text-right">Take Home</TableHead>
                        <TableHead className="font-semibold text-xs text-right">CTC</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {recordsData.map((row, index) => (
                        <TableRow key={`${row.clock_in_id}-${index}`} className="hover:bg-gray-50/80 dark:hover:bg-gray-800/50 text-xs">
                          <TableCell className="font-mono font-medium text-gray-900 dark:text-gray-100">{row.clock_in_id}</TableCell>
                          <TableCell className="font-mono text-gray-500">{row.sbpstaffid || row.clock_in_id}</TableCell>
                          <TableCell className="font-medium text-gray-900 dark:text-gray-100">{row.name}</TableCell>
                          <TableCell>
                            <span className="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200">
                              {row.department || "No Dept"}
                            </span>
                          </TableCell>
                          <TableCell className="text-gray-600 dark:text-gray-400">{row.designation || "-"}</TableCell>
                          <TableCell className="text-right font-medium">
                            {row.basic_salary != null ? Number(row.basic_salary).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : "-"}
                          </TableCell>
                          <TableCell className="text-right font-medium text-orange-600 dark:text-orange-400">
                            {row.total_overtime != null ? Number(row.total_overtime).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : "0.00"}
                          </TableCell>
                          <TableCell className="text-right text-gray-600 dark:text-gray-400">
                            {row.paye != null ? Number(row.paye).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : "-"}
                          </TableCell>
                          <TableCell className="text-right font-bold text-emerald-600 dark:text-emerald-400">
                            {row.take_home != null ? Number(row.take_home).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : "-"}
                          </TableCell>
                          <TableCell className="text-right font-medium text-purple-600 dark:text-purple-400">
                            {row.ctc != null ? Number(row.ctc).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : "-"}
                          </TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                </div>
              ) : (
                <div className="py-16 text-center">
                  <FileSpreadsheet className="mx-auto h-12 w-12 text-gray-300 dark:text-gray-600" />
                  <h3 className="mt-3 text-base font-semibold text-gray-900 dark:text-gray-100">
                    No payroll data for {formattedMonth}
                  </h3>
                  <p className="mt-1 text-xs text-gray-500 max-w-sm mx-auto">
                    Upload both the <b>Basic Salary</b> and <b>Overtime</b> spreadsheets to process and generate the departmental summary.
                  </p>
                  <Button onClick={() => setIsUploadOpen(true)} className="mt-4 bg-emerald-600 hover:bg-emerald-700 text-white">
                    <Upload className="mr-2 h-4 w-4" />
                    Upload Files Now
                  </Button>
                </div>
              )}

              {/* Pagination */}
              {hasData && records?.links && records.links.length > 3 && (
                <div className="flex flex-col sm:flex-row items-center justify-between gap-4 p-4 border-t">
                  <div className="text-xs text-gray-500">
                    Page {records.current_page} of {records.last_page} ({records.total} records)
                  </div>
                  <div className="flex flex-wrap gap-1">
                    {records.links.map((link, index) => (
                      <Button
                        key={index}
                        variant={link.active ? "default" : "outline"}
                        size="sm"
                        disabled={!link.url}
                        asChild={!!link.url}
                        className="h-8 px-3 text-xs"
                      >
                        {link.url ? (
                          <Link
                            href={link.url}
                            preserveState
                            preserveScroll
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

          {/* Upload Modal */}
          <Dialog open={isUploadOpen} onOpenChange={setIsUploadOpen}>
            <DialogContent className="sm:max-w-md">
              <DialogHeader>
                <DialogTitle className="flex items-center gap-2">
                  <Upload className="h-5 w-5 text-emerald-600" />
                  Upload Twillium Accra 2 Payroll
                </DialogTitle>
                <DialogDescription>
                  Upload both the <b>Basic</b> and <b>Overtime</b> files for the selected month to process payroll.
                </DialogDescription>
              </DialogHeader>

              <form onSubmit={handleUploadSubmit} className="space-y-4">
                <div>
                  <Label htmlFor="upload-month">Payroll Month</Label>
                  <Input
                    id="upload-month"
                    type="month"
                    value={uploadForm.data.month}
                    onChange={(e) => uploadForm.setData("month", e.target.value)}
                    required
                    className="mt-1"
                  />
                </div>

                <div>
                  <Label htmlFor="basic-file" className="flex items-center justify-between">
                    <span>Basic Payroll File (CSV/XLSX)</span>
                    <a
                      href={route("twelliumaccra2.download-template", { type: "basic" })}
                      className="text-xs text-primary hover:underline font-normal"
                    >
                      Download Template
                    </a>
                  </Label>
                  <Input
                    id="basic-file"
                    type="file"
                    accept=".xlsx,.xls,.csv"
                    onChange={(e) => uploadForm.setData("basic_file", e.target.files?.[0] || null)}
                    required
                    className="mt-1"
                  />
                  {uploadForm.errors.basic_file && (
                    <p className="text-red-500 text-xs mt-1">{uploadForm.errors.basic_file}</p>
                  )}
                </div>

                <div>
                  <Label htmlFor="overtime-file" className="flex items-center justify-between">
                    <span>Overtime File (CSV/XLSX)</span>
                    <a
                      href={route("twelliumaccra2.download-template", { type: "overtime" })}
                      className="text-xs text-primary hover:underline font-normal"
                    >
                      Download Template
                    </a>
                  </Label>
                  <Input
                    id="overtime-file"
                    type="file"
                    accept=".xlsx,.xls,.csv"
                    onChange={(e) => uploadForm.setData("overtime_file", e.target.files?.[0] || null)}
                    required
                    className="mt-1"
                  />
                  {uploadForm.errors.overtime_file && (
                    <p className="text-red-500 text-xs mt-1">{uploadForm.errors.overtime_file}</p>
                  )}
                </div>

                <DialogFooter className="mt-6">
                  <Button type="button" variant="outline" onClick={() => setIsUploadOpen(false)}>
                    Cancel
                  </Button>
                  <Button type="submit" disabled={uploadForm.processing} className="bg-emerald-600 hover:bg-emerald-700 text-white">
                    {uploadForm.processing ? "Processing Files..." : "Process Payroll"}
                  </Button>
                </DialogFooter>
              </form>
            </DialogContent>
          </Dialog>

          {/* Delete Confirmation Modal */}
          <Dialog open={isDeleteOpen} onOpenChange={setIsDeleteOpen}>
            <DialogContent className="sm:max-w-md">
              <DialogHeader>
                <DialogTitle className="flex items-center gap-2 text-red-600">
                  <Trash2 className="h-5 w-5" />
                  Delete Monthly Payroll
                </DialogTitle>
                <DialogDescription>
                  Are you sure you want to delete all Basic and Overtime records for <b>{formattedMonth}</b>? This action will drop the tables for this month and cannot be undone.
                </DialogDescription>
              </DialogHeader>

              <form onSubmit={handleDeleteSubmit}>
                <DialogFooter className="mt-4">
                  <Button type="button" variant="outline" onClick={() => setIsDeleteOpen(false)}>
                    Cancel
                  </Button>
                  <Button type="submit" variant="destructive" disabled={deleteForm.processing}>
                    {deleteForm.processing ? "Deleting..." : "Yes, Delete Month"}
                  </Button>
                </DialogFooter>
              </form>
            </DialogContent>
          </Dialog>
        </div>
      </AppLayout>
    </ErrorBoundary>
  );
}
