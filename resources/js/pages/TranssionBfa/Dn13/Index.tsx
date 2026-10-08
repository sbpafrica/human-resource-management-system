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

interface Dn13Record {
  staffid: string;
  firstname: string | null;
  lastname: string | null;
  fullname: string | null;
  basic: number | null;
  cnssno: string | null;
  cnssexonere: number | null;
  cnsssalaire: number | null;
}

interface Props {
  withSsnit: Dn13Record[];
  withoutSsnit: Dn13Record[];
  selectedMonth: string;
  formattedMonth: string;
  totalWithSsnit: number;
  totalWithoutSsnit: number;
  search?: string;
  flash?: {
    success?: string;
    error?: string;
  };
}

const breadcrumbs = [
  { title: "Transsion BFA", href: "#" },
  { title: "DN13 / CNSS", href: "/transsion-bfa/dn13" },
];

export default function Dn13Index({
  withSsnit,
  withoutSsnit,
  selectedMonth,
  formattedMonth,
  totalWithSsnit,
  totalWithoutSsnit,
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
      route("transsion-bfa.stat.dn13"),
      { month: e.target.value, search },
      { preserveState: false, preserveScroll: true }
    );
  };

  const handleSearch = () => {
    router.get(
      route("transsion-bfa.stat.dn13"),
      { month: data.month, search },
      { preserveState: true, preserveScroll: true }
    );
  };

  const handleExport = () => {
    window.location.href = route("transsion-bfa.stat.dn13-export", { month: data.month, search });
  };

  return (
    <ErrorBoundary>
      <AppLayout breadcrumbs={breadcrumbs}>
        <Head title="Transsion BFA — DN13 / CNSS Report" />

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
              <h1 className="text-2xl font-bold">Transsion BFA — DN13 / CNSS Report</h1>
              <p className="text-sm text-muted-foreground mt-1">
                Caisse Nationale de Sécurité Sociale (CNSS) statutory deductions
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

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <Card>
              <CardContent className="p-4">
                <div className="text-sm text-emerald-600 font-medium">Declared With CNSS / SSNIT</div>
                <div className="text-2xl font-bold mt-1 text-emerald-700">{totalWithSsnit}</div>
              </CardContent>
            </Card>
            <Card>
              <CardContent className="p-4">
                <div className="text-sm text-amber-600 font-medium">Without CNSS / SSNIT</div>
                <div className="text-2xl font-bold mt-1 text-amber-700">{totalWithoutSsnit}</div>
              </CardContent>
            </Card>
          </div>

          <Card>
            <CardHeader className="pb-3">
              <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                  <CardTitle>DN13 Schedule — {formattedMonth}</CardTitle>
                  <p className="text-xs text-muted-foreground mt-1">
                    Staff declared to CNSS
                  </p>
                </div>

                <div className="flex items-center gap-2">
                  <Input
                    placeholder="Search by ID, name, CNSS..."
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
              {withSsnit && withSsnit.length > 0 ? (
                <div className="rounded-md border overflow-x-auto">
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead className="w-12">#</TableHead>
                        <TableHead>Staff ID</TableHead>
                        <TableHead>Full Name</TableHead>
                        <TableHead>CNSS Number</TableHead>
                        <TableHead className="text-right">Salaire Brut</TableHead>
                        <TableHead className="text-right font-semibold">CNSS (5.5%)</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {withSsnit.map((r, idx) => (
                        <TableRow key={r.staffid + idx}>
                          <TableCell>{idx + 1}</TableCell>
                          <TableCell className="font-mono font-medium">{r.staffid}</TableCell>
                          <TableCell>{r.fullname || `${r.firstname || ''} ${r.lastname || ''}`.trim()}</TableCell>
                          <TableCell className="font-mono text-xs">{r.cnssno || "—"}</TableCell>
                          <TableCell className="text-right font-mono">
                            {r.basic !== null ? Number(r.basic).toLocaleString() : "—"}
                          </TableCell>
                          <TableCell className="text-right font-mono font-semibold text-emerald-700">
                            {r.cnsssalaire !== null ? Number(r.cnsssalaire).toLocaleString() : "—"}
                          </TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                </div>
              ) : (
                <div className="text-center py-12">
                  <FileSpreadsheet className="mx-auto h-12 w-12 text-gray-400" />
                  <h3 className="mt-2 text-sm font-semibold text-gray-900">No CNSS records found</h3>
                  <p className="mt-1 text-sm text-gray-500">
                    No CNSS declarations recorded for Transsion BFA in {formattedMonth}.
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
