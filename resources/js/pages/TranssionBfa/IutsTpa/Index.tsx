import { Head, router, useForm } from "@inertiajs/react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Download, FileSpreadsheet, CheckCircle, XCircle } from "lucide-react";
import AppLayout from "@/layouts/app-layout";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { useState, useEffect } from "react";
import ErrorBoundary from "@/components/ErrorBoundary";

interface IutsRecord {
  staffid: string;
  firstname: string | null;
  lastname: string | null;
  fullname: string | null;
  gross: number | null;
  iutsimpos: number | null;
  iutsnet: number | null;
  enfant: string | null;
}

interface Props {
  records: IutsRecord[];
  selectedMonth: string;
  formattedMonth: string;
  totalRecords: number;
  search?: string;
  flash?: {
    success?: string;
    error?: string;
  };
}

const breadcrumbs = [
  { title: "Transsion BFA", href: "#" },
  { title: "IUTS & TPA", href: "/transsion-bfa/iuts-tpa" },
];

export default function IutsTpaIndex({
  records,
  selectedMonth,
  formattedMonth,
  totalRecords,
  search: initialSearch = "",
  flash,
}: Props) {
  const [showToast, setShowToast] = useState(false);
  const [toastMessage, setToastMessage] = useState("");
  const [toastType, setToastType] = useState<"success" | "error">("success");
  const [search, setSearch] = useState(initialSearch);

  const { data, setData } = useForm({
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
      const timeout = toastType === "error" ? 6000 : 3000;
      const timer = setTimeout(() => setShowToast(false), timeout);
      return () => clearTimeout(timer);
    }
  }, [showToast, toastType]);

  const handleMonthChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    setData("month", e.target.value);
    router.get(
      route("transsion-bfa.stat.iuts-tpa"),
      { month: e.target.value, search },
      { preserveState: false, preserveScroll: true }
    );
  };

  const handleSearch = () => {
    router.get(
      route("transsion-bfa.stat.iuts-tpa"),
      { month: data.month, search },
      { preserveState: true, preserveScroll: true }
    );
  };

  const handleExport = () => {
    window.location.href = route("transsion-bfa.stat.iuts-tpa-export", { month: data.month, search });
  };

  return (
    <ErrorBoundary>
      <AppLayout breadcrumbs={breadcrumbs}>
        <Head title="Transsion BFA — IUTS & TPA Report" />

        <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
          {showToast && (
            <div
              className={`fixed top-4 right-4 z-50 flex items-center gap-2 rounded-lg p-4 shadow-lg w-full sm:w-auto max-w-sm ${
                toastType === "success" ? "bg-green-500 text-white" : "bg-red-500 text-white"
              } animate-in fade-in slide-in-from-top-5`}
            >
              {toastType === "success" ? <CheckCircle className="h-5 w-5" /> : <XCircle className="h-5 w-5" />}
              <span>{toastMessage}</span>
              <Button
                variant="ghost"
                size="sm"
                className="ml-auto p-1 text-white hover:text-white hover:bg-black/20"
                onClick={() => setShowToast(false)}
              >
                <XCircle className="h-4 w-4" />
              </Button>
            </div>
          )}

          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
              <h1 className="text-2xl font-bold">Transsion BFA — IUTS & TPA Report</h1>
              <p className="text-sm text-muted-foreground mt-1">
                Impôt Unique sur les Traitements et Salaires (IUTS) tax schedules
              </p>
            </div>

            <div className="flex flex-wrap items-center gap-2">
              <div className="flex items-center gap-2">
                <Label htmlFor="month" className="text-sm whitespace-nowrap">Month:</Label>
                <Input
                  id="month"
                  type="month"
                  value={data.month}
                  onChange={handleMonthChange}
                  className="w-40"
                />
              </div>

              <Button variant="outline" onClick={handleExport} className="gap-1 bg-blue-50 text-blue-700 border-blue-200 hover:bg-blue-100">
                <Download className="h-4 w-4" />
                Export Excel
              </Button>
            </div>
          </div>

          <Card>
            <CardHeader className="pb-3">
              <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                  <CardTitle>IUTS Tax Schedule — {formattedMonth}</CardTitle>
                  <p className="text-xs text-muted-foreground mt-1">
                    Total Records: {totalRecords}
                  </p>
                </div>

                <div className="flex items-center gap-2">
                  <Input
                    placeholder="Search by ID, name..."
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    onKeyDown={(e) => e.key === "Enter" && handleSearch()}
                    className="w-full sm:w-64"
                  />
                  <Button size="sm" onClick={handleSearch}>
                    Search
                  </Button>
                </div>
              </div>
            </CardHeader>
            <CardContent>
              {records && records.length > 0 ? (
                <div className="rounded-md border overflow-x-auto">
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead className="w-12">#</TableHead>
                        <TableHead>Staff ID</TableHead>
                        <TableHead>Full Name</TableHead>
                        <TableHead className="text-right">Salaire Brut</TableHead>
                        <TableHead className="text-right">Base Imposable (IUTS)</TableHead>
                        <TableHead className="text-center">Enfant</TableHead>
                        <TableHead className="text-right font-semibold">IUTS Net</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {records.map((r, idx) => (
                        <TableRow key={r.staffid + idx}>
                          <TableCell>{idx + 1}</TableCell>
                          <TableCell className="font-mono font-medium">{r.staffid}</TableCell>
                          <TableCell>{r.fullname || `${r.firstname || ''} ${r.lastname || ''}`.trim()}</TableCell>
                          <TableCell className="text-right font-mono">
                            {r.gross !== null ? Number(r.gross).toLocaleString() : "—"}
                          </TableCell>
                          <TableCell className="text-right font-mono">
                            {r.iutsimpos !== null ? Number(r.iutsimpos).toLocaleString() : "—"}
                          </TableCell>
                          <TableCell className="text-center">{r.enfant || "0"}</TableCell>
                          <TableCell className="text-right font-mono font-semibold text-emerald-700">
                            {r.iutsnet !== null ? Number(r.iutsnet).toLocaleString() : "—"}
                          </TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                </div>
              ) : (
                <div className="text-center py-12">
                  <FileSpreadsheet className="mx-auto h-12 w-12 text-gray-400" />
                  <h3 className="mt-2 text-sm font-semibold text-gray-900">No IUTS records found</h3>
                  <p className="mt-1 text-sm text-gray-500">
                    No IUTS records recorded for Transsion BFA in {formattedMonth}.
                  </p>
                </div>
              )}
            </CardContent>
          </Card>
        </div>
      </AppLayout>
    </ErrorBoundary>
  );
}
