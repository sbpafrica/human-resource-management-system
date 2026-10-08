import { Head, router, useForm } from "@inertiajs/react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Plus, Pencil, Trash, CheckCircle, XCircle, Download, Upload, ArrowUpDown, FileSpreadsheet } from "lucide-react";
import AppLayout from "@/layouts/app-layout";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger, DialogFooter } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { useState, useEffect } from "react";
import ErrorBoundary from "@/components/ErrorBoundary";

interface BankRecord {
  staffida: string;
  employname: string;
  bankname: string;
  accountnum: string;
}

interface Props {
  banks: {
    data: BankRecord[];
    current_page: number;
    last_page: number;
    total: number;
    links: Array<{ url: string | null; label: string; active: boolean }>;
    from?: number;
  };
  filters: {
    search?: string;
    sort?: string;
    order?: string;
  };
  totalRecords: number;
  searchCount: number | null;
  flash?: {
    success?: string;
    error?: string;
  };
}

const breadcrumbs = [
  { title: "Transsion BFA", href: "#" },
  { title: "Bank Details", href: "/transsion-bfa/bank" },
];

export default function TranssionBfaBankIndex({
  banks,
  filters,
  totalRecords,
  searchCount,
  flash,
}: Props) {
  const [showToast, setShowToast] = useState(false);
  const [toastMessage, setToastMessage] = useState("");
  const [toastType, setToastType] = useState<"success" | "error">("success");
  const [search, setSearch] = useState(filters.search || "");
  const [isOpen, setIsOpen] = useState(false);
  const [isBulkOpen, setIsBulkOpen] = useState(false);
  const [editingBank, setEditingBank] = useState<BankRecord | null>(null);

  const { data, setData, post, put, delete: destroy, processing, reset, errors } = useForm({
    staffida: "",
    employname: "",
    bankname: "",
    accountnum: "",
  });

  const bulkForm = useForm({
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
    if (showToast) {
      const timeout = toastType === "error" ? 6000 : 3000;
      const timer = setTimeout(() => setShowToast(false), timeout);
      return () => clearTimeout(timer);
    }
  }, [showToast, toastType]);

  const handleSearch = () => {
    router.get(
      route("transsion-bfa.bank.index"),
      { search, sort: filters.sort, order: filters.order },
      { preserveState: true, preserveScroll: true }
    );
  };

  const handleEdit = (b: BankRecord) => {
    setEditingBank(b);
    setData({
      staffida: b.staffida,
      employname: b.employname,
      bankname: b.bankname,
      accountnum: b.accountnum,
    });
    setIsOpen(true);
  };

  const handleSubmit = (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    if (editingBank) {
      put(route("transsion-bfa.bank.update", editingBank.staffida), {
        onSuccess: () => {
          setIsOpen(false);
          reset();
          setEditingBank(null);
        },
      });
    } else {
      post(route("transsion-bfa.bank.store"), {
        onSuccess: () => {
          setIsOpen(false);
          reset();
        },
      });
    }
  };

  const handleDelete = (staffida: string) => {
    if (confirm("Are you sure you want to delete this bank record?")) {
      destroy(route("transsion-bfa.bank.destroy", staffida));
    }
  };

  const handleBulkUpload = (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    if (bulkForm.data.file) {
      bulkForm.post(route("transsion-bfa.bank.bulk-upload"), {
        forceFormData: true,
        onSuccess: () => {
          setIsBulkOpen(false);
          bulkForm.reset();
        },
      });
    }
  };

  return (
    <ErrorBoundary>
      <AppLayout breadcrumbs={breadcrumbs}>
        <Head title="Transsion BFA Bank Details" />

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
              <h1 className="text-2xl font-bold">Transsion BFA Bank Details</h1>
              <p className="text-sm text-muted-foreground mt-1">
                Banking and account numbers for Burkina Faso employees
              </p>
            </div>

            <div className="flex flex-wrap items-center gap-2">
              <Dialog open={isOpen} onOpenChange={(open) => { setIsOpen(open); if (!open) { reset(); setEditingBank(null); } }}>
                <DialogTrigger asChild>
                  <Button className="gap-1">
                    <Plus className="h-4 w-4" />
                    Add Bank Detail
                  </Button>
                </DialogTrigger>
                <DialogContent>
                  <DialogHeader>
                    <DialogTitle>{editingBank ? "Edit Bank Detail" : "Add Bank Detail"}</DialogTitle>
                  </DialogHeader>
                  <form onSubmit={handleSubmit} className="space-y-4">
                    <div>
                      <Label htmlFor="staffida">Staff ID</Label>
                      <Input
                        id="staffida"
                        value={data.staffida}
                        onChange={(e) => setData("staffida", e.target.value)}
                        disabled={!!editingBank}
                        required
                        className="mt-1"
                      />
                      {errors.staffida && <p className="text-xs text-red-500 mt-1">{errors.staffida}</p>}
                    </div>
                    <div>
                      <Label htmlFor="employname">Employee Name</Label>
                      <Input
                        id="employname"
                        value={data.employname}
                        onChange={(e) => setData("employname", e.target.value)}
                        required
                        className="mt-1"
                      />
                    </div>
                    <div>
                      <Label htmlFor="bankname">Bank Name</Label>
                      <Input
                        id="bankname"
                        value={data.bankname}
                        onChange={(e) => setData("bankname", e.target.value)}
                        required
                        className="mt-1"
                      />
                    </div>
                    <div>
                      <Label htmlFor="accountnum">Account Number</Label>
                      <Input
                        id="accountnum"
                        value={data.accountnum}
                        onChange={(e) => setData("accountnum", e.target.value)}
                        required
                        className="mt-1"
                      />
                    </div>
                    <DialogFooter>
                      <Button type="button" variant="outline" onClick={() => setIsOpen(false)}>
                        Cancel
                      </Button>
                      <Button type="submit" disabled={processing}>
                        {editingBank ? "Save Changes" : "Create"}
                      </Button>
                    </DialogFooter>
                  </form>
                </DialogContent>
              </Dialog>

              <Button
                variant="outline"
                onClick={() => { window.location.href = route("transsion-bfa.bank.download-template"); }}
                className="gap-1"
              >
                <FileSpreadsheet className="h-4 w-4" />
                Template
              </Button>

              <Dialog open={isBulkOpen} onOpenChange={setIsBulkOpen}>
                <DialogTrigger asChild>
                  <Button variant="outline" className="gap-1 bg-green-50 text-green-700 border-green-200 hover:bg-green-100">
                    <Upload className="h-4 w-4 text-green-600" />
                    Bulk Upload
                  </Button>
                </DialogTrigger>
                <DialogContent>
                  <DialogHeader>
                    <DialogTitle>Bulk Upload Bank Details</DialogTitle>
                  </DialogHeader>
                  <form onSubmit={handleBulkUpload} className="space-y-4">
                    <div>
                      <Label htmlFor="bulk-file">Excel File</Label>
                      <Input
                        id="bulk-file"
                        type="file"
                        accept=".xlsx,.xls"
                        onChange={(e) => {
                          const file = e.target.files?.[0];
                          if (file) bulkForm.setData("file", file);
                        }}
                        required
                        className="mt-1"
                      />
                    </div>
                    <DialogFooter>
                      <Button type="button" variant="outline" onClick={() => setIsBulkOpen(false)}>
                        Cancel
                      </Button>
                      <Button type="submit" disabled={bulkForm.processing || !bulkForm.data.file}>
                        Upload
                      </Button>
                    </DialogFooter>
                  </form>
                </DialogContent>
              </Dialog>

              <Button
                variant="outline"
                onClick={() => { window.location.href = route("transsion-bfa.bank.export"); }}
                className="gap-1 bg-blue-50 text-blue-700 border-blue-200 hover:bg-blue-100"
              >
                <Download className="h-4 w-4" />
                Export
              </Button>
            </div>
          </div>

          <Card>
            <CardHeader className="pb-3">
              <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                  <CardTitle>Bank Records</CardTitle>
                  <p className="text-xs text-muted-foreground mt-1">
                    {searchCount !== null
                      ? `Showing ${searchCount} of ${totalRecords} matching records`
                      : `Total Records: ${totalRecords}`}
                  </p>
                </div>

                <div className="flex items-center gap-2">
                  <Input
                    placeholder="Search by ID, name, bank, account..."
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
              {banks?.data && banks.data.length > 0 ? (
                <>
                  <div className="rounded-md border overflow-x-auto">
                    <Table>
                      <TableHeader>
                        <TableRow>
                          <TableHead className="w-12">No.</TableHead>
                          <TableHead>Staff ID</TableHead>
                          <TableHead>Employee Name</TableHead>
                          <TableHead>Bank Name</TableHead>
                          <TableHead>Account Number</TableHead>
                          <TableHead className="text-right">Actions</TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {banks.data.map((b, idx) => (
                          <TableRow key={b.staffida}>
                            <TableCell>{(banks.from ?? 1) + idx}</TableCell>
                            <TableCell className="font-mono font-medium">{b.staffida}</TableCell>
                            <TableCell>{b.employname}</TableCell>
                            <TableCell>{b.bankname}</TableCell>
                            <TableCell className="font-mono">{b.accountnum}</TableCell>
                            <TableCell className="text-right">
                              <div className="flex items-center justify-end gap-1">
                                <Button variant="ghost" size="sm" onClick={() => handleEdit(b)}>
                                  <Pencil className="h-4 w-4 text-blue-600" />
                                </Button>
                                <Button variant="ghost" size="sm" onClick={() => handleDelete(b.staffida)}>
                                  <Trash className="h-4 w-4 text-red-600" />
                                </Button>
                              </div>
                            </TableCell>
                          </TableRow>
                        ))}
                      </TableBody>
                    </Table>
                  </div>

                  {banks.links && banks.links.length > 3 && (
                    <div className="flex justify-end gap-2 mt-4">
                      {banks.links.map((link, idx) => (
                        <Button
                          key={idx}
                          variant={link.active ? "default" : "outline"}
                          size="sm"
                          disabled={!link.url}
                          asChild={!!link.url}
                        >
                          {link.url ? (
                            <a href={link.url} dangerouslySetInnerHTML={{ __html: link.label }} />
                          ) : (
                            <span dangerouslySetInnerHTML={{ __html: link.label }} />
                          )}
                        </Button>
                      ))}
                    </div>
                  )}
                </>
              ) : (
                <div className="text-center py-12">
                  <FileSpreadsheet className="mx-auto h-12 w-12 text-gray-400" />
                  <h3 className="mt-2 text-sm font-semibold text-gray-900">No bank records found</h3>
                  <p className="mt-1 text-sm text-gray-500">
                    No bank accounts on file for Transsion BFA.
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
