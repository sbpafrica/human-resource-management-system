import { Head, router } from "@inertiajs/react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
  Download,
  Upload,
  Trash2,
  Search,
  Users,
  DollarSign,
  Briefcase,
  AlertCircle,
  FileSpreadsheet,
  CheckCircle,
  XCircle,
} from "lucide-react";
import AppLayout from "@/layouts/app-layout";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
  DialogFooter,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { useState, useEffect, useMemo } from "react";
import { useForm } from "@inertiajs/react";
import ErrorBoundary from "@/components/ErrorBoundary";

interface BeninBungeRecord {
  staff_id: string;
  names?: string | null;
  first_names?: string | null;
  department?: string | null;
  joining_date?: string | null;
  contract_start_date?: string | null;
  contract_end_date?: string | null;
  position?: string | null;
  job_grade?: string | null;
  work_place?: string | null;
  bank_name?: string | null;
  bank_account_number?: string | null;
  social_security_number?: string | null;
  legal_work_days?: number | null;
  work_days?: number | null;
  basic_salary?: number | string | null;
  sursalaire?: number | string | null;
  basic_kpi?: number | string | null;
  kpi_score?: number | string | null;
  kpi_result?: number | string | null;
  commission?: number | string | null;
  bonuses?: number | string | null;
  communication_allowance?: number | string | null;
  transport_allowance?: number | string | null;
  other_special_bonus_subsidy_etc?: number | string | null;
  gross_salaries?: number | string | null;
  deduction?: number | string | null;
  gross_amount?: number | string | null;
  remarque?: string | null;
  ps_pension_salarie?: number | string | null;
  pac?: number | string | null;
  pac_arrondi?: number | string | null;
  its?: number | string | null;
  its2?: number | string | null;
  its_deduction_totale?: number | string | null;
  net_a_payer?: number | string | null;
  prestations_familiales?: number | string | null;
  pension_employeur_pe_65_percent?: number | string | null;
  risques_professionnels_rp_4_percent?: number | string | null;
  vps_versement_patronal_sur_salaire_4_percent?: number | string | null;
  total_part_patronale?: number | string | null;
  total_des_resources_humaines?: number | string | null;
  frais_de_service_percent_ht?: number | string | null;
  tva_18_percent?: number | string | null;
  cout_total_entreprise_externalisation?: number | string | null;
  cout_total_facture?: number | string | null;
}

interface Props {
  records: BeninBungeRecord[];
  selectedMonth: string;
  formattedMonth: string;
  totalRecords: number;
  flash?: {
    success?: string;
    error?: string;
  };
}

const breadcrumbs = [
  { title: "Bunge West Africa", href: "/benin-bunge" },
  { title: "Benin Bunge", href: "/benin-bunge" },
];

export default function BeninBungeIndex({
  records = [],
  selectedMonth,
  formattedMonth,
  totalRecords = 0,
  flash,
}: Props) {
  const [isBulkUploadOpen, setIsBulkUploadOpen] = useState(false);
  const [isDeleteConfirmOpen, setIsDeleteConfirmOpen] = useState(false);
  const [isDeleting, setIsDeleting] = useState(false);
  const [showToast, setShowToast] = useState(false);
  const [toastMessage, setToastMessage] = useState("");
  const [toastType, setToastType] = useState<"success" | "error">("success");
  const [search, setSearch] = useState("");
  const [sortField, setSortField] = useState<keyof BeninBungeRecord>("names");
  const [sortDirection, setSortDirection] = useState<"asc" | "desc">("asc");
  const [currentPage, setCurrentPage] = useState(1);
  const pageSize = 20;

  const { data, setData } = useForm({
    month: selectedMonth,
  });

  const uploadForm = useForm({
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
    if (Object.keys(uploadForm.errors).length > 0) {
      const errorMessage = Object.values(uploadForm.errors).join(" | ");
      setToastMessage(errorMessage);
      setToastType("error");
      setShowToast(true);
    }
  }, [uploadForm.errors]);

  useEffect(() => {
    if (showToast) {
      const timeout = toastType === "error" ? 6000 : 3000;
      const timer = setTimeout(() => setShowToast(false), timeout);
      return () => clearTimeout(timer);
    }
  }, [showToast, toastType]);

  const handleMonthChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const newMonth = e.target.value;
    setData("month", newMonth);
    uploadForm.setData("month", newMonth);
    router.get(
      route("benin-bunge.pay.index"),
      { month: newMonth },
      { preserveState: false, preserveScroll: true }
    );
  };

  const handleDownloadTemplate = () => {
    window.location.href = route("benin-bunge.pay.download-template");
  };

  const handleBulkUpload = (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    if (uploadForm.data.file) {
      uploadForm.post(route("benin-bunge.pay.upload"), {
        forceFormData: true,
        preserveState: true,
        onSuccess: () => {
          setIsBulkUploadOpen(false);
          uploadForm.reset();
        },
      });
    }
  };

  const handleDelete = async () => {
    try {
      setIsDeleting(true);
      const csrfToken = document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute("content");

      const response = await fetch(route("benin-bunge.pay.delete"), {
        method: "DELETE",
        headers: {
          "Content-Type": "application/json",
          "X-CSRF-TOKEN": csrfToken || "",
          Accept: "application/json",
        },
        body: JSON.stringify({ month: data.month }),
      });

      const res = await response.json();
      setIsDeleteConfirmOpen(false);

      if (response.ok && res.success) {
        setToastMessage(res.message || "Records deleted successfully.");
        setToastType("success");
        setShowToast(true);
        router.reload();
      } else {
        throw new Error(res.message || "Failed to delete records.");
      }
    } catch (err: any) {
      setToastMessage(err.message || "Failed to delete records.");
      setToastType("error");
      setShowToast(true);
    } finally {
      setIsDeleting(false);
    }
  };

  const handleExport = () => {
    window.location.href = route("benin-bunge.pay.export", { month: data.month });
  };

  // Calculations & Filtering
  const filteredRecords = useMemo(() => {
    if (!Array.isArray(records)) return [];
    return records.filter((r) => {
      const q = search.toLowerCase();
      const fullName = `${r.names || ""} ${r.first_names || ""}`.toLowerCase();
      const staffid = (r.staff_id || "").toLowerCase();
      const dept = (r.department || "").toLowerCase();
      const pos = (r.position || "").toLowerCase();
      const workplace = (r.work_place || "").toLowerCase();
      return (
        fullName.includes(q) ||
        staffid.includes(q) ||
        dept.includes(q) ||
        pos.includes(q) ||
        workplace.includes(q)
      );
    });
  }, [records, search]);

  const sortedRecords = useMemo(() => {
    return [...filteredRecords].sort((a, b) => {
      let aVal = a[sortField] ?? "";
      let bVal = b[sortField] ?? "";

      const numA = parseFloat(String(aVal).replace(/[^0-9.-]+/g, ""));
      const numB = parseFloat(String(bVal).replace(/[^0-9.-]+/g, ""));

      if (!isNaN(numA) && !isNaN(numB) && sortField !== "staff_id") {
        return sortDirection === "asc" ? numA - numB : numB - numA;
      }

      return sortDirection === "asc"
        ? String(aVal).localeCompare(String(bVal))
        : String(bVal).localeCompare(String(aVal));
    });
  }, [filteredRecords, sortField, sortDirection]);

  const paginatedRecords = useMemo(() => {
    const start = (currentPage - 1) * pageSize;
    return sortedRecords.slice(start, start + pageSize);
  }, [sortedRecords, currentPage]);

  const totalPages = Math.ceil(sortedRecords.length / pageSize) || 1;

  const totalGross = useMemo(() => {
    if (!Array.isArray(records)) return 0;
    return records.reduce((acc, r) => {
      const v = parseFloat(String(r.gross_salaries || r.gross_amount || "").replace(/[^0-9.-]+/g, ""));
      return acc + (isNaN(v) ? 0 : v);
    }, 0);
  }, [records]);

  const totalNet = useMemo(() => {
    if (!Array.isArray(records)) return 0;
    return records.reduce((acc, r) => {
      const v = parseFloat(String(r.net_a_payer || "").replace(/[^0-9.-]+/g, ""));
      return acc + (isNaN(v) ? 0 : v);
    }, 0);
  }, [records]);

  const totalDeductions = useMemo(() => {
    if (!Array.isArray(records)) return 0;
    return records.reduce((acc, r) => {
      const v = parseFloat(String(r.deduction || r.its_deduction_totale || "").replace(/[^0-9.-]+/g, ""));
      return acc + (isNaN(v) ? 0 : v);
    }, 0);
  }, [records]);

  const handleSort = (field: keyof BeninBungeRecord) => {
    if (sortField === field) {
      setSortDirection((prev) => (prev === "asc" ? "desc" : "asc"));
    } else {
      setSortField(field);
      setSortDirection("asc");
    }
  };

  return (
    <ErrorBoundary>
      <AppLayout breadcrumbs={breadcrumbs}>
        <Head title="Benin Bunge Payroll" />

        <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-4 md:p-6">
          {/* Toast Notification */}
          {showToast && (
            <div
              className={`fixed top-4 right-4 z-50 flex items-center gap-2 rounded-lg p-4 shadow-lg w-full sm:w-auto max-w-md ${
                toastType === "success" ? "bg-green-600 text-white" : "bg-red-600 text-white"
              } animate-in fade-in slide-in-from-top-5`}
            >
              {toastType === "success" ? (
                <CheckCircle className="h-5 w-5 shrink-0" />
              ) : (
                <XCircle className="h-5 w-5 shrink-0" />
              )}
              <span className="text-sm">{toastMessage}</span>
              <Button
                variant="ghost"
                size="sm"
                className="ml-auto p-1 text-white hover:bg-black/20"
                onClick={() => setShowToast(false)}
              >
                <XCircle className="h-4 w-4" />
              </Button>
            </div>
          )}

          {/* Header & Main Controls */}
          <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
              <h1 className="text-2xl font-bold tracking-tight">Benin Bunge Payroll</h1>
              <p className="text-sm text-muted-foreground mt-1">
                Benin staff payroll & social contributions management for {formattedMonth}
              </p>
            </div>

            <div className="flex flex-wrap items-center gap-2">
              <div className="flex items-center gap-2 bg-card border rounded-md px-3 py-1.5 shadow-sm">
                <Label htmlFor="month" className="text-sm font-medium whitespace-nowrap">
                  Month:
                </Label>
                <Input
                  id="month"
                  type="month"
                  value={data.month}
                  onChange={handleMonthChange}
                  min="2020-01"
                  max={new Date().toISOString().slice(0, 7)}
                  className="h-8 w-36 border-0 p-0 focus-visible:ring-0 shadow-none font-medium"
                />
              </div>

              <Button
                onClick={handleDownloadTemplate}
                variant="outline"
                size="sm"
                className="h-9 gap-1.5"
              >
                <Download className="h-4 w-4" />
                Template
              </Button>

              <Dialog
                open={isBulkUploadOpen}
                onOpenChange={(open) => {
                  setIsBulkUploadOpen(open);
                  if (!open) uploadForm.reset();
                }}
              >
                <DialogTrigger asChild>
                  <Button size="sm" className="h-9 gap-1.5 bg-green-600 hover:bg-green-700 text-white">
                    <Upload className="h-4 w-4" />
                    Upload File
                  </Button>
                </DialogTrigger>
                <DialogContent className="sm:max-w-md">
                  <DialogHeader>
                    <DialogTitle>Upload Benin Bunge Payroll Data</DialogTitle>
                    <DialogDescription>
                      Upload the Excel file (.xlsx, .xls) for {formattedMonth}.
                    </DialogDescription>
                  </DialogHeader>
                  <form onSubmit={handleBulkUpload}>
                    <div className="grid gap-4 py-4">
                      <div className="grid gap-2">
                        <Label htmlFor="upload-month">Selected Month</Label>
                        <Input
                          id="upload-month"
                          type="month"
                          value={uploadForm.data.month}
                          onChange={(e) => uploadForm.setData("month", e.target.value)}
                          required
                        />
                      </div>
                      <div className="grid gap-2">
                        <Label htmlFor="file">Excel File</Label>
                        <Input
                          id="file"
                          type="file"
                          accept=".xlsx,.xls"
                          onChange={(e) => {
                            const file = e.target.files?.[0];
                            if (file) uploadForm.setData("file", file);
                          }}
                          required
                        />
                        {uploadForm.errors.file && (
                          <p className="text-red-500 text-xs">{uploadForm.errors.file}</p>
                        )}
                      </div>
                    </div>
                    <DialogFooter>
                      <Button
                        type="button"
                        variant="outline"
                        onClick={() => setIsBulkUploadOpen(false)}
                      >
                        Cancel
                      </Button>
                      <Button
                        type="submit"
                        disabled={uploadForm.processing || !uploadForm.data.file}
                        className="bg-green-600 hover:bg-green-700 text-white"
                      >
                        {uploadForm.processing ? "Uploading..." : "Upload & Save"}
                      </Button>
                    </DialogFooter>
                  </form>
                </DialogContent>
              </Dialog>

              <Button
                onClick={handleExport}
                disabled={records.length === 0}
                size="sm"
                className="h-9 gap-1.5 bg-blue-600 hover:bg-blue-700 text-white"
              >
                <FileSpreadsheet className="h-4 w-4" />
                Export
              </Button>

              {records.length > 0 && (
                <Button
                  onClick={() => setIsDeleteConfirmOpen(true)}
                  variant="destructive"
                  size="sm"
                  className="h-9 gap-1.5"
                >
                  <Trash2 className="h-4 w-4" />
                  Delete Month
                </Button>
              )}
            </div>
          </div>

          {/* Delete Confirmation Dialog */}
          <Dialog open={isDeleteConfirmOpen} onOpenChange={setIsDeleteConfirmOpen}>
            <DialogContent className="sm:max-w-md">
              <DialogHeader>
                <DialogTitle className="flex items-center gap-2 text-destructive">
                  <AlertCircle className="h-5 w-5" />
                  Delete Month Records
                </DialogTitle>
                <DialogDescription>
                  Are you sure you want to delete all Benin Bunge records for{" "}
                  <strong>{formattedMonth}</strong>? This action cannot be undone.
                </DialogDescription>
              </DialogHeader>
              <DialogFooter className="gap-2 sm:gap-0">
                <Button
                  type="button"
                  variant="outline"
                  onClick={() => setIsDeleteConfirmOpen(false)}
                  disabled={isDeleting}
                >
                  Cancel
                </Button>
                <Button
                  type="button"
                  variant="destructive"
                  onClick={handleDelete}
                  disabled={isDeleting}
                >
                  {isDeleting ? "Deleting..." : "Delete All"}
                </Button>
              </DialogFooter>
            </DialogContent>
          </Dialog>

          {/* Metric Summary Cards */}
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <Card>
              <CardHeader className="flex flex-row items-center justify-between pb-2">
                <CardTitle className="text-sm font-medium text-muted-foreground">
                  Total Employees
                </CardTitle>
                <Users className="h-4 w-4 text-blue-500" />
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold">{totalRecords}</div>
                <p className="text-xs text-muted-foreground mt-1">Benin Payroll headcount</p>
              </CardContent>
            </Card>

            <Card>
              <CardHeader className="flex flex-row items-center justify-between pb-2">
                <CardTitle className="text-sm font-medium text-muted-foreground">
                  Total Salaire Brut
                </CardTitle>
                <DollarSign className="h-4 w-4 text-amber-500" />
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold">
                  {totalGross.toLocaleString("fr-FR", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                </div>
                <p className="text-xs text-muted-foreground mt-1">Gross salaries total (FCFA)</p>
              </CardContent>
            </Card>

            <Card>
              <CardHeader className="flex flex-row items-center justify-between pb-2">
                <CardTitle className="text-sm font-medium text-muted-foreground">
                  Total Déductions
                </CardTitle>
                <Briefcase className="h-4 w-4 text-purple-500" />
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold">
                  {totalDeductions.toLocaleString("fr-FR", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                </div>
                <p className="text-xs text-muted-foreground mt-1">ITS & Retenues Salariales</p>
              </CardContent>
            </Card>

            <Card>
              <CardHeader className="flex flex-row items-center justify-between pb-2">
                <CardTitle className="text-sm font-medium text-muted-foreground">
                  Total Net à Payer
                </CardTitle>
                <DollarSign className="h-4 w-4 text-emerald-500" />
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold text-emerald-600">
                  {totalNet.toLocaleString("fr-FR", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                </div>
                <p className="text-xs text-muted-foreground mt-1">Take-home pay total (FCFA)</p>
              </CardContent>
            </Card>
          </div>

          {/* Table Card */}
          <Card className="flex-1 flex flex-col">
            <CardHeader className="border-b px-6 py-4">
              <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                  <CardTitle className="text-lg">Payroll Details ({formattedMonth})</CardTitle>
                  <p className="text-sm text-muted-foreground mt-0.5">
                    Showing {sortedRecords.length} staff record{sortedRecords.length === 1 ? "" : "s"}
                  </p>
                </div>

                <div className="flex flex-wrap items-center gap-3">
                  <div className="relative w-full sm:w-64">
                    <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                    <Input
                      type="text"
                      placeholder="Search by ID, name, dept, place..."
                      value={search}
                      onChange={(e) => {
                        setSearch(e.target.value);
                        setCurrentPage(1);
                      }}
                      className="pl-8 h-9 text-sm"
                    />
                  </div>

                  <div className="flex items-center gap-2">
                    <Select
                      value={sortField}
                      onValueChange={(val) => handleSort(val as keyof BeninBungeRecord)}
                    >
                      <SelectTrigger className="h-9 w-40 text-sm">
                        <SelectValue placeholder="Sort by" />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="names">Full Name</SelectItem>
                        <SelectItem value="staff_id">Staff ID</SelectItem>
                        <SelectItem value="department">Department</SelectItem>
                        <SelectItem value="gross_salaries">Salaire Brut</SelectItem>
                        <SelectItem value="net_a_payer">Net à Payer</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                </div>
              </div>
            </CardHeader>

            <CardContent className="p-0 flex-1 flex flex-col justify-between">
              {sortedRecords.length === 0 ? (
                <div className="flex flex-col items-center justify-center p-12 text-center">
                  <div className="h-16 w-16 rounded-full bg-muted flex items-center justify-center mb-4">
                    <FileSpreadsheet className="h-8 w-8 text-muted-foreground" />
                  </div>
                  <h3 className="text-lg font-semibold">No Payroll Data Found</h3>
                  <p className="text-sm text-muted-foreground max-w-md mt-1 mb-6">
                    There are no Benin Bunge payroll records available for{" "}
                    <strong>{formattedMonth}</strong>. Download the template and upload your
                    completed Excel file to populate this table.
                  </p>
                  <div className="flex flex-wrap gap-3 justify-center">
                    <Button
                      onClick={handleDownloadTemplate}
                      variant="outline"
                      size="sm"
                      className="gap-2"
                    >
                      <Download className="h-4 w-4" />
                      Download Template
                    </Button>
                    <Button
                      onClick={() => setIsBulkUploadOpen(true)}
                      size="sm"
                      className="gap-2 bg-green-600 hover:bg-green-700 text-white"
                    >
                      <Upload className="h-4 w-4" />
                      Upload Data
                    </Button>
                  </div>
                </div>
              ) : (
                <>
                  <div className="overflow-x-auto">
                    <Table>
                      <TableHeader className="bg-muted/50">
                        <TableRow>
                          <TableHead className="w-12 text-center font-semibold">#</TableHead>
                          <TableHead
                            className="cursor-pointer font-semibold"
                            onClick={() => handleSort("staff_id")}
                          >
                            Staff ID {sortField === "staff_id" && (sortDirection === "asc" ? "↑" : "↓")}
                          </TableHead>
                          <TableHead
                            className="cursor-pointer font-semibold"
                            onClick={() => handleSort("names")}
                          >
                            Nom & Prénom {sortField === "names" && (sortDirection === "asc" ? "↑" : "↓")}
                          </TableHead>
                          <TableHead
                            className="cursor-pointer font-semibold"
                            onClick={() => handleSort("department")}
                          >
                            Department {sortField === "department" && (sortDirection === "asc" ? "↑" : "↓")}
                          </TableHead>
                          <TableHead className="font-semibold">Position</TableHead>
                          <TableHead className="font-semibold">Lieu / Banque</TableHead>
                          <TableHead className="text-right font-semibold">Salaire Base</TableHead>
                          <TableHead
                            className="text-right cursor-pointer font-semibold"
                            onClick={() => handleSort("gross_salaries")}
                          >
                            Salaire Brut {sortField === "gross_salaries" && (sortDirection === "asc" ? "↑" : "↓")}
                          </TableHead>
                          <TableHead className="text-right font-semibold">Pension Salarié</TableHead>
                          <TableHead className="text-right font-semibold">ITS Total</TableHead>
                          <TableHead
                            className="text-right cursor-pointer font-semibold text-emerald-700 dark:text-emerald-400"
                            onClick={() => handleSort("net_a_payer")}
                          >
                            Net à Payer {sortField === "net_a_payer" && (sortDirection === "asc" ? "↑" : "↓")}
                          </TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {paginatedRecords.map((r, idx) => {
                          const rowNum = (currentPage - 1) * pageSize + idx + 1;
                          const fullName = `${r.names || ""} ${r.first_names || ""}`.trim() || "-";
                          return (
                            <TableRow key={`${r.staff_id}-${idx}`} className="hover:bg-muted/40">
                              <TableCell className="text-center text-xs text-muted-foreground">
                                {rowNum}
                              </TableCell>
                              <TableCell className="font-mono text-xs font-medium">{r.staff_id}</TableCell>
                              <TableCell className="font-medium">{fullName}</TableCell>
                              <TableCell className="text-xs text-muted-foreground">
                                {r.department || "-"}
                              </TableCell>
                              <TableCell className="text-xs">{r.position || "-"}</TableCell>
                              <TableCell className="text-xs text-muted-foreground">
                                {r.work_place || r.bank_name || "-"}
                              </TableCell>
                              <TableCell className="text-right font-mono text-xs">
                                {r.basic_salary != null ? String(r.basic_salary) : "-"}
                              </TableCell>
                              <TableCell className="text-right font-mono text-xs font-semibold">
                                {r.gross_salaries || r.gross_amount || "-"}
                              </TableCell>
                              <TableCell className="text-right font-mono text-xs text-muted-foreground">
                                {r.ps_pension_salarie != null ? String(r.ps_pension_salarie) : "-"}
                              </TableCell>
                              <TableCell className="text-right font-mono text-xs text-muted-foreground">
                                {r.its_deduction_totale || r.its || "-"}
                              </TableCell>
                              <TableCell className="text-right font-mono text-xs font-bold text-emerald-600 dark:text-emerald-400">
                                {r.net_a_payer || "-"}
                              </TableCell>
                            </TableRow>
                          );
                        })}
                      </TableBody>
                    </Table>
                  </div>

                  {/* Pagination footer */}
                  <div className="flex flex-col sm:flex-row items-center justify-between gap-4 border-t px-6 py-4">
                    <p className="text-xs text-muted-foreground">
                      Showing {(currentPage - 1) * pageSize + 1} to{" "}
                      {Math.min(currentPage * pageSize, sortedRecords.length)} of{" "}
                      {sortedRecords.length} records
                    </p>
                    <div className="flex items-center gap-2">
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => setCurrentPage((p) => Math.max(1, p - 1))}
                        disabled={currentPage === 1}
                      >
                        Previous
                      </Button>
                      <span className="text-xs px-2">
                        Page {currentPage} of {totalPages}
                      </span>
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => setCurrentPage((p) => Math.min(totalPages, p + 1))}
                        disabled={currentPage >= totalPages}
                      >
                        Next
                      </Button>
                    </div>
                  </div>
                </>
              )}
            </CardContent>
          </Card>
        </div>
      </AppLayout>
    </ErrorBoundary>
  );
}
