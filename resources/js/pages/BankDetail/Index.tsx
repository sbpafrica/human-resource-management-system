import { Head, Link, router, useForm } from "@inertiajs/react";
import { Button } from "@/components/ui/button";
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import {
  Plus,
  Pencil,
  Trash,
  CheckCircle,
  XCircle,
  Download,
  Upload,
} from "lucide-react";
import AppLayout from "@/layouts/app-layout";
import {
  Dialog,
  DialogContent,
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
import { Checkbox } from "@/components/ui/checkbox";
import { useState, useEffect } from "react";
import { Input as ShadcnInput } from "@/components/ui/input";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import ErrorBoundary from "@/components/ErrorBoundary";

// ────────────────────────────────────────────────────────────────
// Types
// ────────────────────────────────────────────────────────────────
interface BankDetail {
  employid: string;
  shop: string | null;
  name: string | null;
  bank: string | null;
  account: string | null;
  code: string | null;
  branch: string | null;
  momonum: string | null;
  momoname: string | null;
}

interface BankCode {
  id: number;
  bankname: string;
  branchcodes: string;
  branch: string | null;
}

interface Props {
  bankDetails: {
    data: BankDetail[];
    links: any[];
  };
  bankCodes: BankCode[];
  clients: string[];                    // ← ADDED: from controller
  filters: {
    search: string;
    sort: string;
    order: string;
  };
  totalRecords: number;
  searchCount: number | null;
  flash?: {
    success?: string;
    error?: string;
  };
}

type BankDetailFormData = {
  employid: string;
  shop: string;
  name: string;
  bank: string;
  account: string;
  code: string;
  branch: string;
  momonum: string;
  momoname: string;
};

const breadcrumbs = [{ title: "Bank Details", href: "/bankdetails" }];

// ────────────────────────────────────────────────────────────────
// Helper: Clean string by removing invisible Unicode chars and trim whitespace
// ────────────────────────────────────────────────────────────────
const cleanString = (value: string): string => {
  return value.replace(/[\u200B-\u200D\uFEFF]/g, '').trim();
};

const normalizeBankName = (name: string): string => {
  return name ? name.trim().toUpperCase() : "";
};

// ────────────────────────────────────────────────────────────────
// Helper: Clean all string values in form data
// ────────────────────────────────────────────────────────────────
const cleanFormData = (data: BankDetailFormData): BankDetailFormData => {
  return Object.fromEntries(
    Object.entries(data).map(([key, value]) => [
      key,
      typeof value === "string" ? cleanString(value) : value,
    ])
  ) as BankDetailFormData;
};

// ────────────────────────────────────────────────────────────────
// Main Component
// ────────────────────────────────────────────────────────────────
export default function BankDetailIndex({
  bankDetails,
  bankCodes,
  clients,
  filters,
  totalRecords,
  searchCount,
  flash,
}: Props) {
    console.log('bankCodes received:', bankCodes);
  const [isOpen, setIsOpen] = useState(false);
  const [editingBankDetail, setEditingBankDetail] = useState<BankDetail | null>(null);
  const [isDeleteOpen, setIsDeleteOpen] = useState(false);
  const [deleteEmployId, setDeleteEmployId] = useState<string | null>(null);
  const [isBulkDeleteOpen, setIsBulkDeleteOpen] = useState(false);
  const [isBulkUploadOpen, setIsBulkUploadOpen] = useState(false);
  const [selectedEmployIds, setSelectedEmployIds] = useState<string[]>([]);
  const [showToast, setShowToast] = useState(false);
  const [toastMessage, setToastMessage] = useState("");
  const [toastType, setToastType] = useState<"success" | "error">("success");
  const [search, setSearch] = useState(filters.search || "");
  const [sort, setSort] = useState(filters.sort || "employid");
  const [order, setOrder] = useState(filters.order || "asc");
  const [availableBranches, setAvailableBranches] = useState<BankCode[]>([]);

  // ────────────────────────────────────────────────────────────────
// FINAL WORKING FORM — NO MORE ERRORS, NO MORE REQUIRED BUG
// ────────────────────────────────────────────────────────────────
const form = useForm<BankDetailFormData>({
  employid: "",
  shop: "",
  name: "",
  bank: "",
  account: "",
  code: "",
  branch: "",
  momonum: "",
  momoname: "",
});

form.transform((data: Partial<BankDetailFormData>) => ({
  employid: (data.employid ?? "").trim(),
  shop: data.shop || null,
  name: (data.name ?? "").trim(),
  bank: data.bank && data.bank !== "none" ? data.bank : null,
  account: data.account ? data.account.trim() : null,
  code: data.code || null,
  branch: data.branch ? data.branch.trim() : null,
  momonum: data.momonum ? data.momonum.trim() : null,
  momoname: data.momoname ? data.momoname.trim() : null,
}));

const { data, setData, post, put, processing, delete: destroy, get, errors, reset } = form;



  const bulkDeleteForm = useForm<{ employids: string[] }>({ employids: [] });
  const bulkUploadForm = useForm({ file: null as File | null });

  // ────────────────────────────────────────────────────────────────
  // Effects
  // ────────────────────────────────────────────────────────────────
  useEffect(() => {
    bulkDeleteForm.setData("employids", selectedEmployIds.map(id => id.trim()));
  }, [selectedEmployIds]);

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
    const showError = (msg: string) => {
      setToastMessage(msg);
      setToastType("error");
      setShowToast(true);
    };
    if (Object.keys(bulkDeleteForm.errors).length > 0) {
      showError(bulkDeleteForm.errors.employids || "Failed to delete.");
    }
    if (Object.keys(bulkUploadForm.errors).length > 0) {
      showError(bulkUploadForm.errors.file || "Failed to upload.");
    }
    if (Object.keys(errors).length > 0) {
      showError(Object.values(errors).join(" | "));
    }
  }, [bulkDeleteForm.errors, bulkUploadForm.errors, errors]);

  useEffect(() => {
    if (showToast) {
      const timer = setTimeout(() => setShowToast(false), toastType === "error" ? 60000 : 3000);
      return () => clearTimeout(timer);
    }
  }, [showToast, toastType]);

  // ────────────────────────────────────────────────────────────────
  // Load edit data
  // ────────────────────────────────────────────────────────────────
  useEffect(() => {
    if (editingBankDetail) {
      const bank = editingBankDetail.bank ?? "";
      const branches = bankCodes.filter(bc => bc.bankname === bank && bc.branch);
      const baseBank = bankCodes.find(bc => bc.bankname === bank);
      const baseCode = baseBank?.branchcodes ?? "";

      let branch = editingBankDetail.branch ?? "";
      let code = editingBankDetail.code ?? "";

      if (branches.length === 0 && !code) {
        code = baseCode;
      }

      setData({
        employid: editingBankDetail.employid ?? "",
        shop: editingBankDetail.shop ?? "",
        name: editingBankDetail.name ?? "",
        bank,
        account: editingBankDetail.account ?? "",
        code,
        branch,
        momonum: editingBankDetail.momonum ?? "",
        momoname: editingBankDetail.momoname ?? "",
      });

      setAvailableBranches(branches);
    }
  }, [editingBankDetail, bankCodes]);

  // ────────────────────────────────────────────────────────────────
  // Handlers
  // ────────────────────────────────────────────────────────────────
  const handleEdit = (detail: BankDetail) => {
    setEditingBankDetail(detail);
    setIsOpen(true);
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();

    if (editingBankDetail) {
      router.post(route("bankdetails.update"), {
        ...data,
        original_employid: editingBankDetail.employid,
        _method: "put",
      }, {
        onSuccess: closeModal,
        onError: (err) => {
          showError(Object.values(err).join(" | "));
        },
      });
    } else {
      post(route("bankdetails.store"), {
        onSuccess: closeModal,
        onError: (err) => {
          showError(Object.values(err).join(" | "));
        },
      });
    }
  };

  const closeModal = () => {
    setIsOpen(false);
    reset();
    setEditingBankDetail(null);
    setAvailableBranches([]);
  };

  const handleDelete = (id: string) => {
    setDeleteEmployId(id);
    setIsDeleteOpen(true);
  };

  const confirmDelete = () => {
    if (deleteEmployId) {
      router.post(route("bankdetails.destroy"), {
        employid: deleteEmployId,
        _method: "delete",
      }, {
        onSuccess: () => {
          setIsDeleteOpen(false);
          setDeleteEmployId(null);
        },
        onError: (err) => {
          showError(Object.values(err).join(" | "));
        },
      });
    }
  };

  const handleSelect = (id: string, checked: boolean) => {
    const trimmed = id.trim();
    setSelectedEmployIds(prev =>
      checked ? [...prev.filter(x => x !== trimmed), trimmed] : prev.filter(x => x !== trimmed)
    );
  };

  const handleSelectAll = (checked: boolean) => {
    setSelectedEmployIds(checked ? bankDetails.data.map(b => b.employid.trim()) : []);
  };

  const handleBulkDelete = () => selectedEmployIds.length > 0 && setIsBulkDeleteOpen(true);

  const confirmBulkDelete = () => {
    bulkDeleteForm.post(route("bankdetails.bulk-destroy"), {
      forceFormData: true,
      onSuccess: () => {
        setIsBulkDeleteOpen(false);
        setSelectedEmployIds([]);
      },
    });
  };

  const handleSearchSort = () => {
    get(route("bankdetails.index", { search, sort, order }), { preserveState: true });
  };

  const handleExport = () => {
    const link = document.createElement("a");
    link.href = route("bankdetails.export", { search, sort, order });
    link.download = "bank_details.xlsx";
    link.click();
  };

  const handleDownloadTemplate = () => {
    const link = document.createElement("a");
    link.href = route("bankdetails.download-template");
    link.download = "template.xlsx";
    link.click();
  };

  const handleBulkUpload = (e: React.FormEvent) => {
    e.preventDefault();
    if (bulkUploadForm.data.file) {
      bulkUploadForm.post(route("bankdetails.bulk-upload"), {
        forceFormData: true,
        onSuccess: () => {
          setIsBulkUploadOpen(false);
          bulkUploadForm.reset();
        },
      });
    }
  };

  const bankNames = [...new Set(
  bankCodes
    .map(bc => normalizeBankName(bc.bankname))
    .filter(Boolean)
)].sort();

  const allSelected = bankDetails.data.length > 0 && bankDetails.data.every(b => selectedEmployIds.includes(b.employid.trim()));
  const indeterminate = selectedEmployIds.length > 0 && selectedEmployIds.length < bankDetails.data.length;

  // ────────────────────────────────────────────────────────────────
  // Render
  // ────────────────────────────────────────────────────────────────
  return (
    <ErrorBoundary>
      <AppLayout breadcrumbs={breadcrumbs}>
        <Head title="Bank Details" />
        <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">

          {/* Toast */}
          {showToast && (
            <div className={`fixed top-4 right-4 z-50 flex items-center gap-2 rounded-lg p-4 shadow-lg max-w-sm ${toastType === "success" ? "bg-green-500" : "bg-red-500"} text-white`}>
              {toastType === "success" ? <CheckCircle /> : <XCircle />}
              <span>{toastMessage}</span>
              {toastType === "error" && <Button variant="ghost" size="sm" onClick={() => setShowToast(false)}><XCircle /></Button>}
            </div>
          )}

          {/* Header */}
          <div className="flex flex-col gap-4">
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
              <h1 className="text-2xl font-bold">Bank Details</h1>

              <div className="flex flex-col sm:flex-row gap-4">
                <ShadcnInput
                  placeholder="Search..."
                  value={search}
                  onChange={e => setSearch(e.target.value)}
                  onKeyUp={handleSearchSort}
                  className="w-full sm:w-64"
                />

                <Select value={`${sort}:${order}`} onValueChange={v => { const [s, o] = v.split(":"); setSort(s); setOrder(o); handleSearchSort(); }}>
                  <SelectTrigger className="w-full sm:w-48"><SelectValue placeholder="Sort by" /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="employid:asc">Employee ID (A-Z)</SelectItem>
                    <SelectItem value="employid:desc">Employee ID (Z-A)</SelectItem>
                    <SelectItem value="shop:asc">Shop (A-Z)</SelectItem>
                    <SelectItem value="bank:asc">Bank (A-Z)</SelectItem>
                    <SelectItem value="branch:asc">Branch (A-Z)</SelectItem>
                  </SelectContent>
                </Select>

                <Button onClick={handleExport}><Download className="h-4 w-4 mr-2" /> Export</Button>

                <Dialog open={isOpen} onOpenChange={open => { setIsOpen(open); if (!open) closeModal(); }}>
                  <DialogTrigger asChild>
                    <Button><Plus className="h-4 w-4 mr-2" /> New</Button>
                  </DialogTrigger>
                  <DialogContent className="max-w-2xl max-h-[80vh] overflow-y-auto">
                    <DialogHeader>
                      <DialogTitle>{editingBankDetail ? "Edit" : "Create"} Bank Detail</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={handleSubmit}>
                      <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 py-4">

                        {/* Employee ID */}
                        <div className="grid gap-2">
                          <Label>Employee ID *</Label>
                          <Input
  value={data.employid || ""}
  onChange={e => setData("employid", e.target.value)}
  required
/>
                          {errors.employid && <p className="text-red-500 text-sm">{errors.employid}</p>}
                        </div>

                        {/* Shop */}
{/* Shop - MELCOM = free text branch only */}
{/* Shop - MELCOM = type branch only (PERFECT & FINAL) */}
<div className="grid gap-2">
  <Label>Shop *</Label>

  {/* Use a hidden state to remember if MELCOM mode is active */}
  {data.shop === "MELCOM" || (data.shop ?? "").startsWith("MELCOM") || (Boolean(data.shop) && !clients.includes(data.shop || "")) ? (
    <Input
      value={(data.shop ?? "").startsWith("MELCOM") ? (data.shop || "").slice(6).trimStart() : (data.shop ?? "")}
      onChange={e => setData("shop", e.target.value)}
      placeholder="Enter branch name (e.g. Spintex, Osu, Kumasi)"
      required
      autoFocus
    />
  ) : (
    <Select
      value={data.shop || ""}
      onValueChange={value => {
        if (value === "MELCOM") {
          setData("shop", "MELCOM"); // trigger MELCOM mode
        } else {
          setData("shop", value);
        }
      }}
    >
      <SelectTrigger>
        <SelectValue placeholder="Select shop" />
      </SelectTrigger>
      <SelectContent>
        {clients.map(clientName => (
          <SelectItem key={clientName} value={clientName}>
            {clientName}
          </SelectItem>
        ))}
      </SelectContent>
    </Select>
  )}

  {/* Optional hint */}
  {data.shop === "MELCOM" && (
    <p className="text-xs text-green-600 mt-1">
      ✓ MELCOM selected — type branch name only
    </p>
  )}

  {errors.shop && <p className="text-red-500 text-sm mt-1">{errors.shop}</p>}
</div>

                        {/* Name */}
                        <div className="grid gap-2">
                          <Label>Name *</Label>
                          <Input
  value={data.name || ""}
  onChange={e => setData("name", e.target.value)}
  required
/>
                          {errors.name && <p className="text-red-500 text-sm">{errors.name}</p>}
                        </div>

                        {/* Bank */}
                        {/* Bank */}
<div className="grid gap-2">
  <Label>Bank</Label>
  <Select
    value={data.bank || ""}
    onValueChange={value => {
      const bank = value === "none" ? "" : normalizeBankName(value);

      const allForBank = bank ? bankCodes.filter(bc => normalizeBankName(bc.bankname) === bank) : [];
      const withBranches = allForBank.filter(b => b.branch);
      const fallback = allForBank[0];

      setAvailableBranches(withBranches);

      setData(prev => ({
        ...prev,
        bank,
        code: fallback?.branchcodes || "",
        branch: withBranches.length > 0 ? "" : (fallback?.branch || ""),
        account: "",
      }));
    }}
  >
    <SelectTrigger><SelectValue placeholder="Select bank" /></SelectTrigger>
    <SelectContent>
      <SelectItem value="none">None</SelectItem>
      {bankNames.map(name => (
        <SelectItem key={name} value={name}>{name}</SelectItem>
      ))}
    </SelectContent>
  </Select>
</div>

                        {/* Account Number */}
                        <div className="grid gap-2">
                          <Label>Account Number{data.bank && " *"}</Label>
                          <Input
  value={data.account || ""}
  onChange={e => setData("account", e.target.value)}
  required={!!data.bank}
  disabled={!data.bank}
placeholder={data.bank ? "Enter account" : "Select bank"}
                          />
                          {errors.account && <p className="text-red-500 text-sm">{errors.account}</p>}
                        </div>

                        {/* Sort Code */}
                        <div className="grid gap-2">
                          <Label>Sort Code{data.bank && " *"}</Label>
                          {(() => {
                            const normBank = data.bank ? normalizeBankName(data.bank) : "";
                            const isTargetBank = ['ABSA', 'ADB', 'ECOBANK'].some(b => normBank.includes(b));
                            const targetBankCodes = data.bank
                              ? bankCodes.filter(bc => {
                                  const nb = normalizeBankName(bc.bankname);
                                  return nb === normBank || normBank.includes(nb) || nb.includes(normBank);
                                })
                              : [];

                            if (isTargetBank && targetBankCodes.length > 0) {
                              return (
                                <Select
                                  value={data.code || ""}
                                  onValueChange={(selectedCode) => {
                                    const matched = targetBankCodes.find(bc => bc.branchcodes === selectedCode);
                                    setData(prev => ({
                                      ...prev,
                                      code: selectedCode,
                                      branch: matched?.branch || prev.branch || "",
                                    }));
                                  }}
                                >
                                  <SelectTrigger>
                                    <SelectValue placeholder="Select sort code" />
                                  </SelectTrigger>
                                  <SelectContent>
                                    {Array.from(new Map(targetBankCodes.map(bc => [bc.branchcodes, bc])).values()).map((bc) => (
                                      <SelectItem key={bc.id} value={bc.branchcodes}>
                                        {bc.branchcodes}
                                      </SelectItem>
                                    ))}
                                  </SelectContent>
                                </Select>
                              );
                            }

                            return (
                              <Input
                                value={data.code}
                                readOnly
                                className="bg-gray-100"
                                disabled
                                placeholder={
                                  data.bank
                                    ? availableBranches.length > 0
                                      ? "Select a branch to see code"
                                      : "Auto-filled (no branches defined)"
                                    : "Select bank first"
                                }
                              />
                            );
                          })()}
                        </div>


{/* Branch */}
<div className="grid gap-2 sm:col-span-2">
  <Label>Branch{data.bank && " *"}</Label>

  {data.bank && availableBranches.length > 0 ? (
    // Branch dropdown when bank has predefined branches
    <Select
      value={data.branch || ""}
      onValueChange={(selectedBranch) => {
        const selected = availableBranches.find(bc => bc.branch === selectedBranch);
        setData(prev => ({
          ...prev,
          branch: selectedBranch,
          code: selected?.branchcodes || prev.code,
        }));
      }}
    >
      <SelectTrigger>
        <SelectValue placeholder="Select branch" />
      </SelectTrigger>
      <SelectContent>
        {availableBranches.map(bc => (
          <SelectItem key={bc.id} value={bc.branch || ""}>
            {bc.branch} ({bc.branchcodes})
          </SelectItem>
        ))}
      </SelectContent>
    </Select>
  ) : data.bank ? (
    // Manual branch entry when no predefined branches
    <>
      <Input
        value={data.branch || ""}
        onChange={e => setData("branch", e.target.value)}
        required
        placeholder="Enter branch name (e.g. Head Office)"
      />
      {data.code && (
        <p className="text-sm text-muted-foreground mt-1">
          Sort Code: <span className="font-mono font-semibold">{data.code}</span>
        </p>
      )}
    </>
  ) : (
    <Input disabled placeholder="Select bank first" />
  )}

  {errors.branch && <p className="text-red-500 text-sm">{errors.branch}</p>}
</div>

                        {/* Mobile Money */}
                        <div className="grid gap-2">
                          <Label>Mobile Money Number{data.momoname && " *"}</Label>
                          <Input
                            value={data.momonum}
                            onChange={e => setData("momonum", e.target.value)}
                            onBlur={e => setData("momonum", cleanString(e.target.value))}
                            required={!!data.momoname}
                          />
                        </div>

                        <div className="grid gap-2">
                          <Label>Mobile Money Name{data.momonum && " *"}</Label>
                          <Input
                            value={data.momoname}
                            onChange={e => setData("momoname", e.target.value)}
                            onBlur={e => setData("momoname", cleanString(e.target.value))}
                            required={!!data.momonum}
                          />
                        </div>
                      </div>

                      <DialogFooter>
                        <Button type="submit" disabled={processing}>
                          {editingBankDetail ? "Update" : "Create"}
                        </Button>
                      </DialogFooter>
                    </form>
                  </DialogContent>
                </Dialog>
              </div>
            </div>

            {/* Bulk Actions */}
            <div className="flex flex-col sm:flex-row gap-4 justify-end">
              <Button onClick={handleDownloadTemplate} className="bg-blue-500 hover:bg-blue-600">
                <Download className="h-4 w-4 mr-2" /> Template
              </Button>

              <Dialog open={isBulkUploadOpen} onOpenChange={open => { setIsBulkUploadOpen(open); if (!open) bulkUploadForm.reset(); }}>
                <DialogTrigger asChild>
                  <Button className="bg-green-500 hover:bg-green-600"><Upload className="h-4 w-4 mr-2" /> Upload</Button>
                </DialogTrigger>
                <DialogContent>
                  <DialogHeader><DialogTitle>Bulk Upload</DialogTitle></DialogHeader>
                  <form onSubmit={handleBulkUpload}>
                    <div className="grid gap-4 py-4">
                      <Input
                        type="file"
                        accept=".xlsx,.xls,.csv,.ods"
                        onChange={e => e.target.files?.[0] && bulkUploadForm.setData("file", e.target.files[0])}
                      />
                    </div>
                    <DialogFooter>
                      <Button type="submit" disabled={!bulkUploadForm.data.file || bulkUploadForm.processing}>Upload</Button>
                    </DialogFooter>
                  </form>
                </DialogContent>
              </Dialog>

              <Button variant="destructive" onClick={handleBulkDelete} disabled={selectedEmployIds.length === 0}>
                <Trash className="h-4 w-4 mr-2" /> Delete ({selectedEmployIds.length})
              </Button>
            </div>
          </div>

          {/* Table */}
          {bankDetails.data.length > 0 ? (
            <Card>
              <CardHeader>
                <CardTitle>Bank Details</CardTitle>
                <div className="text-sm text-muted-foreground">
                  {searchCount !== null ? `Showing ${searchCount} of ${totalRecords}` : `Total: ${totalRecords}`}
                </div>
              </CardHeader>
              <CardContent>
                <div className="hidden md:block">
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>
                          <div className="flex items-center">
                            <Checkbox
                              checked={allSelected}
                              onCheckedChange={handleSelectAll}
                            />
                            {indeterminate && <span className="ml-1 text-xs text-muted-foreground">—</span>}
                          </div>
                        </TableHead>
                        <TableHead>ID</TableHead>
                        <TableHead>Shop</TableHead>
                        <TableHead>Name</TableHead>
                        <TableHead>Bank</TableHead>
                        <TableHead>Account</TableHead>
                        <TableHead>Code</TableHead>
                        <TableHead>Branch</TableHead>
                        <TableHead>Momo #</TableHead>
                        <TableHead>Momo Name</TableHead>
                        <TableHead className="text-right">Actions</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {bankDetails.data.map(bd => (
                        <TableRow key={bd.employid}>
                          <TableCell>
                            <Checkbox
                              checked={selectedEmployIds.includes(bd.employid.trim())}
                              onCheckedChange={c => handleSelect(bd.employid, c as boolean)}
                            />
                          </TableCell>
                          <TableCell>{bd.employid}</TableCell>
                          <TableCell>{bd.shop || "—"}</TableCell>
                          <TableCell>{bd.name || "—"}</TableCell>
                          <TableCell>{bd.bank || "—"}</TableCell>
                          <TableCell>{bd.account || "—"}</TableCell>
                          <TableCell>{bd.code || "—"}</TableCell>
                          <TableCell>{bd.branch || "—"}</TableCell>
                          <TableCell>{bd.momonum || "—"}</TableCell>
                          <TableCell>{bd.momoname || "—"}</TableCell>
                          <TableCell className="text-right">
                            <Button variant="outline" size="sm" onClick={() => handleEdit(bd)}>
                              <Pencil className="h-4 w-4" />
                            </Button>
                            <Button variant="destructive" size="sm" onClick={() => handleDelete(bd.employid)}>
                              <Trash className="h-4 w-4" />
                            </Button>
                          </TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                </div>

                {/* Mobile View */}
                <div className="block md:hidden space-y-4">
                  {bankDetails.data.map(bd => (
                    <div key={bd.employid} className="border rounded-lg p-4">
                      <div className="flex items-center gap-2">
                        <Checkbox
                          checked={selectedEmployIds.includes(bd.employid.trim())}
                          onCheckedChange={c => handleSelect(bd.employid, c as boolean)}
                        />
                        <div className="font-semibold">{bd.employid}</div>
                      </div>
                      <div className="text-sm text-muted-foreground">Shop: {bd.shop || "—"}</div>
                      <div className="text-sm text-muted-foreground">Name: {bd.name || "—"}</div>
                      <div className="text-sm text-muted-foreground">Bank: {bd.bank || "—"}</div>
                      <div className="text-sm text-muted-foreground">Account: {bd.account || "—"}</div>
                      <div className="text-sm text-muted-foreground">Branch: {bd.branch || "—"}</div>
                      <div className="flex gap-2 mt-2">
                        <Button variant="outline" size="sm" onClick={() => handleEdit(bd)}><Pencil /></Button>
                        <Button variant="destructive" size="sm" onClick={() => handleDelete(bd.employid)}><Trash /></Button>
                      </div>
                    </div>
                  ))}
                </div>

                {/* Pagination */}
                <div className="flex justify-end mt-4 gap-2">
                  {bankDetails.links.map((link, i) => (
                    <Button
                      key={i}
                      variant={link.active ? "default" : "outline"}
                      disabled={!link.url}
                      asChild={!!link.url}
                    >
                      {link.url ? (
                        <Link href={link.url} dangerouslySetInnerHTML={{ __html: link.label }} />
                      ) : (
                        <span dangerouslySetInnerHTML={{ __html: link.label }} />
                      )}
                    </Button>
                  ))}
                </div>
              </CardContent>
            </Card>
          ) : (
            <Card>
              <CardContent className="pt-6 text-center text-gray-500">
                No records. Create one!
              </CardContent>
            </Card>
          )}

          {/* Delete Dialogs */}
          <Dialog open={isDeleteOpen} onOpenChange={setIsDeleteOpen}>
            <DialogContent>
              <DialogHeader><DialogTitle>Delete?</DialogTitle></DialogHeader>
              <p>Are you sure?</p>
              <DialogFooter>
                <Button variant="outline" onClick={() => setIsDeleteOpen(false)}>Cancel</Button>
                <Button variant="destructive" onClick={confirmDelete} disabled={processing}>Delete</Button>
              </DialogFooter>
            </DialogContent>
          </Dialog>

          <Dialog open={isBulkDeleteOpen} onOpenChange={setIsBulkDeleteOpen}>
            <DialogContent>
              <DialogHeader><DialogTitle>Delete {selectedEmployIds.length}?</DialogTitle></DialogHeader>
              <p>Irreversible.</p>
              <DialogFooter>
                <Button variant="outline" onClick={() => setIsBulkDeleteOpen(false)}>Cancel</Button>
                <Button variant="destructive" onClick={confirmBulkDelete}>Delete</Button>
              </DialogFooter>
            </DialogContent>
          </Dialog>
        </div>
      </AppLayout>
    </ErrorBoundary>
  );
}
