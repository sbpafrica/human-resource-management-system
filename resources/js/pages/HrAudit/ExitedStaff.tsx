import AppLayout from "@/layouts/app-layout";
import { Head, router } from "@inertiajs/react";
import React, { useState } from "react";
import {
  Building2,
  FileSpreadsheet,
  FileText,
  Search,
  RefreshCw,
  ChevronLeft,
  ChevronRight,
  UserMinus,
  CalendarDays,
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
  client_key?: string;
  client_name?: string;
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
    month_range: string;
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

export default function ExitedStaffIndex({
  records,
  filters,
  clients = [],
  summary = [],
  totalRecords = 0,
  flash,
}: Props) {
  const [selectedClient, setSelectedClient] = useState(filters.client || "all");
  const [search, setSearch] = useState(filters.search || "");
  
  const parseInitialMonths = () => {
    if (!filters.month_range) return { single: "", from: "", to: "" };
    const parts = filters.month_range.split("-").map(p => p.trim());
    if (parts.length === 1) return { single: parts[0], from: "", to: "" };
    return { single: "", from: parts[0], to: parts[1] || "" };
  };
  
  const initMonths = parseInitialMonths();
  const [singleMonth, setSingleMonth] = useState(initMonths.single);
  const [rangeFrom, setRangeFrom] = useState(initMonths.from);
  const [rangeTo, setRangeTo] = useState(initMonths.to);
  
  const [isExportingExcel, setIsExportingExcel] = useState(false);
  const [isGeneratingPdf, setIsGeneratingPdf] = useState(false);

  const getMonthRangeStr = () => {
    if (singleMonth) return singleMonth;
    if (rangeFrom && rangeTo) return `${rangeFrom} - ${rangeTo}`;
    return "";
  };

  const validateDates = () => {
    if (singleMonth && (rangeFrom || rangeTo)) {
      alert('Please select either a single month OR a range, not both.');
      return false;
    }
    if (!singleMonth && !rangeFrom && !rangeTo) {
      alert('Please select a month or range.');
      return false;
    }
    if ((rangeFrom && !rangeTo) || (!rangeFrom && rangeTo)) {
      alert('Please complete the range by selecting both "From" and "To" months.');
      return false;
    }
    return true;
  };

  const handleLoadData = (e?: React.FormEvent) => {
    if (e) e.preventDefault();
    if (!validateDates()) return;
    
    router.get(
      route("hr-audit.exited-staff"),
      { client: selectedClient, search, month_range: getMonthRangeStr(), page: 1 },
      { preserveState: true, replace: true }
    );
  };

  const handleResetFilters = () => {
    setSelectedClient("all");
    setSearch("");
    setSingleMonth("");
    setRangeFrom("");
    setRangeTo("");
    router.get(
      route("hr-audit.exited-staff"),
      { client: "all", search: "", month_range: "", page: 1 },
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
    if (!validateDates()) return;
    setIsExportingExcel(true);
    const params = new URLSearchParams({
      client: selectedClient,
      search: search,
      month_range: getMonthRangeStr(),
    });
    window.location.href = route("hr-audit.exited-staff.export") + "?" + params.toString();
    setTimeout(() => setIsExportingExcel(false), 2000);
  };

  const handleDownloadGeneralPdf = () => {
    if (!validateDates()) return;
    setIsGeneratingPdf(true);
    
    try {
      const summaryTableBody: any[] = [
        [
          { text: "Client Name", bold: true, fillColor: "#015ab4", color: "#FFFFFF" },
          { text: "Number of Exited Staff", bold: true, fillColor: "#015ab4", color: "#FFFFFF" },
        ],
      ];

      summary.forEach((item) => {
        summaryTableBody.push([item.client_name, item.count.toString()]);
      });

      const clientDetails: Record<string, AuditRecord[]> = {};
      const allData = records.data || [];
      allData.forEach(record => {
        const cName = record.client_name || "Unknown";
        if (!clientDetails[cName]) clientDetails[cName] = [];
        clientDetails[cName].push(record);
      });

      const detailedContent: any[] = [];
      Object.keys(clientDetails).forEach(clientName => {
        const emps = clientDetails[clientName];
        detailedContent.push({ 
            text: `${clientName} Exited Staff`, 
            style: 'subheader', 
            margin: [0, 20, 0, 10] 
        });

        if (clientName === 'Melcom') {
            const detailTable = {
                table: {
                    headerRows: 1,
                    widths: ['auto', '*', '*'],
                    body: [
                        [
                            { text: 'Staff ID', bold: true },
                            { text: 'Name', bold: true },
                            { text: 'Company / Shop', bold: true }
                        ]
                    ]
                }
            };
            emps.forEach(emp => {
                detailTable.table.body.push([
                    emp.staff_id,
                    emp.name,
                    emp.company_shop
                ]);
            });
            detailedContent.push(detailTable);
        } else {
            const detailTable = {
                table: {
                    headerRows: 1,
                    widths: ['auto', '*'],
                    body: [
                        [
                            { text: 'Staff ID', bold: true },
                            { text: 'Name', bold: true }
                        ]
                    ]
                }
            };
            emps.forEach(emp => {
                detailTable.table.body.push([
                    emp.staff_id,
                    emp.name
                ]);
            });
            detailedContent.push(detailTable);
        }
      });

      const docDefinition: any = {
        pageSize: 'A4',
        pageMargins: [40, 60, 40, 60],
        content: [
            { 
                canvas: [
                    { 
                        type: 'rect', 
                        x: 0, 
                        y: 0, 
                        w: 515, 
                        h: 40, 
                        color: '#015ab4' 
                    }
                ],
                absolutePosition: { x: 40, y: 20 }
            },
            { 
                text: `EXITED STAFF AUDIT FOR ${getMonthRangeStr()}`, 
                style: 'headerText',
                absolutePosition: { x: 40, y: 25 }
            },
            {
                table: {
                    headerRows: 1,
                    widths: ['*', 'auto'],
                    body: summaryTableBody
                },
                margin: [0, 60, 0, 20]
            },
            ...detailedContent
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
                fontSize: 18, 
                bold: true, 
                color: 'white', 
                alignment: 'center',
                width: 515
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

      const safeRange = getMonthRangeStr().replace(' - ', '_to_');
      pdfMake.createPdf(docDefinition).download(`Exited_Staff_Audit_${safeRange}.pdf`);
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
    { title: "Exited Staff", href: "/hr-audit/exited-staff" },
  ];

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Exited Staff Report" />

      <div className="space-y-6 p-4 sm:p-6 max-w-7xl mx-auto">
        {/* Header */}
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div>
            <h1 className="text-2xl font-bold tracking-tight text-gray-900 dark:text-gray-100 flex items-center gap-2">
              <UserMinus className="h-7 w-7 text-rose-600 dark:text-rose-400" />
              Exited Staff Report
            </h1>
            <p className="text-xs sm:text-sm text-muted-foreground mt-1">
              Audit employees who exited during specific payroll periods across all clients.
            </p>
          </div>
          <div className="flex flex-wrap items-center gap-2 self-start sm:self-auto">
            <Button
              onClick={handleExportExcel}
              disabled={isExportingExcel || !filters.month_range}
              variant="outline"
              className="border-emerald-600 text-emerald-700 hover:bg-emerald-50 dark:hover:bg-emerald-950/50 gap-2 shadow-xs text-xs sm:text-sm"
            >
              <FileSpreadsheet className="h-4 w-4 text-emerald-600" />
              {isExportingExcel ? "Exporting..." : "Excel Report"}
            </Button>
            <Button
              onClick={handleDownloadGeneralPdf}
              disabled={isGeneratingPdf || !filters.month_range}
              className="bg-rose-600 hover:bg-rose-700 text-white gap-2 shadow-xs text-xs sm:text-sm"
            >
              <FileText className="h-4 w-4" />
              {isGeneratingPdf ? "Generating..." : "Download General Report"}
            </Button>
          </div>
        </div>

        {/* Filter Controls */}
        <Card className="shadow-sm">
          <CardContent className="p-4 sm:p-5">
            <form onSubmit={handleLoadData} className="flex flex-col gap-4">
              <div className="flex flex-wrap items-end gap-4">
                <div className="space-y-1 w-full sm:w-auto flex-1 min-w-[200px]">
                  <Label htmlFor="client" className="text-xs font-semibold text-gray-700 dark:text-gray-300">
                    Select Client
                  </Label>
                  <Select value={selectedClient} onValueChange={setSelectedClient}>
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

                <div className="space-y-1 w-full sm:w-auto">
                  <Label htmlFor="singleMonth" className="text-xs font-semibold text-gray-700 dark:text-gray-300">
                    Single Month (MM/YYYY)
                  </Label>
                  <div className="relative">
                    <CalendarDays className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                    <Input
                      id="singleMonth"
                      type="text"
                      placeholder="MM/YYYY"
                      value={singleMonth}
                      onChange={(e) => {
                          setSingleMonth(e.target.value);
                          setRangeFrom(''); setRangeTo('');
                      }}
                      className="pl-9 h-9 text-xs sm:text-sm w-32"
                    />
                  </div>
                </div>

                <div className="space-y-1 w-full sm:w-auto">
                  <Label htmlFor="rangeFrom" className="text-xs font-semibold text-gray-700 dark:text-gray-300">
                    Range From
                  </Label>
                  <Input
                    id="rangeFrom"
                    type="text"
                    placeholder="MM/YYYY"
                    value={rangeFrom}
                    onChange={(e) => {
                        setRangeFrom(e.target.value);
                        setSingleMonth('');
                    }}
                    className="h-9 text-xs sm:text-sm w-32"
                  />
                </div>

                <div className="space-y-1 w-full sm:w-auto">
                  <Label htmlFor="rangeTo" className="text-xs font-semibold text-gray-700 dark:text-gray-300">
                    Range To
                  </Label>
                  <Input
                    id="rangeTo"
                    type="text"
                    placeholder="MM/YYYY"
                    value={rangeTo}
                    onChange={(e) => {
                        setRangeTo(e.target.value);
                        setSingleMonth('');
                    }}
                    className="h-9 text-xs sm:text-sm w-32"
                  />
                </div>
              </div>

              <div className="flex flex-wrap items-end gap-4 mt-2 border-t pt-4">
                <div className="space-y-1 flex-1">
                  <Label htmlFor="search" className="text-xs font-semibold text-gray-700 dark:text-gray-300">
                    Search Results
                  </Label>
                  <div className="relative max-w-sm">
                    <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                    <Input
                      id="search"
                      type="text"
                      placeholder="Search Staff ID / Name..."
                      value={search}
                      onChange={(e) => setSearch(e.target.value)}
                      className="pl-9 h-9 text-xs sm:text-sm"
                    />
                  </div>
                </div>
                
                <div className="flex items-center gap-2">
                  <Button type="submit" className="h-9 bg-rose-600 hover:bg-rose-700 text-xs sm:text-sm gap-1.5 text-white">
                    <Search className="h-3.5 w-3.5" />
                    Load Data
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
        {filters.month_range && (
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4">
            <Card className="shadow-xs border bg-card">
              <CardContent className="p-4">
                <div className="flex items-center justify-between">
                  <p className="text-xs font-medium text-muted-foreground">Total Exited Staff</p>
                  <UserMinus className="h-4 w-4 text-rose-500" />
                </div>
                <p className="text-xl sm:text-2xl font-bold text-rose-600 dark:text-rose-400 mt-1">
                  {totalRecords}
                </p>
              </CardContent>
            </Card>

            <Card className="shadow-xs border bg-card">
              <CardContent className="p-4">
                <div className="flex items-center justify-between">
                  <p className="text-xs font-medium text-muted-foreground">Selected Client</p>
                  <Building2 className="h-4 w-4 text-blue-500" />
                </div>
                <p className="text-base sm:text-lg font-bold text-gray-900 dark:text-gray-100 mt-1 truncate">
                  {clients.find((c) => c.value === filters.client)?.label || "All"}
                </p>
              </CardContent>
            </Card>

            <Card className="shadow-xs border bg-card">
              <CardContent className="p-4">
                <div className="flex items-center justify-between">
                  <p className="text-xs font-medium text-muted-foreground">Period</p>
                  <CalendarDays className="h-4 w-4 text-indigo-500" />
                </div>
                <p className="text-sm font-bold text-indigo-600 dark:text-indigo-400 mt-2 truncate">
                  {filters.month_range}
                </p>
              </CardContent>
            </Card>
          </div>
        )}

        {/* Data Table */}
        <Card className="shadow-sm overflow-hidden border">
          <CardHeader className="bg-rose-600 text-white py-3.5 px-4 sm:px-6">
            <div className="flex items-center justify-between">
              <div>
                <CardTitle className="text-base sm:text-lg font-bold tracking-wide">
                  EXITED STAFF
                </CardTitle>
                <CardDescription className="text-xs text-rose-100">
                  {filters.month_range ? `Period: ${filters.month_range}` : "Select a month or range to load data"}
                </CardDescription>
              </div>
              {filters.month_range && (
                <span className="text-xs font-semibold bg-white/20 px-2.5 py-1 rounded-full">
                  {totalRecords} Records
                </span>
              )}
            </div>
          </CardHeader>
          <CardContent className="p-0">
            {!filters.month_range ? (
              <div className="py-12 text-center text-muted-foreground text-sm">
                <CalendarDays className="h-10 w-10 text-gray-300 mx-auto mb-3" />
                <p className="font-medium">No date period selected.</p>
                <p className="text-xs text-gray-400 mt-1">
                  Please enter a month (MM/YYYY) or a date range and click Load Data.
                </p>
              </div>
            ) : recordsList.length > 0 ? (
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
                        <TableCell className="font-mono font-semibold text-rose-700 dark:text-rose-400">
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
                <UserMinus className="h-10 w-10 text-gray-300 mx-auto mb-3" />
                <p className="font-medium">No exited staff found.</p>
                <p className="text-xs text-gray-400 mt-1">
                  Try adjusting your date range or client filter.
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
                          link.active ? "bg-rose-600 hover:bg-rose-700 text-white" : ""
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
