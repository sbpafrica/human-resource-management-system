import AppLayout from "@/layouts/app-layout";
import { Head, router } from "@inertiajs/react";
import React, { useState } from "react";
import {
  FileSpreadsheet,
  FileText,
  Search,
  RefreshCw,
  ChevronLeft,
  ChevronRight,
  Calculator,
  CalendarDays,
  AlertCircle,
  TrendingDown,
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
import pdfMake from "pdfmake/build/pdfmake";
import pdfFonts from "pdfmake/build/vfs_fonts";

if ((pdfMake as any).vfs === undefined && (pdfFonts as any)?.pdfMake?.vfs) {
  (pdfMake as any).vfs = (pdfFonts as any).pdfMake.vfs;
}

interface AuditRecord {
  staff_id: string;
  name: string;
  start_date: string;
  shop: string;
  position: string;
  length_of_service: string;
  current_basic: number;
  entitled_basic: number;
  deficit: number;
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

interface Props {
  records: PaginatedRecords;
  filters: {
    month_year: string;
    search: string;
  };
  totalRecords: number;
  flash?: {
    success?: string;
    error?: string;
  };
}

export default function MelcomBasicIndex({
  records,
  filters,
  totalRecords = 0,
  flash,
}: Props) {
  const [search, setSearch] = useState(filters.search || "");
  const [monthYear, setMonthYear] = useState(filters.month_year || "");
  
  const [isExportingExcel, setIsExportingExcel] = useState(false);
  const [isGeneratingPdf, setIsGeneratingPdf] = useState(false);

  const validateDate = () => {
    if (!monthYear) {
      alert("Please enter a Month/Year (MM/YYYY).");
      return false;
    }
    const regex = /^(0[1-9]|1[0-2])\/\d{4}$/;
    if (!regex.test(monthYear)) {
        alert("Invalid format. Please use MM/YYYY.");
        return false;
    }
    return true;
  };

  const handleLoadData = (e?: React.FormEvent) => {
    if (e) e.preventDefault();
    if (!validateDate()) return;
    
    router.get(
      route("hr-audit.melcom-basic"),
      { search, month_year: monthYear, page: 1 },
      { preserveState: true, replace: true }
    );
  };

  const handleResetFilters = () => {
    setSearch("");
    setMonthYear("");
    router.get(
      route("hr-audit.melcom-basic"),
      { search: "", month_year: "", page: 1 },
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
    if (!validateDate()) return;
    setIsExportingExcel(true);
    const params = new URLSearchParams({
      search: search,
      month_year: monthYear,
    });
    window.location.href = route("hr-audit.melcom-basic.export") + "?" + params.toString();
    setTimeout(() => setIsExportingExcel(false), 2000);
  };

  const handleDownloadGeneralPdf = () => {
    if (!validateDate()) return;
    setIsGeneratingPdf(true);
    
    try {
      const allData = records.data || [];
      
      let currentBasicTotal = 0;
      let entitledBasicTotal = 0;
      let deficitTotal = 0;

      const detailTable = {
          table: {
              headerRows: 1,
              widths: ['auto', '*', 'auto', '*', 'auto', 'auto', 'auto'],
              body: [
                  [
                      { text: 'Staff ID', bold: true, fontSize: 9 },
                      { text: 'Name', bold: true, fontSize: 9 },
                      { text: 'Shop', bold: true, fontSize: 9 },
                      { text: 'LOS', bold: true, fontSize: 9 },
                      { text: 'Current', bold: true, fontSize: 9 },
                      { text: 'Entitled', bold: true, fontSize: 9 },
                      { text: 'Deficit', bold: true, fontSize: 9 }
                  ]
              ]
          }
      };

      allData.forEach(emp => {
          currentBasicTotal += emp.current_basic;
          entitledBasicTotal += emp.entitled_basic;
          deficitTotal += emp.deficit;

          detailTable.table.body.push([
              { text: emp.staff_id, fontSize: 8 },
              { text: emp.name, fontSize: 8 },
              { text: emp.shop, fontSize: 8 },
              { text: emp.length_of_service, fontSize: 8 },
              { text: emp.current_basic.toFixed(2), fontSize: 8 },
              { text: emp.entitled_basic.toFixed(2), fontSize: 8 },
              { text: emp.deficit.toFixed(2), fontSize: 8, color: 'red' }
          ] as any);
      });

      // Totals Row
      detailTable.table.body.push([
          { text: 'TOTALS', bold: true, fontSize: 9, colSpan: 4 } as any,
          {}, {}, {},
          { text: currentBasicTotal.toFixed(2), bold: true, fontSize: 9 } as any,
          { text: entitledBasicTotal.toFixed(2), bold: true, fontSize: 9 } as any,
          { text: deficitTotal.toFixed(2), bold: true, fontSize: 9, color: 'red' } as any
      ]);

      const docDefinition: any = {
        pageSize: 'A4',
        pageMargins: [30, 60, 30, 60],
        content: [
            { 
                canvas: [
                    { 
                        type: 'rect', 
                        x: 0, 
                        y: 0, 
                        w: 535, 
                        h: 40, 
                        color: '#D97706' // amber-600
                    }
                ],
                absolutePosition: { x: 30, y: 20 }
            },
            { 
                text: `MELCOM BASIC SALARY AUDIT (${monthYear})`, 
                style: 'headerText',
                absolutePosition: { x: 30, y: 25 }
            },
            {
                text: "Underpaid Employees Report",
                style: 'subheader',
                margin: [0, 50, 0, 10]
            },
            detailTable
        ],
        footer: function(currentPage: number, pageCount: number) {
            return {
                text: `Page ${currentPage} of ${pageCount}`,
                alignment: 'center',
                fontSize: 8,
                margin: [0, 10]
            };
        },
        styles: {
            headerText: { 
                fontSize: 16, 
                bold: true, 
                color: 'white', 
                alignment: 'center',
                width: 535
            },
            subheader: { 
                fontSize: 14, 
                bold: true 
            }
        },
        defaultStyle: {
            fontSize: 10
        }
      };

      const safeRange = monthYear.replace('/', '_');
      pdfMake.createPdf(docDefinition).download(`Melcom_Underpaid_Audit_${safeRange}.pdf`);
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
    { title: "Melcom Basic", href: "/hr-audit/melcom-basic" },
  ];

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Melcom Basic Report" />

      <div className="space-y-6 p-4 sm:p-6 max-w-7xl mx-auto">
        {/* Header */}
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div>
            <h1 className="text-2xl font-bold tracking-tight text-gray-900 dark:text-gray-100 flex items-center gap-2">
              <Calculator className="h-7 w-7 text-amber-600 dark:text-amber-400" />
              Melcom Basic Audit
            </h1>
            <p className="text-xs sm:text-sm text-muted-foreground mt-1">
              Audit Melcom employee basic salaries against entitled scale based on length of service.
            </p>
          </div>
          <div className="flex flex-wrap items-center gap-2 self-start sm:self-auto">
            <Button
              onClick={handleExportExcel}
              disabled={isExportingExcel || !filters.month_year}
              variant="outline"
              className="border-emerald-600 text-emerald-700 hover:bg-emerald-50 dark:hover:bg-emerald-950/50 gap-2 shadow-xs text-xs sm:text-sm"
            >
              <FileSpreadsheet className="h-4 w-4 text-emerald-600" />
              {isExportingExcel ? "Exporting..." : "Excel Report"}
            </Button>
            <Button
              onClick={handleDownloadGeneralPdf}
              disabled={isGeneratingPdf || !filters.month_year}
              className="bg-amber-600 hover:bg-amber-700 text-white gap-2 shadow-xs text-xs sm:text-sm"
            >
              <FileText className="h-4 w-4" />
              {isGeneratingPdf ? "Generating..." : "Download PDF Report"}
            </Button>
          </div>
        </div>

        {flash?.error && (
            <div className="bg-red-50 border-l-4 border-red-500 p-4 text-red-700 flex items-start gap-3 rounded-r-md">
                <AlertCircle className="h-5 w-5 flex-shrink-0" />
                <p className="text-sm">{flash.error}</p>
            </div>
        )}

        {/* Filter Controls */}
        <Card className="shadow-sm">
          <CardContent className="p-4 sm:p-5">
            <form onSubmit={handleLoadData} className="flex flex-col gap-4">
              <div className="flex flex-wrap items-end gap-4">
                
                <div className="space-y-1 w-full sm:w-auto flex-1 max-w-[200px]">
                  <Label htmlFor="monthYear" className="text-xs font-semibold text-gray-700 dark:text-gray-300">
                    Month/Year (MM/YYYY)
                  </Label>
                  <div className="relative">
                    <CalendarDays className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                    <Input
                      id="monthYear"
                      type="text"
                      placeholder="MM/YYYY"
                      value={monthYear}
                      onChange={(e) => setMonthYear(e.target.value)}
                      className="pl-9 h-9 text-xs sm:text-sm"
                    />
                  </div>
                </div>

                <div className="space-y-1 flex-1 min-w-[200px]">
                  <Label htmlFor="search" className="text-xs font-semibold text-gray-700 dark:text-gray-300">
                    Search Results
                  </Label>
                  <div className="relative">
                    <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                    <Input
                      id="search"
                      type="text"
                      placeholder="Search Staff ID / Name / Shop..."
                      value={search}
                      onChange={(e) => setSearch(e.target.value)}
                      className="pl-9 h-9 text-xs sm:text-sm"
                    />
                  </div>
                </div>
                
                <div className="flex items-center gap-2">
                  <Button type="submit" className="h-9 bg-amber-600 hover:bg-amber-700 text-xs sm:text-sm gap-1.5 text-white">
                    <Calculator className="h-3.5 w-3.5" />
                    Check Data
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
              </div>
            </form>
          </CardContent>
        </Card>

        {/* Stats Grid */}
        {filters.month_year && !flash?.error && (
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
            <Card className="shadow-xs border bg-card">
              <CardContent className="p-4">
                <div className="flex items-center justify-between">
                  <p className="text-xs font-medium text-muted-foreground">Underpaid Employees</p>
                  <AlertCircle className="h-4 w-4 text-amber-500" />
                </div>
                <p className="text-xl sm:text-2xl font-bold text-amber-600 dark:text-amber-400 mt-1">
                  {totalRecords}
                </p>
              </CardContent>
            </Card>

            <Card className="shadow-xs border bg-card">
              <CardContent className="p-4">
                <div className="flex items-center justify-between">
                  <p className="text-xs font-medium text-muted-foreground">Payroll Period</p>
                  <CalendarDays className="h-4 w-4 text-indigo-500" />
                </div>
                <p className="text-base sm:text-lg font-bold text-indigo-600 dark:text-indigo-400 mt-1 truncate">
                  {filters.month_year}
                </p>
              </CardContent>
            </Card>
          </div>
        )}

        {/* Data Table */}
        <Card className="shadow-sm overflow-hidden border">
          <CardHeader className="bg-amber-600 text-white py-3.5 px-4 sm:px-6">
            <div className="flex items-center justify-between">
              <div>
                <CardTitle className="text-base sm:text-lg font-bold tracking-wide">
                  UNDERPAID MELCOM STAFF
                </CardTitle>
                <CardDescription className="text-xs text-amber-100">
                  {filters.month_year ? `Period: ${filters.month_year}` : "Select a month/year to run audit"}
                </CardDescription>
              </div>
              {filters.month_year && !flash?.error && (
                <span className="text-xs font-semibold bg-white/20 px-2.5 py-1 rounded-full flex items-center gap-1">
                  <TrendingDown className="h-3 w-3" />
                  {totalRecords} Found
                </span>
              )}
            </div>
          </CardHeader>
          <CardContent className="p-0">
            {!filters.month_year ? (
              <div className="py-12 text-center text-muted-foreground text-sm">
                <Calculator className="h-10 w-10 text-gray-300 mx-auto mb-3" />
                <p className="font-medium">No payroll period selected.</p>
                <p className="text-xs text-gray-400 mt-1">
                  Please enter a month (MM/YYYY) and click Check Data.
                </p>
              </div>
            ) : recordsList.length > 0 ? (
              <div className="overflow-x-auto">
                <Table>
                  <TableHeader>
                    <TableRow className="bg-gray-100/90 dark:bg-gray-800/80 hover:bg-gray-100/90">
                      <TableHead className="font-bold text-xs w-12 text-center">No.</TableHead>
                      <TableHead className="font-bold text-xs">Staff ID</TableHead>
                      <TableHead className="font-bold text-xs">Name</TableHead>
                      <TableHead className="font-bold text-xs">Shop</TableHead>
                      <TableHead className="font-bold text-xs">Service</TableHead>
                      <TableHead className="font-bold text-xs text-right">Current</TableHead>
                      <TableHead className="font-bold text-xs text-right">Entitled</TableHead>
                      <TableHead className="font-bold text-xs text-right text-rose-600">Deficit</TableHead>
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
                        <TableCell className="font-mono font-semibold text-amber-700 dark:text-amber-400">
                          {row.staff_id}
                        </TableCell>
                        <TableCell className="font-semibold text-gray-900 dark:text-gray-100">
                          {row.name}
                        </TableCell>
                        <TableCell className="text-gray-600 dark:text-gray-300">
                          {row.shop}
                        </TableCell>
                        <TableCell className="text-gray-600 dark:text-gray-300 font-medium">
                          {row.length_of_service}
                        </TableCell>
                        <TableCell className="text-right font-mono text-gray-600">
                          {Number(row.current_basic).toFixed(2)}
                        </TableCell>
                        <TableCell className="text-right font-mono font-semibold text-emerald-600">
                          {Number(row.entitled_basic).toFixed(2)}
                        </TableCell>
                        <TableCell className="text-right font-mono font-bold text-rose-600">
                          {Number(row.deficit).toFixed(2)}
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </div>
            ) : flash?.error ? (
                <div className="py-12 text-center text-muted-foreground text-sm">
                <AlertCircle className="h-10 w-10 text-gray-300 mx-auto mb-3" />
                <p className="font-medium text-rose-600">{flash.error}</p>
              </div>
            ) : (
              <div className="py-12 text-center text-muted-foreground text-sm">
                <Calculator className="h-10 w-10 text-gray-300 mx-auto mb-3" />
                <p className="font-medium">No underpaid employees found.</p>
                <p className="text-xs text-gray-400 mt-1">
                  Everyone appears to be receiving their entitled basic salary.
                </p>
              </div>
            )}

            {/* Pagination Controls */}
            {records?.links && records.links.length > 0 && recordsList.length > 0 && (
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
                          link.active ? "bg-amber-600 hover:bg-amber-700 text-white" : ""
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
