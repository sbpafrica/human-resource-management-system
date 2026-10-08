import { Head, useForm, router } from "@inertiajs/react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
  Archive,
  Play,
  FileSpreadsheet,
  Download,
  AlertTriangle,
  CheckCircle,
  History,
  Users,
} from "lucide-react";
import AppLayout from "@/layouts/app-layout";
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
import { useState } from "react";

interface ArchiveRunItem {
  id: number;
  payroll_month: string;
  payroll_year: string;
  formatted_period: string;
  archived_count: number;
  held_count: number;
  new_hire_count: number;
  active_count: number;
  duplicate_count: number;
  error_count: number;
  report_filename: string | null;
  run_by_name: string | null;
  created_at: string;
}

interface Props {
  runs: {
    data: ArchiveRunItem[];
    links: any[];
  };
  flash?: {
    success?: string;
    error?: string;
  };
}

const months = [
  "january",
  "february",
  "march",
  "april",
  "may",
  "june",
  "july",
  "august",
  "september",
  "october",
  "november",
  "december",
];

const years = ["23", "24", "25", "26", "27"];

export default function ArchiveRunnerIndex({ runs, flash }: Props) {
  const [selectedMonth, setSelectedMonth] = useState("march");
  const [selectedYear, setSelectedYear] = useState("26");
  const [previewData, setPreviewData] = useState<any>(null);
  const [isLoadingPreview, setIsLoadingPreview] = useState(false);
  const [isRunning, setIsRunning] = useState(false);

  const breadcrumbs = [
    { title: "Database", href: "/employeeportal" },
    { title: "Archive Tool", href: "/archive" },
  ];

  const handlePreview = async () => {
    setIsLoadingPreview(true);
    setPreviewData(null);
    try {
      const res = await fetch(
        `/archive/preview?month=${selectedMonth}&year=${selectedYear}`,
        {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "X-CSRF-TOKEN":
              (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)
                ?.content || "",
          },
        }
      );
      const json = await res.json();
      setPreviewData(json);
    } catch (err) {
      console.error(err);
    } finally {
      setIsLoadingPreview(false);
    }
  };

  const handleRun = () => {
    setIsRunning(true);
    router.post(
      route("archive.run"),
      { month: selectedMonth, year: selectedYear },
      {
        onFinish: () => {
          setIsRunning(false);
          setPreviewData(null);
        },
      }
    );
  };

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Monthly Archive Tool" />

      <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold flex items-center gap-2 text-gray-900">
              <Archive className="h-6 w-6 text-amber-600" />
              Automated Monthly Archive Tool
            </h1>
            <p className="text-sm text-gray-500 mt-1">
              Compare active staff records against monthly client payroll tables to archive inactive
              staff.
            </p>
          </div>
          <Button variant="outline" asChild>
            <a href="/employeearchived">
              <Users className="h-4 w-4 mr-2" />
              View Archived Staff
            </a>
          </Button>
        </div>

        {/* Run / Preview Config Card */}
        <Card>
          <CardHeader>
            <CardTitle className="text-lg">Select Payroll Period</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="flex flex-wrap items-center gap-4">
              <div className="w-48">
                <Select value={selectedMonth} onValueChange={setSelectedMonth}>
                  <SelectTrigger>
                    <SelectValue placeholder="Select Month" />
                  </SelectTrigger>
                  <SelectContent>
                    {months.map((m) => (
                      <SelectItem key={m} value={m}>
                        {m.charAt(0).toUpperCase() + m.slice(1)}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>

              <div className="w-32">
                <Select value={selectedYear} onValueChange={setSelectedYear}>
                  <SelectTrigger>
                    <SelectValue placeholder="Select Year" />
                  </SelectTrigger>
                  <SelectContent>
                    {years.map((y) => (
                      <SelectItem key={y} value={y}>
                        20{y}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>

              <Button
                variant="outline"
                onClick={handlePreview}
                disabled={isLoadingPreview || isRunning}
              >
                <FileSpreadsheet className="h-4 w-4 mr-2 text-blue-600" />
                {isLoadingPreview ? "Analyzing..." : "Preview Archive"}
              </Button>

              <Button
                className="bg-amber-600 hover:bg-amber-700 text-white"
                onClick={handleRun}
                disabled={isLoadingPreview || isRunning}
              >
                <Play className="h-4 w-4 mr-2" />
                {isRunning ? "Running Archive..." : "Execute Live Archive"}
              </Button>
            </div>

            {/* Preview Summary */}
            {previewData && (
              <div className="mt-4 p-4 bg-gray-50 rounded-lg border space-y-3">
                <h3 className="font-semibold text-gray-800">
                  Preview Summary for {previewData.formattedPeriod}
                </h3>
                <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
                  <div className="p-3 bg-white rounded border">
                    <div className="text-gray-500 text-xs">To Be Archived</div>
                    <div className="text-xl font-bold text-red-600">
                      {previewData.summary?.archived || 0}
                    </div>
                  </div>
                  <div className="p-3 bg-white rounded border">
                    <div className="text-gray-500 text-xs">On Hold (Future Start)</div>
                    <div className="text-xl font-bold text-amber-600">
                      {previewData.summary?.held || 0}
                    </div>
                  </div>
                  <div className="p-3 bg-white rounded border">
                    <div className="text-gray-500 text-xs">Unregistered New Hires</div>
                    <div className="text-xl font-bold text-blue-600">
                      {previewData.summary?.new_hires || 0}
                    </div>
                  </div>
                  <div className="p-3 bg-white rounded border">
                    <div className="text-gray-500 text-xs">Active (Untouched)</div>
                    <div className="text-xl font-bold text-green-600">
                      {previewData.summary?.active || 0}
                    </div>
                  </div>
                </div>
              </div>
            )}
          </CardContent>
        </Card>

        {/* History of Past Runs */}
        <Card>
          <CardHeader>
            <CardTitle className="text-lg flex items-center gap-2">
              <History className="h-5 w-5 text-gray-500" />
              Archive Execution History
            </CardTitle>
          </CardHeader>
          <CardContent>
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Period</TableHead>
                  <TableHead>Archived</TableHead>
                  <TableHead>Held</TableHead>
                  <TableHead>New Hires</TableHead>
                  <TableHead>Run By</TableHead>
                  <TableHead>Date Executed</TableHead>
                  <TableHead className="text-right">Report</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {runs.data.length > 0 ? (
                  runs.data.map((r) => (
                    <TableRow key={r.id}>
                      <TableCell className="font-semibold">{r.formatted_period}</TableCell>
                      <TableCell className="text-red-600 font-medium">
                        {r.archived_count}
                      </TableCell>
                      <TableCell className="text-amber-600">{r.held_count}</TableCell>
                      <TableCell className="text-blue-600">{r.new_hire_count}</TableCell>
                      <TableCell>{r.run_by_name || "System"}</TableCell>
                      <TableCell className="text-xs text-gray-500">{r.created_at}</TableCell>
                      <TableCell className="text-right">
                        {r.report_filename ? (
                          <Button variant="outline" size="sm" asChild>
                            <a href={`/archive/report/${r.id}`}>
                              <Download className="h-4 w-4 mr-1 text-emerald-600" />
                              Download
                            </a>
                          </Button>
                        ) : (
                          <span className="text-xs text-gray-400">N/A</span>
                        )}
                      </TableCell>
                    </TableRow>
                  ))
                ) : (
                  <TableRow>
                    <TableCell colSpan={7} className="h-24 text-center text-gray-500">
                      No archive runs recorded yet.
                    </TableCell>
                  </TableRow>
                )}
              </TableBody>
            </Table>
          </CardContent>
        </Card>
      </div>
    </AppLayout>
  );
}
