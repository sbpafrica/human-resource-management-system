import AppLayout from "@/layouts/app-layout";
import { Head, router } from "@inertiajs/react";
import React, { useState, useMemo } from "react";
import {
  Building2,
  Download,
  FileSpreadsheet,
  FileText,
  Search,
  Users,
  RefreshCw,
  ChevronLeft,
  ChevronRight,
  AlertTriangle,
} from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import pdfMake from "pdfmake/build/pdfmake";
import pdfFonts from "pdfmake/build/vfs_fonts";

// Initialize pdfMake fonts
if ((pdfMake as any).vfs === undefined && (pdfFonts as any)?.pdfMake?.vfs) {
  (pdfMake as any).vfs = (pdfFonts as any).pdfMake.vfs;
}

interface AuditRecord {
  staff_id: string;
  name: string;
  company_shop: string;
  company?: string;
}

interface PaginatedRecords {
  data: AuditRecord[];
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

interface SummaryItem {
  client_key: string;
  client_name: string;
  count: number;
}

interface Props {
  records: PaginatedRecords;
  filters: {
    client: string;
    search: string;
  };
  clients: Array<{ value: string; label: string }>;
  summary: SummaryItem[];
  totalRecords: number;
  flash?: {
    success?: string;
    error?: string;
  };
}

export default function BankAuditIndex({
  records,
  filters,
  clients = [],
  summary = [],
  totalRecords = 0,
  flash,
}: Props) {
  const [selectedClient, setSelectedClient] = useState(filters.client || "all");
  const [search, setSearch] = useState(filters.search || "");
  const [isExportingExcel, setIsExportingExcel] = useState(false);
  const [isGeneratingPdf, setIsGeneratingPdf] = useState(false);

  const handleClientChange = (val: string) => {
    setSelectedClient(val);
    router.get(
      route("hr-audit.bank"),
      { client: val, search, page: 1 },
      { preserveState: true, replace: true }
    );
  };

  const handleSearchSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    router.get(
      route("hr-audit.bank"),
      { client: selectedClient, search, page: 1 },
      { preserveState: true, replace: true }
    );
  };

  const handleResetFilters = () => {
    setSelectedClient("all");
    setSearch("");
    router.get(
      route("hr-audit.bank"),
      { client: "all", search: "", page: 1 },
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

  const handleExportExcel = () => {
    setIsExportingExcel(true);
    const params = new URLSearchParams({
      client: selectedClient,
      search: search,
    });
    window.location.href = route("hr-audit.bank.export") + "?" + params.toString();
    setTimeout(() => setIsExportingExcel(false), 2000);
  };

  // Generate General PDF Report matching legacy bank_audit_page.php pdfMake logic
  const handleDownloadGeneralPdf = () => {
    setIsGeneratingPdf(true);
    try {
      const summaryTableBody: any[] = [
        [
          { text: "Client Name", bold: true, fillColor: "#1E40AF", color: "#FFFFFF" },
          { text: "Number of Employees with no Bank Details", bold: true, fillColor: "#1E40AF", color: "#FFFFFF" },
        ],
      ];

      summary.forEach((item) => {
        summaryTableBody.push([item.client_name, item.count.toString()]);
      });

      const docDefinition: any = {
        content: [
          {
            canvas: [
              {
                type: "rect",
                x: 0,
                y: 0,
                w: 515,
                h: 40,
                color: "#3E64D4",
              },
            ],
            absolutePosition: { x: 40, y: 20 },
          },
          {
            text: "BANK AUDIT REPORT",
            style: "headerText",
            absolutePosition: { x: 40, y: 25 },
          },
          {
            text: "Employees With No Bank Details",
            style: "subheader",
            margin: [0, 50, 0, 15],
          },
          {
            table: {
              headerRows: 1,
              widths: ["*", "auto"],
              body: summaryTableBody,
            },
            margin: [0, 0, 0, 20],
          },
        ],
        styles: {
          headerText: {
            fontSize: 18,
            bold: true,
            color: "white",
            alignment: "center",
            width: 515,
          },
          subheader: {
            fontSize: 14,
            bold: true,
            color: "#1E293B",
          },
        },
        defaultStyle: {
          fontSize: 10,
        },
        pageMargins: [40, 60, 40, 60],
      };

      pdfMake.createPdf(docDefinition).download(`Bank_Audit_Report_${new Date().toISOString().split("T")[0]}.pdf`);
    } catch (err) {
      console.error("PDF generation failed:", err);
    } finally {
      setIsGeneratingPdf(false);
    }
  };

  const recordsList = records?.data || [];
  const fromIndex   = records?.from || 1;

  const breadcrumbs = [
    { title: "Dashboard", href: "/dashboard" },
    { title: "HR Audit", href: "/hr-audit/bank" },
    { title: "Bank Audit", href: "/hr-audit/bank" },
  ];

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Bank Audit - Employees With No Bank Details" />

      <div className="space-y-6 p-4 sm:p-6 max-w-7xl mx-auto">
        {/* Header */}
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div>
            <h1 className="text-2xl font-bold tracking-tight text-gray-900 dark:text-gray-100 flex items-center gap-2">
              <Building2 className="h-7 w-7 text-blue-600 dark:text-blue-400" />
              Bank Audit - Employees With No Bank Details
            </h1>
            <p className="text-xs sm:text-sm text-muted-foreground mt-1">
              Audit active staff records missing bank names, account numbers, or branch details across all client operations.
            </p>
          </div>
          <div className="flex flex-wrap items-center gap-2 self-start sm:self-auto">
            <Button
              onClick={handleExportExcel}
              disabled={isExportingExcel}
              variant="outline"
              className="border-emerald-600 text-emerald-700 hover:bg-emerald-50 dark:hover:bg-emerald-950/50 gap-2 shadow-xs text-xs sm:text-sm"
            >
              <FileSpreadsheet className="h-4 w-4 text-emerald-600" />
              {isExportingExcel ? "Exporting..." : "Excel Report"}
            </Button>
            <Button
              onClick={handleDownloadGeneralPdf}
              disabled={isGeneratingPdf}
              className="bg-blue-600 hover:bg-blue-700 text-white gap-2 shadow-xs text-xs sm:text-sm"
            >
              <FileText className="h-4 w-4" />
              {isGeneratingPdf ? "Generating..." : "Download General Report"}
            </Button>
          </div>
        </div>

        {/* Stats Grid */}
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4">
          <Card className="shadow-xs border bg-card">
            <CardContent className="p-4">
              <div className="flex items-center justify-between">
                <p className="text-xs font-medium text-muted-foreground">Total Missing Bank Details</p>
                <AlertTriangle className="h-4 w-4 text-amber-500" />
              </div>
              <p className="text-xl sm:text-2xl font-bold text-amber-600 dark:text-amber-400 mt-1">
                {totalRecords}
              </p>
            </CardContent>
          </Card>

          <Card className="shadow-xs border bg-card">
            <CardContent className="p-4">
              <div className="flex items-center justify-between">
                <p className="text-xs font-medium text-muted-foreground">Selected Client Filter</p>
                <Building2 className="h-4 w-4 text-blue-500" />
              </div>
              <p className="text-base sm:text-lg font-bold text-gray-900 dark:text-gray-100 mt-1 truncate">
                {clients.find((c) => c.value === selectedClient)?.label || "All"}
              </p>
            </CardContent>
          </Card>

          <Card className="shadow-xs border bg-card">
            <CardContent className="p-4">
              <div className="flex items-center justify-between">
                <p className="text-xs font-medium text-muted-foreground">Audited Companies</p>
                <Users className="h-4 w-4 text-indigo-500" />
              </div>
              <p className="text-xl sm:text-2xl font-bold text-indigo-600 dark:text-indigo-400 mt-1">
                {summary.length}
              </p>
            </CardContent>
          </Card>
        </div>

        {/* Filter Controls */}
        <Card className="shadow-sm">
          <CardContent className="p-4 sm:p-5">
            <form onSubmit={handleSearchSubmit} className="flex flex-col sm:flex-row gap-3 items-end">
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 flex-1 w-full">
                <div className="space-y-1">
                  <Label htmlFor="client" className="text-xs font-semibold text-gray-700 dark:text-gray-300">
                    Select Client
                  </Label>
                  <Select value={selectedClient} onValueChange={handleClientChange}>
                    <SelectTrigger id="client" className="h-9 text-xs sm:text-sm">
                      <SelectValue placeholder="Select Client" />
                    </SelectTrigger>
                    <SelectContent>
                      {clients.map((c) => (
                        <SelectItem key={c.value} value={c.value}>
                          {c.label}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>

                <div className="space-y-1">
                  <Label htmlFor="search" className="text-xs font-semibold text-gray-700 dark:text-gray-300">
                    Search Staff ID / Name / Shop
                  </Label>
                  <div className="relative">
                    <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                    <Input
                      id="search"
                      type="text"
                      placeholder="Search..."
                      value={search}
                      onChange={(e) => setSearch(e.target.value)}
                      className="pl-9 h-9 text-xs sm:text-sm"
                    />
                  </div>
                </div>
              </div>

              <div className="flex items-center gap-2 self-stretch sm:self-auto">
                <Button type="submit" className="h-9 text-xs sm:text-sm gap-1.5 flex-1 sm:flex-initial">
                  <Search className="h-3.5 w-3.5" />
                  Search
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
        <Card className="shadow-sm overflow-hidden border">
          <CardHeader className="bg-blue-600 text-white py-3.5 px-4 sm:px-6">
            <div className="flex items-center justify-between">
              <div>
                <CardTitle className="text-base sm:text-lg font-bold tracking-wide">
                  BANK AUDIT
                </CardTitle>
                <CardDescription className="text-xs text-blue-100">
                  Employees missing bank account details
                </CardDescription>
              </div>
              <span className="text-xs font-semibold bg-white/20 px-2.5 py-1 rounded-full">
                {totalRecords} Records
              </span>
            </div>
          </CardHeader>
          <CardContent className="p-0">
            {recordsList.length > 0 ? (
              <div className="overflow-x-auto">
                <Table>
                  <TableHeader>
                    <TableRow className="bg-gray-100/90 dark:bg-gray-800/80 hover:bg-gray-100/90">
                      <TableHead className="font-bold text-xs w-16 text-center">No.</TableHead>
                      <TableHead className="font-bold text-xs">Staff ID</TableHead>
                      <TableHead className="font-bold text-xs">Name</TableHead>
                      <TableHead className="font-bold text-xs">Company / Shop</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {recordsList.map((row, idx) => (
                      <TableRow
                        key={`${row.staff_id}-${idx}`}
                        className="hover:bg-gray-50/80 dark:hover:bg-gray-800/50 text-xs"
                      >
                        <TableCell className="text-center font-mono text-muted-foreground">
                          {fromIndex + idx}
                        </TableCell>
                        <TableCell className="font-mono font-semibold text-blue-700 dark:text-blue-400">
                          {row.staff_id}
                        </TableCell>
                        <TableCell className="font-semibold text-gray-900 dark:text-gray-100">
                          {row.name}
                        </TableCell>
                        <TableCell className="text-gray-600 dark:text-gray-300">
                          {row.company_shop}
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </div>
            ) : (
              <div className="py-12 text-center text-muted-foreground text-sm">
                <Building2 className="h-10 w-10 text-gray-300 mx-auto mb-3" />
                <p className="font-medium">No records found matching bank audit criteria.</p>
                <p className="text-xs text-gray-400 mt-1">
                  All employees in the selected client category have valid bank details recorded.
                </p>
              </div>
            )}

            {/* Pagination Controls */}
            {records?.links && records.links.length > 0 && (
              <div className="flex flex-col sm:flex-row items-center justify-between p-3 sm:p-4 border-t gap-3">
                <div className="text-xs text-muted-foreground text-center sm:text-left">
                  Showing{" "}
                  <span className="font-medium text-foreground">{records.from || 0}</span> to{" "}
                  <span className="font-medium text-foreground">{records.to || 0}</span> of{" "}
                  <span className="font-medium text-foreground">{records.total || 0}</span> entries
                </div>

                <div className="flex flex-wrap items-center justify-center gap-1 max-w-full overflow-x-auto py-1">
                  {records.links.map((link, idx) => {
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
