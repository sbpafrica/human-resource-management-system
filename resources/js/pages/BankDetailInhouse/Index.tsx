import { Head, Link, router, useForm } from "@inertiajs/react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Plus, Pencil, Trash, CheckCircle2, XCircle, Download, Upload } from "lucide-react";
import AppLayout from "@/layouts/app-layout";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger, DialogFooter } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Checkbox } from "@/components/ui/checkbox";
import { useState, useEffect } from "react";
import { Input as ShadcnInput } from "@/components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import ErrorBoundary from "@/components/ErrorBoundary";

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

const breadcrumbs = [
  { title: "Bank Details Inhouse", href: "/bankdetailsinhouse" },
];

export default function BankDetailInhouseIndex({ bankDetails, bankCodes, filters, totalRecords, searchCount, flash }: Props) {
  // Debug: Log props to verify data
  console.log('Props:', { bankDetails, bankCodes, filters, totalRecords, searchCount, flash });

  const [isOpen, setIsOpen] = useState(false);
  const [editingBankDetail, setEditingBankDetail] = useState<BankDetail | null>(null);
  const [isDeleteOpen, setIsDeleteOpen] = useState(false);
  const [deleteEmployId, setDeleteEmployId] = useState<string | null>(null);
  const [isBulkDeleteOpen, setIsBulkDeleteOpen] = useState(false);
  const [isUploadOpen, setIsUploadOpen] = useState(false);
  const [selectedEmployIds, setSelectedEmployIds] = useState<string[]>([]);
  const [showToast, setShowToast] = useState(false);
  const [toastMessage, setToastMessage] = useState("");
  const [toastType, setToastType] = useState<"success" | "error">("success");
  const [search, setSearch] = useState(filters.search || "");
  const [sort, setSort] = useState(filters.sort || "employid");
  const [order, setOrder] = useState(filters.order || "asc");
  const [selectedBank, setSelectedBank] = useState<string>("");
  const [availableBranches, setAvailableBranches] = useState<BankCode[]>([]);

  const { data, setData, post, put, processing, reset, delete: destroy, get, errors } = useForm({
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

  const uploadForm = useForm({
    file: null as File | null,
  });

  const bulkDeleteForm = useForm({
    employids: [] as string[],
  });

  useEffect(() => {
    bulkDeleteForm.setData('employids', selectedEmployIds);
    console.log("Updated bulkDeleteForm.employids:", selectedEmployIds);
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
    if (Object.keys(bulkDeleteForm.errors).length > 0) {
      const errorMessage = bulkDeleteForm.errors.employids || "Failed to delete selected records. Please try again.";
      setToastMessage(errorMessage);
      setToastType("error");
      setShowToast(true);
    }
    if (Object.keys(uploadForm.errors).length > 0) {
      const errorMessage = uploadForm.errors.file || "Failed to upload file. Please try again.";
      setToastMessage(errorMessage);
      setToastType("error");
      setShowToast(true);
    }
    if (Object.keys(errors).length > 0) {
      const errorMessage = Object.values(errors).join(' | ');
      setToastMessage(errorMessage);
      setToastType("error");
      setShowToast(true);
    }
  }, [bulkDeleteForm.errors, uploadForm.errors, errors]);

  useEffect(() => {
    if (showToast) {
      const duration = toastType === "error" ? 60000 : 3000;
      const timer = setTimeout(() => setShowToast(false), duration);
      return () => clearTimeout(timer);
    }
  }, [showToast, toastType]);

  useEffect(() => {
    if (data.bank && data.bank !== "none" && bankCodes) {
      const branches = bankCodes.filter((bc) => bc.bankname === data.bank && bc.branch && bc.branch !== "") || [];
      console.log('Branches for bank', data.bank, ':', branches);
      setAvailableBranches(branches);
      if (branches.length > 0) {
        setData("branch", branches[0].branch || "");
        setData("code", branches[0].branchcodes || "");
      } else {
        const bankCode = bankCodes.find((bc) => bc.bankname === data.bank);
        setData("code", bankCode?.branchcodes || "");
        setData("branch", "");
      }
    } else {
      setAvailableBranches([]);
      setData("branch", "");
      setData("code", "");
      setData("account", "");
    }
  }, [data.bank, bankCodes]);

  useEffect(() => {
    if (data.bank && data.bank !== "none" && data.branch) {
      const selectedBranch = bankCodes.find((bc) => bc.bankname === data.bank && bc.branch === data.branch);
      if (selectedBranch) {
        setData("code", selectedBranch.branchcodes);
      }
    }
  }, [data.branch, bankCodes]);

  useEffect(() => {
    if ((data.momonum || data.momoname) && (!data.bank || data.bank === "none")) {
      setData({
        ...data,
        account: "",
        code: "",
        branch: "",
      });
      setAvailableBranches([]);
    }
  }, [data.momonum, data.momoname]);

  const handleSubmit = (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    console.log("Submitting form data:", data);
    if (editingBankDetail) {
      router.post(route("bankdetailsinhouse.update"), {
        ...data,
        original_employid: editingBankDetail.employid,
        _method: "put",
      }, {
        onSuccess: () => {
          setIsOpen(false);
          reset();
          setEditingBankDetail(null);
          setSelectedBank("");
          setAvailableBranches([]);
        },
        onError: (errors) => {
          console.error("Update failed:", errors);
        },
      });
    } else {
      post(route("bankdetailsinhouse.store"), {
        onSuccess: () => {
          setIsOpen(false);
          reset();
          setSelectedBank("");
          setAvailableBranches([]);
        },
      });
    }
  };

  const handleEdit = (bankDetail: BankDetail) => {
    console.log('Editing bank detail:', bankDetail);
    setEditingBankDetail(bankDetail);
    setData({
      employid: bankDetail.employid,
      shop: bankDetail.shop || "",
      name: bankDetail.name || "",
      bank: bankDetail.bank || "",
      account: bankDetail.account || "",
      code: bankDetail.code || "",
      branch: bankDetail.branch || "",
      momonum: bankDetail.momonum || "",
      momoname: bankDetail.momoname || "",
    });
    setSelectedBank(bankDetail.bank || "none");
    setIsOpen(true);
  };

  const handleDelete = (employid: string) => {
    setDeleteEmployId(employid);
    setIsDeleteOpen(true);
  };

  const confirmDelete = () => {
    if (deleteEmployId) {
      router.post(route("bankdetailsinhouse.destroy"), {
        employid: deleteEmployId,
        _method: "delete",
      }, {
        onSuccess: () => {
          setIsDeleteOpen(false);
          setDeleteEmployId(null);
        },
        onError: (errors) => {
          console.error("Delete failed:", errors);
        },
      });
    }
  };

  const handleSelect = (employid: string, checked: boolean) => {
    setSelectedEmployIds((prev) =>
      checked ? [...prev, employid] : prev.filter((id) => id !== employid)
    );
  };

  const handleSelectAll = (checked: boolean) => {
    setSelectedEmployIds(checked ? bankDetails.data.map((bankDetail) => bankDetail.employid) : []);
  };

  const handleBulkDelete = () => {
    if (selectedEmployIds.length > 0) {
      setIsBulkDeleteOpen(true);
    }
  };

  const confirmBulkDelete = () => {
    console.log("Bulk delete triggered with employids:", bulkDeleteForm.data.employids);
    bulkDeleteForm.post(route("bankdetailsinhouse.bulk-destroy"), {
      onSuccess: () => {
        console.log("Bulk delete successful");
        setIsBulkDeleteOpen(false);
        setSelectedEmployIds([]);
      },
      onError: (errors) => {
        console.error("Bulk delete failed:", errors);
      },
      preserveState: true,
      preserveScroll: true,
    });
  };

  const handleSearchSort = () => {
    get(route("bankdetailsinhouse.index", { search, sort, order }), {
      preserveState: true,
      preserveScroll: true,
    });
  };

  const handleExport = () => {
    const url = route("bankdetailsinhouse.export", { search, sort, order });
    const link = document.createElement("a");
    link.href = url;
    link.download = "bank_details_inhouse.xlsx";
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  };

  const handleUpload = (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    if (uploadForm.data.file) {
      console.log("Uploading file:", uploadForm.data.file.name);
      uploadForm.post(route("bankdetailsinhouse.bulk-upload"), {
        onSuccess: () => {
          setIsUploadOpen(false);
          uploadForm.reset();
        },
        onError: (errors) => {
          console.error("Upload failed:", errors);
        },
        preserveState: true,
        preserveScroll: true,
      });
    }
  };

  const handleDownloadTemplate = () => {
    const url = route("bankdetailsinhouse.download-template");
    const link = document.createElement("a");
    link.href = url;
    link.download = "bank_details_inhouse_template.xlsx";
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  };

  // Filter out null or empty bank names
  const bankNames = bankCodes ? [...new Set(bankCodes.map((bc) => bc.bankname).filter(Boolean))] : [];

  return (
    <ErrorBoundary>
      <AppLayout breadcrumbs={breadcrumbs}>
        <Head title="Bank Details Inhouse" />
        <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
          {showToast && (
            <div
              className={`fixed top-4 right-4 z-50 flex items-center gap-2 rounded-lg p-4 shadow-lg w-full sm:w-auto max-w-sm ${
                toastType === "success" ? "bg-green-500 text-white" : "bg-red-500 text-white"
              } animate-in fade-in slide-in-from-top-5`}
            >
              {toastType === "success" ? <CheckCircle2 className="h-5 w-5" /> : <XCircle className="h-5 w-5" />}
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
              <h1 className="text-2xl font-bold">Bank Details Inhouse</h1>
              <div className="flex flex-col sm:flex-row gap-4">
                <ShadcnInput
                  placeholder="Search by employee ID, shop, name, bank, or mobile money name..."
                  value={search}
                  onChange={(e) => setSearch(e.target.value)}
                  onKeyUp={handleSearchSort}
                  className="w-full sm:w-64"
                />
                <Select
                  value={`${sort}:${order}`}
                  onValueChange={(value) => {
                    setSort(value.split(":")[0]);
                    setOrder(value.split(":")[1]);
                    handleSearchSort();
                  }}
                >
                  <SelectTrigger className="w-full sm:w-48">
                    <SelectValue placeholder="Sort by" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="employid:asc">Employee ID (A-Z)</SelectItem>
                    <SelectItem value="employid:desc">Employee ID (Z-A)</SelectItem>
                    <SelectItem value="shop:asc">Shop (A-Z)</SelectItem>
                    <SelectItem value="shop:desc">Shop (Z-A)</SelectItem>
                    <SelectItem value="name:asc">Name (A-Z)</SelectItem>
                    <SelectItem value="name:desc">Name (Z-A)</SelectItem>
                    <SelectItem value="bank:asc">Bank (A-Z)</SelectItem>
                    <SelectItem value="bank:desc">Bank (Z-A)</SelectItem>
                    <SelectItem value="account:asc">Account (A-Z)</SelectItem>
                    <SelectItem value="account:desc">Account (Z-A)</SelectItem>
                    <SelectItem value="branch:asc">Branch (A-Z)</SelectItem>
                    <SelectItem value="branch:desc">Branch (Z-A)</SelectItem>
                    <SelectItem value="momonum:asc">Mobile Money Number (A-Z)</SelectItem>
                    <SelectItem value="momonum:desc">Mobile Money Number (Z-A)</SelectItem>
                    <SelectItem value="momoname:asc">Mobile Money Name (A-Z)</SelectItem>
                    <SelectItem value="momoname:desc">Mobile Money Name (Z-A)</SelectItem>
                    <SelectItem value="created_at:asc">Created (Oldest)</SelectItem>
                    <SelectItem value="created_at:desc">Created (Newest)</SelectItem>
                  </SelectContent>
                </Select>
                <Button onClick={handleExport} className="w-full sm:w-auto">
                  <Download className="h-4 w-4 mr-2" />
                  Export to Excel
                </Button>
                <Dialog
                  open={isOpen}
                  onOpenChange={(open) => {
                    setIsOpen(open);
                    if (!open) {
                      reset();
                      setEditingBankDetail(null);
                      setSelectedBank("");
                      setAvailableBranches([]);
                    }
                  }}
                >
                  <DialogTrigger asChild>
                    <Button className="w-full sm:w-auto">
                      <Plus className="h-4 w-4 mr-2" />
                      New Bank Detail Inhouse
                    </Button>
                  </DialogTrigger>
                  <DialogContent className="p-6 sm:max-w-2xl w-full max-h-[80vh] overflow-y-auto">
                    <DialogHeader>
                      <DialogTitle>{editingBankDetail ? "Edit Bank Detail Inhouse" : "Create New Bank Detail Inhouse"}</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={handleSubmit}>
                      <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 py-4">
                        <div className="grid gap-2">
                          <Label htmlFor="employid">Employee ID</Label>
                          <Input
                            id="employid"
                            value={data.employid}
                            onChange={(e) => setData("employid", e.target.value)}
                            required
                            className="w-full"
                          />
                          {errors.employid && <p className="text-red-500 text-sm">{errors.employid}</p>}
                        </div>
                        <div className="grid gap-2">
                          <Label htmlFor="shop">Shop</Label>
                          <Select
                            value={data.shop}
                            onValueChange={(value) => setData("shop", value)}
                            required
                          >
                            <SelectTrigger className="w-full">
                              <SelectValue placeholder="Select a shop" />
                            </SelectTrigger>
                            <SelectContent>
                              {[
                                'SBP AFRICA',
                                'SBP AFRICA LTD',
                                'SBP AFRICA LTD.',
                                'SBP SOLUTIONS',
                                'SBP SOLUTIONS LTD'
                              ].map((shop) => (
                                <SelectItem key={shop} value={shop}>
                                  {shop}
                                </SelectItem>
                              ))}
                            </SelectContent>
                          </Select>
                          {errors.shop && <p className="text-red-500 text-sm">{errors.shop}</p>}
                        </div>
                        <div className="grid gap-2">
                          <Label htmlFor="name">Name</Label>
                          <Input
                            id="name"
                            value={data.name}
                            onChange={(e) => setData("name", e.target.value)}
                            required
                            className="w-full"
                          />
                          {errors.name && <p className="text-red-500 text-sm">{errors.name}</p>}
                        </div>
                        <div className="grid gap-2">
                          <Label htmlFor="bank">Bank</Label>
                          <Select
                            value={data.bank || "none"}
                            onValueChange={(value) => setData("bank", value === "none" ? "" : value)}
                          >
                            <SelectTrigger className="w-full">
                              <SelectValue placeholder="Select a bank (optional)" />
                            </SelectTrigger>
                            <SelectContent>
                              <SelectItem value="none">None</SelectItem>
                              {bankNames.map((bankname) => {
                                console.log('Bank SelectItem value:', bankname);
                                return (
                                  <SelectItem key={bankname} value={bankname}>
                                    {bankname}
                                  </SelectItem>
                                );
                              })}
                            </SelectContent>
                          </Select>
                          {errors.bank && <p className="text-red-500 text-sm">{errors.bank}</p>}
                        </div>
                        <div className="grid gap-2">
                          <Label htmlFor="account">Account Number{data.bank && data.bank !== "none" ? " *" : ""}</Label>
                          <Input
                            id="account"
                            value={data.account}
                            onChange={(e) => setData("account", e.target.value)}
                            required={!!data.bank && data.bank !== "none"}
                            className="w-full"
                            disabled={!data.bank || data.bank === "none"}
                          />
                          {errors.account && <p className="text-red-500 text-sm">{errors.account}</p>}
                        </div>
                        <div className="grid gap-2">
                          <Label htmlFor="code">Sort Code{data.bank && data.bank !== "none" ? " *" : ""}</Label>
                          {(() => {
                            const normalize = (s: string) => s ? s.trim().toUpperCase() : "";
                            const normBank = data.bank && data.bank !== "none" ? normalize(data.bank) : "";
                            const isTargetBank = ['ABSA', 'ADB', 'ECOBANK'].some(b => normBank.includes(b));
                            const targetBankCodes = data.bank && data.bank !== "none"
                              ? bankCodes.filter(bc => {
                                  const nb = normalize(bc.bankname);
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
                                  disabled={!data.bank || data.bank === "none"}
                                >
                                  <SelectTrigger className="w-full">
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
                                id="code"
                                value={data.code}
                                onChange={(e) => setData("code", e.target.value)}
                                required={!!data.bank && data.bank !== "none"}
                                readOnly
                                className="w-full bg-gray-100"
                                disabled={!data.bank || data.bank === "none"}
                              />
                            );
                          })()}
                          {errors.code && <p className="text-red-500 text-sm">{errors.code}</p>}
                        </div>
                        <div className="grid gap-2 sm:col-span-2">
                          <Label htmlFor="branch">Branch{data.bank && data.bank !== "none" ? " *" : availableBranches.length === 0 ? " (Optional)" : ""}</Label>
                          {availableBranches.length > 0 ? (
                            <Select
                              value={data.branch}
                              onValueChange={(value) => setData("branch", value)}
                              disabled={!data.bank || data.bank === "none"}
                            >
                              <SelectTrigger className="w-full">
                                <SelectValue placeholder="Select a branch" />
                              </SelectTrigger>
                              <SelectContent>
                                {availableBranches.map((bc) => {
                                  console.log('Branch SelectItem value:', bc.branch);
                                  return (
                                    <SelectItem key={bc.id} value={bc.branch!}>
                                      {bc.branch}
                                    </SelectItem>
                                  );
                                })}
                              </SelectContent>
                            </Select>
                          ) : (
                            <Input
                              id="branch"
                              value={data.branch}
                              onChange={(e) => setData("branch", e.target.value)}
                              required={!!data.bank && data.bank !== "none"}
                              className="w-full"
                              disabled={!data.bank || data.bank === "none"}
                            />
                          )}
                          {errors.branch && <p className="text-red-500 text-sm">{errors.branch}</p>}
                        </div>
                        <div className="grid gap-2">
                          <Label htmlFor="momonum">Mobile Money Number{data.momoname ? " *" : ""}</Label>
                          <Input
                            id="momonum"
                            value={data.momonum}
                            onChange={(e) => setData("momonum", e.target.value)}
                            required={!!data.momoname}
                            className="w-full"
                          />
                          {errors.momonum && <p className="text-red-500 text-sm">{errors.momonum}</p>}
                        </div>
                        <div className="grid gap-2">
                          <Label htmlFor="momoname">Mobile Money Name{data.momonum ? " *" : ""}</Label>
                          <Input
                            id="momoname"
                            value={data.momoname}
                            onChange={(e) => setData("momoname", e.target.value)}
                            required={!!data.momonum}
                            className="w-full"
                          />
                          {errors.momoname && <p className="text-red-500 text-sm">{errors.momoname}</p>}
                        </div>
                      </div>
                      <DialogFooter className="flex flex-col sm:flex-row sm:justify-end gap-2">
                        <Button type="submit" disabled={processing}>
                          {editingBankDetail ? "Update" : "Create"}
                        </Button>
                      </DialogFooter>
                    </form>
                  </DialogContent>
                </Dialog>
              </div>
            </div>
            <div className="flex flex-col sm:flex-row justify-end gap-4">
              <Button
                variant="destructive"
                onClick={handleBulkDelete}
                disabled={selectedEmployIds.length === 0}
                className="w-full sm:w-auto bg-red-600 hover:bg-red-700"
              >
                <Trash className="h-4 w-4 mr-2" />
                Delete Selected
              </Button>
              <Button
                onClick={handleDownloadTemplate}
                className="w-full sm:w-auto bg-blue-600 hover:bg-blue-700"
              >
                <Download className="h-4 w-4 mr-2" />
                Download Template
              </Button>
              <Dialog
                open={isUploadOpen}
                onOpenChange={(open) => {
                  setIsUploadOpen(open);
                  if (!open) {
                    uploadForm.reset();
                  }
                }}
              >
                <DialogTrigger asChild>
                  <Button className="w-full sm:w-auto bg-green-600 hover:bg-green-700">
                    <Upload className="h-4 w-4 mr-2" />
                    Upload Excel
                  </Button>
                </DialogTrigger>
                <DialogContent className="p-6 sm:max-w-md w-full">
                  <DialogHeader>
                    <DialogTitle>Upload Bank Details Inhouse</DialogTitle>
                  </DialogHeader>
                  <form onSubmit={handleUpload}>
                    <div className="grid gap-4 py-4">
                      <div className="grid gap-2">
                        <Label htmlFor="file">Excel File</Label>
                        <Input
                          id="file"
                          type="file"
                          accept=".xlsx,.xls"
                          onChange={(e) => uploadForm.setData("file", e.target.files?.[0] || null)}
                          className="w-full"
                        />
                        {uploadForm.errors.file && <p className="text-red-500 text-sm">{uploadForm.errors.file}</p>}
                      </div>
                    </div>
                    <DialogFooter className="flex flex-col sm:flex-row sm:justify-end gap-2">
                      <Button type="submit" disabled={uploadForm.processing || !uploadForm.data.file} className="bg-green-600 hover:bg-green-700">
                        Upload
                      </Button>
                    </DialogFooter>
                  </form>
                </DialogContent>
              </Dialog>
            </div>
          </div>

          {bankDetails.data.length > 0 ? (
            <Card>
              <CardHeader>
                <CardTitle>Your Bank Details Inhouse</CardTitle>
                <div className="text-sm text-muted-foreground">
                  {searchCount !== null
                    ? `Showing ${searchCount} of ${totalRecords} records matching your search`
                    : `Total Records: ${totalRecords}`}
                </div>
              </CardHeader>
              <CardContent>
                <div className="block md:hidden space-y-4">
                  {bankDetails.data.map((bankDetail) => (
                    <div key={bankDetail.employid} className="border rounded-lg p-4 bg-background">
                      <div className="flex flex-col gap-2">
                        <div className="flex items-center gap-2">
                          <Checkbox
                            checked={selectedEmployIds.includes(bankDetail.employid)}
                            onCheckedChange={(checked) => handleSelect(bankDetail.employid, checked as boolean)}
                          />
                          <div className="font-semibold">{bankDetail.employid}</div>
                        </div>
                        <div className="text-sm text-muted-foreground">Shop: {bankDetail.shop || "N/A"}</div>
                        <div className="text-sm text-muted-foreground">Name: {bankDetail.name || "N/A"}</div>
                        <div className="text-sm text-muted-foreground">Bank: {bankDetail.bank || "N/A"}</div>
                        <div className="text-sm text-muted-foreground">Account: {bankDetail.account || "N/A"}</div>
                        <div className="text-sm text-muted-foreground">Sort Code: {bankDetail.code || "N/A"}</div>
                        <div className="text-sm text-muted-foreground">Branch: {bankDetail.branch || "N/A"}</div>
                        <div className="text-sm text-muted-foreground">Mobile Money Number: {bankDetail.momonum || "N/A"}</div>
                        <div className="text-sm text-muted-foreground">Mobile Money Name: {bankDetail.momoname || "N/A"}</div>
                        <div className="flex gap-2">
                          <Button variant="outline" size="sm" onClick={() => handleEdit(bankDetail)}>
                            <Pencil className="h-4 w-4" />
                          </Button>
                          <Button variant="destructive" size="sm" onClick={() => handleDelete(bankDetail.employid)}>
                            <Trash className="h-4 w-4" />
                          </Button>
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
                <div className="hidden md:block">
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>
                          <Checkbox
                            checked={selectedEmployIds.length === bankDetails.data.length && bankDetails.data.length > 0}
                            onCheckedChange={handleSelectAll}
                          />
                        </TableHead>
                        <TableHead>Employee ID</TableHead>
                        <TableHead>Shop</TableHead>
                        <TableHead>Name</TableHead>
                        <TableHead>Bank</TableHead>
                        <TableHead>Account</TableHead>
                        <TableHead>Sort Code</TableHead>
                        <TableHead>Branch</TableHead>
                        <TableHead>Mobile Money Number</TableHead>
                        <TableHead>Mobile Money Name</TableHead>
                        <TableHead className="text-right">Actions</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {bankDetails.data.map((bankDetail) => (
                        <TableRow key={bankDetail.employid}>
                          <TableCell>
                            <Checkbox
                              checked={selectedEmployIds.includes(bankDetail.employid)}
                              onCheckedChange={(checked) => handleSelect(bankDetail.employid, checked as boolean)}
                            />
                          </TableCell>
                          <TableCell>{bankDetail.employid}</TableCell>
                          <TableCell>{bankDetail.shop || "N/A"}</TableCell>
                          <TableCell>{bankDetail.name || "N/A"}</TableCell>
                          <TableCell>{bankDetail.bank || "N/A"}</TableCell>
                          <TableCell>{bankDetail.account || "N/A"}</TableCell>
                          <TableCell>{bankDetail.code || "N/A"}</TableCell>
                          <TableCell>{bankDetail.branch || "N/A"}</TableCell>
                          <TableCell>{bankDetail.momonum || "N/A"}</TableCell>
                          <TableCell>{bankDetail.momoname || "N/A"}</TableCell>
                          <TableCell className="text-right">
                            <div className="flex gap-2 justify-end">
                              <Button variant="outline" size="sm" onClick={() => handleEdit(bankDetail)}>
                                <Pencil className="h-4 w-4" />
                              </Button>
                              <Button variant="destructive" size="sm" onClick={() => handleDelete(bankDetail.employid)}>
                                <Trash className="h-4 w-4" />
                              </Button>
                            </div>
                          </TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                </div>
                <div className="flex justify-end mt-4">
                  <div className="flex gap-2">
                    {bankDetails.links.map((link, index) => (
                      <Button
                        key={index}
                        variant={link.active ? "default" : "outline"}
                        disabled={!link.url}
                        asChild={!!link.url}
                      >
                        {link.url ? (
                          <Link href={link.url} dangerouslySetInnerHTML={{ __html: link.label }} preserveState preserveScroll />
                        ) : (
                          <span dangerouslySetInnerHTML={{ __html: link.label }} />
                        )}
                      </Button>
                    ))}
                  </div>
                </div>
              </CardContent>
            </Card>
          ) : (
            <Card>
              <CardContent className="pt-6">
                <p className="text-center text-gray-500">No bank detail inhouse records found. Create a new record to get started!</p>
              </CardContent>
            </Card>
          )}

          <Dialog open={isDeleteOpen} onOpenChange={setIsDeleteOpen}>
            <DialogContent className="p-4 sm:max-w-md w-full">
              <DialogHeader>
                <DialogTitle>Confirm Deletion</DialogTitle>
              </DialogHeader>
              <p>Are you sure you want to delete this bank detail inhouse record? This action cannot be undone.</p>
              <DialogFooter className="flex flex-col sm:flex-row sm:justify-end gap-2">
                <Button variant="outline" onClick={() => setIsDeleteOpen(false)}>
                  Cancel
                </Button>
                <Button variant="destructive" onClick={confirmDelete} disabled={processing}>
                  Delete
                </Button>
              </DialogFooter>
            </DialogContent>
          </Dialog>

          <Dialog open={isBulkDeleteOpen} onOpenChange={setIsBulkDeleteOpen}>
            <DialogContent className="p-4 sm:max-w-md w-full">
              <DialogHeader>
                <DialogTitle>Confirm Bulk Deletion</DialogTitle>
              </DialogHeader>
              <p>Are you sure you want to delete {selectedEmployIds.length} bank detail inhouse record(s)? This action cannot be undone.</p>
              <DialogFooter className="flex flex-col sm:flex-row sm:justify-end gap-2">
                <Button variant="outline" onClick={() => setIsBulkDeleteOpen(false)}>
                  Cancel
                </Button>
                <Button variant="destructive" onClick={confirmBulkDelete} disabled={bulkDeleteForm.processing}>
                  Delete Selected
                </Button>
              </DialogFooter>
            </DialogContent>
          </Dialog>
        </div>
      </AppLayout>
    </ErrorBoundary>
  );
}
