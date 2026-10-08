import { Head, router, Link } from "@inertiajs/react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Download } from "lucide-react";
import AppLayout from "@/layouts/app-layout";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { useState, useEffect } from "react";
import { useForm } from "@inertiajs/react";
import ErrorBoundary from "@/components/ErrorBoundary";
import { CheckCircle, XCircle } from "lucide-react";

interface Record {
  staff_id: string;
  shop: string;
  lastname: string;
  firstname: string;
  othername: string;
  ssnit_number: string;
  gh_card_number: string;
  basic_salary: number | null;
  ssnit_amount: number | null;
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
  flash?: {
    success?: string;
    error?: string;
  };
}

const breadcrumbs = [
  { title: "SSNIT Payment", href: "#" },
  { title: "Avara", href: "/avarassnitpay" },
];

export default function AvaraSsnitPay({ records, selectedMonth, formattedMonth, totalRecords, searchCount, flash }: Props) {
  const [showToast, setShowToast] = useState(false);
  const [toastMessage, setToastMessage] = useState("");
  const [toastType, setToastType] = useState<"success" | "error">("success");
  const [search, setSearch] = useState("");
  const [sort, setSort] = useState("staff_id:asc");

  const { data, setData, errors } = useForm({
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

  const handleSearchSort = () => {
    const [sortColumn, sortOrder] = sort.split(':');
    router.get(
      route('avarassnitpay.index'),
      { month: data.month, search, sort: sortColumn, order: sortOrder },
      { preserveState: true, preserveScroll: true }
    );
  };

  const handleMonthChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    setData("month", e.target.value);
    const [sortColumn, sortOrder] = sort.split(':');
    router.get(
      route('avarassnitpay.index'),
      { month: e.target.value, search, sort: sortColumn, order: sortOrder },
      { preserveState: true, preserveScroll: true }
    );
  };

  const handleSearchChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    setSearch(e.target.value);
  };

  const handleSortChange = (value: string) => {
    setSort(value);
    const [sortColumn, sortOrder] = value.split(':');
    router.get(
      route('avarassnitpay.index'),
      { month: data.month, search, sort: sortColumn, order: sortOrder },
      { preserveState: true, preserveScroll: true }
    );
  };

  const handleExport = async () => {
    try {
      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
      if (!csrfToken) throw new Error('CSRF token missing');

      const [col, dir] = sort.split(':');

      const response = await fetch(route('avarassnitpay.export', {
        month: data.month,
        search: search || undefined,
        sort: col,
        order: dir,
      }), {
        method: 'GET',
        headers: {
          'Accept': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
          'X-CSRF-TOKEN': csrfToken,
        },
      });

      if (!response.ok) throw new Error('Export failed');

      const blob = await response.blob();
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `avara_ssnit_${data.month}.xlsx`;
      a.click();
      window.URL.revokeObjectURL(url);

      setToastMessage('Export successful!');
      setToastType('success');
      setShowToast(true);
    } catch (err) {
      setToastMessage('Export failed');
      setToastType('error');
      setShowToast(true);
    }
  };

  return (
    <ErrorBoundary>
      <AppLayout breadcrumbs={breadcrumbs}>
        <Head title="Avara SSNIT Payment" />
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
              <h1 className="text-2xl font-bold">Avara SSNIT Payment</h1>
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
              </div>
            </div>
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-end gap-4">
              <div className="flex items-center gap-2">
                <Label htmlFor="search">Search:</Label>
                <Input
                  id="search"
                  type="text"
                  value={search}
                  onChange={handleSearchChange}
                  onKeyUp={handleSearchSort}
                  placeholder="Search by Staff ID, Name, SSNIT..."
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
                    <SelectItem value="staff_id:asc">Staff ID (Asc)</SelectItem>
                    <SelectItem value="staff_id:desc">Staff ID (Desc)</SelectItem>
                    <SelectItem value="lastname:asc">Last Name (Asc)</SelectItem>
                    <SelectItem value="lastname:desc">Last Name (Desc)</SelectItem>
                    <SelectItem value="basic_salary:asc">Basic Salary (Asc)</SelectItem>
                    <SelectItem value="basic_salary:desc">Basic Salary (Desc)</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <Button onClick={handleExport} className="w-full sm:w-auto bg-purple-500 hover:bg-purple-600">
                <Download className="h-4 w-4 mr-2" />
                Export to Excel
              </Button>
            </div>
          </div>
          <Card>
            <CardHeader>
              <CardTitle>Avara SSNIT Summary for {formattedMonth}</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="mb-4">
                Showing {records.data.length} of {searchCount ?? totalRecords} records
                {searchCount !== null && ` (filtered from ${totalRecords})`}
              </div>
              {records.data.length > 0 ? (
                <div className="block md:hidden space-y-4">
                  {records.data.map((record, index) => (
                    <div key={`${record.staff_id}-${index}`} className="border rounded-lg p-4 bg-background">
                      <div className="flex flex-col gap-2">
                        <div className="font-semibold">{record.lastname} {record.firstname} {record.othername}</div>
                        <div className="text-sm text-muted-foreground">Staff ID: {record.staff_id}</div>
                        <div className="text-sm text-muted-foreground">Department: {record.shop || "Avara"}</div>
                        <div className="text-sm text-muted-foreground">SSNIT No: {record.ssnit_number || ""}</div>
                        <div className="text-sm text-muted-foreground">Ghana Card: {record.gh_card_number || ""}</div>
                        <div className="text-sm text-muted-foreground">Basic Salary: {record.basic_salary != null ? record.basic_salary : ""}</div>
                        <div className="text-sm text-muted-foreground">SSNIT Amount (13.5%): {record.ssnit_amount != null ? record.ssnit_amount : ""}</div>
                      </div>
                    </div>
                  ))}
                </div>
              ) : (
                <p className="text-center text-gray-500">No records found for the selected month.</p>
              )}
              {records.data.length > 0 && (
                <div className="hidden md:block overflow-x-auto">
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>Staff ID</TableHead>
                        <TableHead>Shop / Dept</TableHead>
                        <TableHead>Last Name</TableHead>
                        <TableHead>First Name</TableHead>
                        <TableHead>Other Name</TableHead>
                        <TableHead>SSNIT Number</TableHead>
                        <TableHead>Ghana Card</TableHead>
                        <TableHead>Basic Salary (GHC)</TableHead>
                        <TableHead>SSNIT Amount (13.5%)</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {records.data.map((record, index) => (
                        <TableRow key={`${record.staff_id}-${index}`}>
                          <TableCell>{record.staff_id}</TableCell>
                          <TableCell>{record.shop || "Avara"}</TableCell>
                          <TableCell>{record.lastname}</TableCell>
                          <TableCell>{record.firstname}</TableCell>
                          <TableCell>{record.othername}</TableCell>
                          <TableCell>{record.ssnit_number}</TableCell>
                          <TableCell>{record.gh_card_number}</TableCell>
                          <TableCell>{record.basic_salary != null ? record.basic_salary : ""}</TableCell>
                          <TableCell>{record.ssnit_amount != null ? record.ssnit_amount : ""}</TableCell>
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
                            data={{ month: data.month, search, sort: sort.split(':')[0], order: sort.split(':')[1] }}
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
