// resources/js/pages/SsnitPayment/Client.tsx

import { Head, router, Link } from "@inertiajs/react";  // ← ADD Link HERE
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Download } from "lucide-react";
import AppLayout from "@/layouts/app-layout";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { useState, useEffect } from "react";
import { CheckCircle, XCircle } from "lucide-react";
import * as XLSX from "xlsx";

interface Record {
  staff_id: string;
  shop: string | null;
  full_name: string;
  ssnit_number: string | null;
  gh_card_number: string | null;
  basic: string;
  ssnit_amount: string;
  missing_ssnit: number;
}

interface Props {
  client: any;
  records: {
    data: Record[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
    total: number;
  };
  selectedMonth: string;
  formattedMonth: string;
  totalRecords: number;
  missingSsnitCount: number;
  searchCount: number | null;
  flash?: { success?: string; error?: string };
}

export default function SsnitClient({
  client,
  records,
  selectedMonth,
  formattedMonth,
  totalRecords,
  missingSsnitCount,
  searchCount,
  flash,
}: Props) {
  const [search, setSearch] = useState("");
  const [sort, setSort] = useState("staff_id:asc");
  const [month, setMonth] = useState(selectedMonth);
  const [showToast, setShowToast] = useState(false);
  const [toastMessage, setToastMessage] = useState("");
  const [toastType, setToastType] = useState<"success" | "error">("success");

  useEffect(() => {
    if (flash?.success || flash?.error) {
      setToastMessage(flash.success || flash.error || "");
      setToastType(flash.success ? "success" : "error");
      setShowToast(true);
    }
  }, [flash]);

  useEffect(() => {
    const timer = setTimeout(() => setShowToast(false), toastType === "error" ? 10000 : 4000);
    return () => clearTimeout(timer);
  }, [showToast]);

  const handleSearchSort = () => {
    const [col, ord] = sort.split(":");
    router.get(route("ssnitpayment.client", client.client_dbname), {
      month, search, sort: col, order: ord
    }, { preserveState: true, preserveScroll: true });
  };

  const handleMonthChange = (e: any) => {
    const newMonth = e.target.value;
    setMonth(newMonth);
    const [col, ord] = sort.split(":");
    router.get(route("ssnitpayment.client", client.client_dbname), {
      month: newMonth, search, sort: col, order: ord
    }, { preserveState: true, preserveScroll: true });
  };

  const handleSortChange = (value: string) => {
    setSort(value);
    const [col, ord] = value.split(":");
    router.get(route("ssnitpayment.client", client.client_dbname), {
      month, search, sort: col, order: ord
    }, { preserveState: true, preserveScroll: true });
  };

  const handleExport = async () => {
    try {
      const [col, ord] = sort.split(":");
      const url = route('ssnitpayment.export', { client_dbname: client.client_dbname });
      const fullUrl = `${url}?month=${month}&search=${encodeURIComponent(search)}&sort=${col}&order=${ord}`;

      const response = await fetch(fullUrl, {
        method: 'GET',
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        },
      });

      if (!response.ok) {
        const err = await response.json();
        throw new Error(err.error || 'Export failed');
      }

      const data = await response.json();

      const headers = ["Staff ID", "Shop/Location", "Full Name", "SSNIT Number", "GH Card", "Basic Salary", "SSNIT 13.5%"];
      const rows = data.map((r: any) => [
        r.staff_id,
        r.shop,
        r.full_name,
        r.ssnit_number,
        r.gh_card_number,
        r.basic,
        r.ssnit_amount,
      ]);

      const wb = XLSX.utils.book_new();
      const ws = XLSX.utils.aoa_to_sheet([headers, ...rows]);
      ws['!cols'] = headers.map(() => ({ wch: 22 }));

      const range = XLSX.utils.decode_range(ws['!ref']!);
      for (let C = range.s.c; C <= range.e.c; ++C) {
        const addr = XLSX.utils.encode_cell({ r: 0, c: C });
        if (ws[addr]) ws[addr].s = { font: { bold: true }, alignment: { horizontal: "left" } };
      }

      XLSX.utils.book_append_sheet(wb, ws, "SSNIT");
      XLSX.writeFile(wb, `${client.short_name}_SSNIT_${month.replace(/-/g, '')}.xlsx`);

      setToastMessage("Export successful!");
      setToastType("success");
      setShowToast(true);

    } catch (err: any) {
      setToastMessage(err.message || "Export failed");
      setToastType("error");
      setShowToast(true);
    }
  };

  return (
    <AppLayout breadcrumbs={[{ title: "SSNIT", href: "/ssnit-payment" }, { title: client.short_name, href: "#" }]}>
      <Head title={`${client.short_name} - SSNIT Contribution`} />

      {showToast && (
        <div className={`fixed top-4 right-4 z-50 flex items-center gap-2 rounded-lg p-4 shadow-lg ${toastType === "success" ? "bg-green-500" : "bg-red-500"} text-white animate-in fade-in slide-in-from-top-5`}>
          {toastType === "success" ? <CheckCircle className="h-5 w-5" /> : <XCircle className="h-5 w-5" />}
          <span>{toastMessage}</span>
        </div>
      )}

      <div className="p-6">
        <div className="flex flex-col gap-6">
          <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <h1 className="text-2xl font-bold">{client.short_name} - SSNIT Contribution</h1>
            <div className="flex items-center gap-3">
              <Label>Month:</Label>
              <Input type="month" value={month} onChange={handleMonthChange} className="w-48" />
            </div>
          </div>

          <div className="flex flex-col sm:flex-row justify-end gap-4">
            <div className="flex items-center gap-2">
              <Label>Search:</Label>
              <Input value={search} onChange={(e) => setSearch(e.target.value)} onKeyUp={(e) => e.key === "Enter" && handleSearchSort()} placeholder="Staff ID, Name, SSNIT, GH Card..." className="w-64" />
            </div>
            <Select value={sort} onValueChange={handleSortChange}>
              <SelectTrigger className="w-64">
                <SelectValue placeholder="Sort by..." />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="staff_id:asc">Staff ID (A-Z)</SelectItem>
                <SelectItem value="staff_id:desc">Staff ID (Z-A)</SelectItem>
                <SelectItem value="full_name:asc">Full Name (A-Z)</SelectItem>
                <SelectItem value="full_name:desc">Full Name (Z-A)</SelectItem>
                <SelectItem value="ssnit_number:asc">SSNIT Number (A-Z)</SelectItem>
                <SelectItem value="ssnit_number:desc">SSNIT Number (Z-A)</SelectItem>
                <SelectItem value="gh_card_number:asc">GH Card (A-Z)</SelectItem>
                <SelectItem value="gh_card_number:desc">GH Card (Z-A)</SelectItem>
                <SelectItem value="basic:desc">Basic Salary (High → Low)</SelectItem>
                <SelectItem value="basic:asc">Basic Salary (Low → High)</SelectItem>
                <SelectItem value="ssnit_amount:desc">SSNIT 13.5% (High → Low)</SelectItem>
              </SelectContent>
            </Select>
            <Button onClick={handleExport} className="bg-purple-600 hover:bg-purple-700">
              <Download className="h-4 w-4 mr-2" /> Export to Excel
            </Button>
          </div>

          <Card>
            <CardHeader>
              <CardTitle>SSNIT Summary - {formattedMonth}</CardTitle>
              <p className="text-sm text-muted-foreground">
                Showing {records.data.length} of {searchCount ?? totalRecords} records
                {missingSsnitCount > 0 && ` • ${missingSsnitCount} missing SSNIT number(s)`}
              </p>
            </CardHeader>
            <CardContent>
              {records.data.length > 0 ? (
                <>
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>
                          <Button variant="ghost" onClick={() => handleSortChange(`staff_id:${sort.startsWith('staff_id') && sort.endsWith('asc') ? 'desc' : 'asc'}`)}>
                            Staff ID {sort.includes('staff_id') && (sort.endsWith('asc') ? '↑' : '↓')}
                          </Button>
                        </TableHead>
                        <TableHead>Shop/Location</TableHead>
                        <TableHead>
                          <Button variant="ghost" onClick={() => handleSortChange(`full_name:${sort.startsWith('full_name') && sort.endsWith('asc') ? 'desc' : 'asc'}`)}>
                            Full Name {sort.includes('full_name') && (sort.endsWith('asc') ? '↑' : '↓')}
                          </Button>
                        </TableHead>
                        <TableHead>
                          <Button variant="ghost" onClick={() => handleSortChange(`ssnit_number:${sort.startsWith('ssnit_number') && sort.endsWith('asc') ? 'desc' : 'asc'}`)}>
                            SSNIT Number {sort.includes('ssnit_number') && (sort.endsWith('asc') ? '↑' : '↓')}
                          </Button>
                        </TableHead>
                        <TableHead>
                          <Button variant="ghost" onClick={() => handleSortChange(`gh_card_number:${sort.startsWith('gh_card_number') && sort.endsWith('asc') ? 'desc' : 'asc'}`)}>
                            GH Card {sort.includes('gh_card_number') && (sort.endsWith('asc') ? '↑' : '↓')}
                          </Button>
                        </TableHead>
                        <TableHead>
                          <Button variant="ghost" onClick={() => handleSortChange(`basic:${sort.startsWith('basic') && sort.endsWith('desc') ? 'asc' : 'desc'}`)}>
                            Basic (GHS) {sort.includes('basic') && (sort.endsWith('desc') ? '↓' : '↑')}
                          </Button>
                        </TableHead>
                        <TableHead>
                          <Button variant="ghost" onClick={() => handleSortChange(`ssnit_amount:${sort.startsWith('ssnit_amount') && sort.endsWith('desc') ? 'asc' : 'desc'}`)}>
                            SSNIT 13.5% {sort.includes('ssnit_amount') && (sort.endsWith('desc') ? '↓' : '↑')}
                          </Button>
                        </TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {records.data.map((r) => (
                        <TableRow key={r.staff_id} className={r.missing_ssnit ? "bg-red-50" : ""}>
                          <TableCell>{r.staff_id}</TableCell>
                          <TableCell>{r.shop || ""}</TableCell>
                          <TableCell>{r.full_name}</TableCell>
                          <TableCell>{r.ssnit_number || ""}</TableCell>
                          <TableCell>{r.gh_card_number || ""}</TableCell>
                          <TableCell>{r.basic}</TableCell>
                          <TableCell>{r.ssnit_amount}</TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>

                  {/* FIXED PAGINATION WITH <Link> */}
                  {records.links && records.links.length > 3 && (
                    <div className="flex justify-center mt-6 gap-2">
                      {records.links.map((link, i) => (
                        <Button
                          key={i}
                          variant={link.active ? "default" : "outline"}
                          size="sm"
                          disabled={!link.url}
                          asChild={!!link.url}
                        >
                          {link.url ? (
                            <Link
                              href={link.url}
                              preserveState
                              preserveScroll
                              data={{ month, search, sort: sort.split(":")[0], order: sort.split(":")[1] }}
                              dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                          ) : (
                            <span dangerouslySetInnerHTML={{ __html: link.label }} />
                          )}
                        </Button>
                      ))}
                    </div>
                  )}
                </>
              ) : (
                <p className="text-center py-12 text-gray-500">No records found for {formattedMonth}.</p>
              )}
            </CardContent>
          </Card>
        </div>
      </div>
    </AppLayout>
  );
}
