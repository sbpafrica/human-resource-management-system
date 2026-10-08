import AppLayout from "@/layouts/app-layout";
import { Head, useForm, router } from "@inertiajs/react";
import React, { useState, useMemo } from "react";
import {
  Clock,
  Upload,
  FileText,
  FileSpreadsheet,
  Download,
  Calendar,
  Search,
  Users,
  CheckCircle2,
  XCircle,
  AlertCircle,
  RefreshCw,
  ChevronLeft,
  ChevronRight,
} from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { Badge } from "@/components/ui/badge";

interface AttendanceRecord {
  employee_id: string;
  employee_name: string;
  date: string;
  legal_work_days: number;
  present: number;
  clock_in: string;
  clock_out: string;
  early_overtime: number;
  overtime: number;
}

interface PaginatedAttendance {
  data: AttendanceRecord[];
  current_page: number;
  last_page: number;
  from: number;
  to: number;
  total: number;
  per_page: number;
  links: Array<{
    url: string | null;
    label: string;
    active: boolean;
  }>;
}

interface Stats {
  total_employees: number;
  total_working_days: number;
  total_present_logs: number;
  total_early_ot_hours: number;
  total_overtime_hours: number;
}

interface Props {
  attendanceRecords: PaginatedAttendance;
  filters: {
    from_date: string;
    to_date: string;
    search: string;
  };
  stats: Stats;
  flash?: {
    success?: string;
    error?: string;
    warning?: string;
  };
}

export default function AttendanceIndex({
  attendanceRecords,
  filters,
  stats,
  flash,
}: Props) {
  const [fromDate, setFromDate] = useState(filters.from_date || "");
  const [toDate, setToDate] = useState(filters.to_date || "");
  const [search, setSearch] = useState(filters.search || "");
  const [isExporting, setIsExporting] = useState(false);

  // Upload User Data Form
  const userUploadForm = useForm({
    user_file: null as File | null,
  });

  // Upload Attendance Log Form
  const attendanceUploadForm = useForm({
    attendance_file: null as File | null,
  });

  const handleFilterSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    router.get(
      route("attendance.index"),
      { from_date: fromDate, to_date: toDate, search, page: 1 },
      { preserveState: true, replace: true }
    );
  };

  const handleResetFilters = () => {
    const defaultFrom = new Date(new Date().getFullYear(), new Date().getMonth(), 1)
      .toISOString()
      .split("T")[0];
    const defaultTo = new Date().toISOString().split("T")[0];
    setFromDate(defaultFrom);
    setToDate(defaultTo);
    setSearch("");
    router.get(
      route("attendance.index"),
      { from_date: defaultFrom, to_date: defaultTo, search: "", page: 1 },
      { preserveState: true, replace: true }
    );
  };

  const handlePageClick = (url: string | null) => {
    if (url) {
      router.visit(url, {
        preserveState: true,
        preserveScroll: true,
      });
    }
  };

  const handleUserUpload = (e: React.FormEvent) => {
    e.preventDefault();
    if (!userUploadForm.data.user_file) return;

    userUploadForm.post(route("attendance.upload-employees"), {
      onSuccess: () => {
        userUploadForm.reset();
      },
    });
  };

  const handleAttendanceUpload = (e: React.FormEvent) => {
    e.preventDefault();
    if (!attendanceUploadForm.data.attendance_file) return;

    attendanceUploadForm.post(route("attendance.upload-attendance"), {
      onSuccess: () => {
        attendanceUploadForm.reset();
      },
    });
  };

  const handleExportExcel = () => {
    setIsExporting(true);
    const params = new URLSearchParams({
      from_date: fromDate,
      to_date: toDate,
      search: search,
    });
    window.location.href = route("attendance.export") + "?" + params.toString();
    setTimeout(() => setIsExporting(false), 2000);
  };

  const recordsList = attendanceRecords?.data || [];
  const totalCount  = attendanceRecords?.total || 0;

  const breadcrumbs = [
    { title: "Dashboard", href: "/dashboard" },
    { title: "Attendance", href: "/attendance" },
  ];

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Inhouse Attendance Report" />

      <div className="space-y-6 p-4 sm:p-6 max-w-7xl mx-auto">
        {/* Header */}
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div>
            <h1 className="text-2xl font-bold tracking-tight text-gray-900 dark:text-gray-100 flex items-center gap-2">
              <Clock className="h-7 w-7 text-blue-600 dark:text-blue-400" />
              Inhouse Attendance System
            </h1>
            <p className="text-xs sm:text-sm text-muted-foreground mt-1">
              Upload employee records and biometric attendance logs, calculate working hours, and export detailed reports.
            </p>
          </div>
          <div className="flex items-center gap-2 self-start sm:self-auto">
            <Button
              onClick={handleExportExcel}
              disabled={isExporting}
              className="bg-emerald-600 hover:bg-emerald-700 text-white gap-2 shadow-sm text-xs sm:text-sm"
            >
              <FileSpreadsheet className="h-4 w-4" />
              {isExporting ? "Exporting..." : "Export Excel"}
            </Button>
          </div>
        </div>

        {/* Flash Notifications */}
        {flash?.success && (
          <div className="p-4 rounded-lg bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200 text-sm flex items-start gap-3 shadow-sm">
            <CheckCircle2 className="h-5 w-5 text-emerald-600 dark:text-emerald-400 mt-0.5 shrink-0" />
            <div className="flex-1">{flash.success}</div>
          </div>
        )}

        {flash?.error && (
          <div className="p-4 rounded-lg bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 text-rose-800 dark:text-rose-200 text-sm flex items-start gap-3 shadow-sm">
            <XCircle className="h-5 w-5 text-rose-600 dark:text-rose-400 mt-0.5 shrink-0" />
            <div className="flex-1">{flash.error}</div>
          </div>
        )}

        {/* Stats Grid */}
        <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 sm:gap-4">
          <Card className="shadow-xs border bg-card">
            <CardContent className="p-4">
              <div className="flex items-center justify-between">
                <p className="text-xs font-medium text-muted-foreground">Employees</p>
                <Users className="h-4 w-4 text-blue-500" />
              </div>
              <p className="text-xl sm:text-2xl font-bold mt-1">{stats?.total_employees || 0}</p>
            </CardContent>
          </Card>

          <Card className="shadow-xs border bg-card">
            <CardContent className="p-4">
              <div className="flex items-center justify-between">
                <p className="text-xs font-medium text-muted-foreground">Working Days</p>
                <Calendar className="h-4 w-4 text-purple-500" />
              </div>
              <p className="text-xl sm:text-2xl font-bold mt-1">{stats?.total_working_days || 0}</p>
            </CardContent>
          </Card>

          <Card className="shadow-xs border bg-card">
            <CardContent className="p-4">
              <div className="flex items-center justify-between">
                <p className="text-xs font-medium text-muted-foreground">Present Logs</p>
                <CheckCircle2 className="h-4 w-4 text-emerald-500" />
              </div>
              <p className="text-xl sm:text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">
                {stats?.total_present_logs || 0}
              </p>
            </CardContent>
          </Card>

          <Card className="shadow-xs border bg-card">
            <CardContent className="p-4">
              <div className="flex items-center justify-between">
                <p className="text-xs font-medium text-muted-foreground">Early OT (Hrs)</p>
                <Clock className="h-4 w-4 text-amber-500" />
              </div>
              <p className="text-xl sm:text-2xl font-bold text-amber-600 dark:text-amber-400 mt-1">
                {stats?.total_early_ot_hours || 0}
              </p>
            </CardContent>
          </Card>

          <Card className="shadow-xs border bg-card col-span-2 sm:col-span-1">
            <CardContent className="p-4">
              <div className="flex items-center justify-between">
                <p className="text-xs font-medium text-muted-foreground">Overtime (Hrs)</p>
                <Clock className="h-4 w-4 text-indigo-500" />
              </div>
              <p className="text-xl sm:text-2xl font-bold text-indigo-600 dark:text-indigo-400 mt-1">
                {stats?.total_overtime_hours || 0}
              </p>
            </CardContent>
          </Card>
        </div>

        {/* Upload Forms Section */}
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          {/* Employee TXT Upload */}
          <Card className="shadow-sm">
            <CardHeader className="pb-3">
              <CardTitle className="text-base font-semibold flex items-center gap-2">
                <FileText className="h-5 w-5 text-blue-600" />
                Upload Employee Master (.txt / .csv)
              </CardTitle>
              <CardDescription className="text-xs">
                Upload tab-separated employee list (Employee ID &amp; Employee Name).
              </CardDescription>
            </CardHeader>
            <CardContent>
              <form onSubmit={handleUserUpload} className="space-y-3">
                <div className="space-y-1">
                  <Input
                    type="file"
                    accept=".txt,.csv"
                    onChange={(e) =>
                      userUploadForm.setData("user_file", e.target.files ? e.target.files[0] : null)
                    }
                    className="text-xs cursor-pointer file:mr-3 file:py-1 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                  />
                  {userUploadForm.errors.user_file && (
                    <p className="text-xs text-rose-500">{userUploadForm.errors.user_file}</p>
                  )}
                </div>
                <Button
                  type="submit"
                  disabled={userUploadForm.processing || !userUploadForm.data.user_file}
                  className="w-full sm:w-auto bg-blue-600 hover:bg-blue-700 text-white text-xs h-9 gap-2"
                >
                  <Upload className="h-3.5 w-3.5" />
                  {userUploadForm.processing ? "Uploading..." : "Upload Employees"}
                </Button>
              </form>
            </CardContent>
          </Card>

          {/* Attendance Log TXT Upload */}
          <Card className="shadow-sm">
            <CardHeader className="pb-3">
              <CardTitle className="text-base font-semibold flex items-center gap-2">
                <Clock className="h-5 w-5 text-emerald-600" />
                Upload Biometric Attendance Log (.txt)
              </CardTitle>
              <CardDescription className="text-xs">
                Upload tab-separated biometric device log containing timestamp and clocking events.
              </CardDescription>
            </CardHeader>
            <CardContent>
              <form onSubmit={handleAttendanceUpload} className="space-y-3">
                <div className="space-y-1">
                  <Input
                    type="file"
                    accept=".txt"
                    onChange={(e) =>
                      attendanceUploadForm.setData("attendance_file", e.target.files ? e.target.files[0] : null)
                    }
                    className="text-xs cursor-pointer file:mr-3 file:py-1 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-medium file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100"
                  />
                  {attendanceUploadForm.errors.attendance_file && (
                    <p className="text-xs text-rose-500">{attendanceUploadForm.errors.attendance_file}</p>
                  )}
                </div>
                <Button
                  type="submit"
                  disabled={attendanceUploadForm.processing || !attendanceUploadForm.data.attendance_file}
                  className="w-full sm:w-auto bg-emerald-600 hover:bg-emerald-700 text-white text-xs h-9 gap-2"
                >
                  <Upload className="h-3.5 w-3.5" />
                  {attendanceUploadForm.processing ? "Uploading..." : "Upload Attendance Log"}
                </Button>
              </form>
            </CardContent>
          </Card>
        </div>

        {/* Date Filter & Search Bar */}
        <Card className="shadow-sm">
          <CardContent className="p-4 sm:p-5">
            <form onSubmit={handleFilterSubmit} className="flex flex-col md:flex-row gap-3 items-end">
              <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 flex-1 w-full">
                <div className="space-y-1">
                  <Label htmlFor="from_date" className="text-xs font-semibold text-gray-700 dark:text-gray-300">
                    From Date
                  </Label>
                  <Input
                    id="from_date"
                    type="date"
                    value={fromDate}
                    onChange={(e) => setFromDate(e.target.value)}
                    className="h-9 text-xs sm:text-sm"
                  />
                </div>

                <div className="space-y-1">
                  <Label htmlFor="to_date" className="text-xs font-semibold text-gray-700 dark:text-gray-300">
                    To Date
                  </Label>
                  <Input
                    id="to_date"
                    type="date"
                    value={toDate}
                    onChange={(e) => setToDate(e.target.value)}
                    className="h-9 text-xs sm:text-sm"
                  />
                </div>

                <div className="space-y-1">
                  <Label htmlFor="search" className="text-xs font-semibold text-gray-700 dark:text-gray-300">
                    Search Employee / ID
                  </Label>
                  <div className="relative">
                    <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                    <Input
                      id="search"
                      type="text"
                      placeholder="Search name or ID..."
                      value={search}
                      onChange={(e) => setSearch(e.target.value)}
                      className="pl-9 h-9 text-xs sm:text-sm"
                    />
                  </div>
                </div>
              </div>

              <div className="flex items-center gap-2 self-stretch md:self-auto">
                <Button type="submit" className="h-9 text-xs sm:text-sm gap-1.5 flex-1 md:flex-initial">
                  <Search className="h-3.5 w-3.5" />
                  Show Report
                </Button>
                <Button
                  type="button"
                  variant="outline"
                  onClick={handleResetFilters}
                  className="h-9 text-xs sm:text-sm gap-1.5"
                >
                  <RefreshCw className="h-3.5 w-3.5" />
                  Reset
                </Button>
              </div>
            </form>
          </CardContent>
        </Card>

        {/* Data Table */}
        <Card className="shadow-sm overflow-hidden">
          <CardHeader className="bg-gray-50/70 dark:bg-gray-800/40 border-b py-3 px-4 sm:px-6">
            <div className="flex items-center justify-between">
              <div>
                <CardTitle className="text-sm sm:text-base font-semibold">
                  Attendance Records ({totalCount} entries)
                </CardTitle>
                <CardDescription className="text-xs">
                  Normal working hours: 08:00 - 17:00 (Mon - Fri). Early OT &amp; Overtime calculated automatically.
                </CardDescription>
              </div>
            </div>
          </CardHeader>
          <CardContent className="p-0">
            {recordsList.length > 0 ? (
              <div className="overflow-x-auto">
                <Table>
                  <TableHeader>
                    <TableRow className="bg-gray-100/80 dark:bg-gray-800/80 hover:bg-gray-100/80">
                      <TableHead className="font-bold text-xs">Employee ID</TableHead>
                      <TableHead className="font-bold text-xs">Employee Name</TableHead>
                      <TableHead className="font-bold text-xs">Date</TableHead>
                      <TableHead className="font-bold text-xs text-center">Work Days</TableHead>
                      <TableHead className="font-bold text-xs text-center">Status</TableHead>
                      <TableHead className="font-bold text-xs text-center">Clock In</TableHead>
                      <TableHead className="font-bold text-xs text-center">Clock Out</TableHead>
                      <TableHead className="font-bold text-xs text-right">Early OT (hrs)</TableHead>
                      <TableHead className="font-bold text-xs text-right">Overtime (hrs)</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {recordsList.map((r, idx) => (
                      <TableRow
                        key={`${r.employee_id}-${r.date}-${idx}`}
                        className="hover:bg-gray-50/80 dark:hover:bg-gray-800/50 text-xs"
                      >
                        <TableCell className="font-mono font-medium">{r.employee_id}</TableCell>
                        <TableCell className="font-semibold text-gray-900 dark:text-gray-100">
                          {r.employee_name}
                        </TableCell>
                        <TableCell className="text-gray-600 dark:text-gray-300">{r.date}</TableCell>
                        <TableCell className="text-center">{r.legal_work_days}</TableCell>
                        <TableCell className="text-center">
                          {r.present ? (
                            <Badge className="bg-emerald-100 text-emerald-800 hover:bg-emerald-100 border-emerald-300 dark:bg-emerald-950 dark:text-emerald-300">
                              Present
                            </Badge>
                          ) : (
                            <Badge variant="outline" className="bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950 dark:text-rose-300">
                              Absent
                            </Badge>
                          )}
                        </TableCell>
                        <TableCell className="text-center font-mono">{r.clock_in}</TableCell>
                        <TableCell className="text-center font-mono">{r.clock_out}</TableCell>
                        <TableCell className="text-right font-mono text-amber-600 dark:text-amber-400 font-semibold">
                          {r.early_overtime > 0 ? r.early_overtime.toFixed(2) : "-"}
                        </TableCell>
                        <TableCell className="text-right font-mono text-indigo-600 dark:text-indigo-400 font-semibold">
                          {r.overtime > 0 ? r.overtime.toFixed(2) : "-"}
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </div>
            ) : (
              <div className="py-12 text-center text-muted-foreground text-sm">
                <Clock className="h-10 w-10 text-gray-300 mx-auto mb-3" />
                <p className="font-medium">No attendance records found.</p>
                <p className="text-xs text-gray-400 mt-1">
                  Try adjusting your date range filter or upload attendance log files above.
                </p>
              </div>
            )}

            {/* Pagination Controls */}
            {attendanceRecords?.links && attendanceRecords.links.length > 0 && (
              <div className="flex flex-col sm:flex-row items-center justify-between p-3 sm:p-4 border-t gap-3">
                <div className="text-xs text-muted-foreground text-center sm:text-left">
                  Showing{" "}
                  <span className="font-medium text-foreground">
                    {attendanceRecords.from || 0}
                  </span>{" "}
                  to{" "}
                  <span className="font-medium text-foreground">
                    {attendanceRecords.to || 0}
                  </span>{" "}
                  of{" "}
                  <span className="font-medium text-foreground">
                    {attendanceRecords.total || 0}
                  </span>{" "}
                  entries
                </div>

                <div className="flex flex-wrap items-center justify-center gap-1 max-w-full overflow-x-auto py-1">
                  {attendanceRecords.links.map((link, idx) => {
                    const isPrevious =
                      link.label.includes("&laquo;") || link.label.toLowerCase().includes("previous");
                    const isNext =
                      link.label.includes("&raquo;") || link.label.toLowerCase().includes("next");

                    return (
                      <Button
                        key={idx}
                        size="sm"
                        variant={link.active ? "default" : "outline"}
                        disabled={!link.url}
                        className={`h-8 text-xs transition-colors ${
                          link.active ? "bg-blue-600 hover:bg-blue-700 text-white" : ""
                        } ${!link.url ? "opacity-50 cursor-not-allowed" : "cursor-pointer"}`}
                        onClick={() => handlePageClick(link.url)}
                      >
                        {isPrevious ? (
                          <span className="flex items-center gap-1">
                            <ChevronLeft className="h-3.5 w-3.5" /> Prev
                          </span>
                        ) : isNext ? (
                          <span className="flex items-center gap-1">
                            Next <ChevronRight className="h-3.5 w-3.5" />
                          </span>
                        ) : (
                          <span dangerouslySetInnerHTML={{ __html: link.label }} />
                        )}
                      </Button>
                    );
                  })}
                </div>
              </div>
            )}
          </CardContent>
        </Card>
      </div>
    </AppLayout>
  );
}
