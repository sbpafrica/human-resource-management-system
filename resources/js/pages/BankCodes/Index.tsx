import { Head, Link, useForm } from "@inertiajs/react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Plus, Pencil, Trash, CheckCircle2, XCircle } from "lucide-react";
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

interface BankCode {
  id: number;
  bankname: string;
  branchcodes: string;
  branch: string | null; // Updated to allow null
}

interface Props {
  bankCodes: {
    data: BankCode[];
    links: any[];
  };
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
  { title: "Bank Codes", href: "/bankcodes" },
];

export default function BankCodeIndex({ bankCodes, filters, totalRecords, searchCount, flash }: Props) {
  const [isOpen, setIsOpen] = useState(false);
  const [editingBankCode, setEditingBankCode] = useState<BankCode | null>(null);
  const [isDeleteOpen, setIsDeleteOpen] = useState(false);
  const [deleteId, setDeleteId] = useState<number | null>(null);
  const [isBulkDeleteOpen, setIsBulkDeleteOpen] = useState(false);
  const [selectedIds, setSelectedIds] = useState<number[]>([]);
  const [showToast, setShowToast] = useState(false);
  const [toastMessage, setToastMessage] = useState("");
  const [toastType, setToastType] = useState<"success" | "error">("success");
  const [search, setSearch] = useState(filters.search || "");
  const [sort, setSort] = useState(filters.sort || "bankname");
  const [order, setOrder] = useState(filters.order || "asc");

  const { data, setData, post, put, processing, reset, delete: destroy, get, errors } = useForm({
    bankname: "",
    branchcodes: "",
    branch: "",
  });

  const bulkDeleteForm = useForm({
    ids: [] as number[],
  });

  // Sync bulkDeleteForm.ids with selectedIds
  useEffect(() => {
    bulkDeleteForm.setData('ids', selectedIds);
    console.log("Updated bulkDeleteForm.ids:", selectedIds);
  }, [selectedIds]);

  // Handle flash messages
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

  // Handle bulk delete form errors
  useEffect(() => {
    if (Object.keys(bulkDeleteForm.errors).length > 0) {
      const errorMessage = bulkDeleteForm.errors.ids || "Failed to delete selected records. Please try again.";
      setToastMessage(errorMessage);
      setToastType("error");
      setShowToast(true);
    }
  }, [bulkDeleteForm.errors]);

  // Handle toast timeout
  useEffect(() => {
    if (showToast) {
      const timer = setTimeout(() => setShowToast(false), 3000);
      return () => clearTimeout(timer);
    }
  }, [showToast]);

  const handleSubmit = (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    console.log("Submitting form data:", data);
    if (editingBankCode) {
      put(route("bankcodes.update", editingBankCode.id), {
        onSuccess: () => {
          setIsOpen(false);
          reset();
          setEditingBankCode(null);
        },
      });
    } else {
      post(route("bankcodes.store"), {
        onSuccess: () => {
          setIsOpen(false);
          reset();
        },
      });
    }
  };

  const handleEdit = (bankCode: BankCode) => {
    setEditingBankCode(bankCode);
    setData({
      bankname: bankCode.bankname,
      branchcodes: bankCode.branchcodes,
      branch: bankCode.branch || "",
    });
    setIsOpen(true);
  };

  const handleDelete = (id: number) => {
    setDeleteId(id);
    setIsDeleteOpen(true);
  };

  const confirmDelete = () => {
    if (deleteId) {
      destroy(route("bankcodes.destroy", deleteId), {
        onSuccess: () => {
          setIsDeleteOpen(false);
          setDeleteId(null);
        },
      });
    }
  };

  const handleSelect = (id: number, checked: boolean) => {
    setSelectedIds((prev) =>
      checked ? [...prev, id] : prev.filter((selectedId) => selectedId !== id)
    );
  };

  const handleSelectAll = (checked: boolean) => {
    setSelectedIds(checked ? bankCodes.data.map((bankCode) => bankCode.id) : []);
  };

  const handleBulkDelete = () => {
    if (selectedIds.length > 0) {
      setIsBulkDeleteOpen(true);
    }
  };

  const confirmBulkDelete = () => {
    console.log("Bulk delete triggered with ids:", bulkDeleteForm.data.ids);
    bulkDeleteForm.post(route("bankcodes.bulk-destroy"), {
      onSuccess: () => {
        console.log("Bulk delete successful");
        setIsBulkDeleteOpen(false);
        setSelectedIds([]);
      },
      onError: (errors) => {
        console.error("Bulk delete failed:", errors);
      },
      preserveState: true,
      preserveScroll: true,
    });
  };

  const handleSearchSort = () => {
    get(route("bankcodes.index", { search, sort, order }), {
      preserveState: true,
      preserveScroll: true,
    });
  };

  return (
    <ErrorBoundary>
      <AppLayout breadcrumbs={breadcrumbs}>
        <Head title="Bank Codes" />
        <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
          {showToast && (
            <div
              className={`fixed top-4 right-4 z-50 flex items-center gap-2 rounded-lg p-4 shadow-lg w-full sm:w-auto max-w-sm ${
                toastType === "success" ? "bg-green-500 text-white" : "bg-red-500 text-white"
              } animate-in fade-in slide-in-from-top-5`}
            >
              {toastType === "success" ? <CheckCircle2 className="h-5 w-5" /> : <XCircle className="h-5 w-5" />}
              <span>{toastMessage}</span>
            </div>
          )}

          <div className="flex flex-col gap-4">
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
              <h1 className="text-2xl font-bold">Bank Codes</h1>
              <div className="flex flex-col sm:flex-row gap-4">
                <ShadcnInput
                  placeholder="Search by bank name, branch code, or branch..."
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
                    <SelectItem value="bankname:asc">Bank Name (A-Z)</SelectItem>
                    <SelectItem value="bankname:desc">Bank Name (Z-A)</SelectItem>
                    <SelectItem value="branchcodes:asc">Branch Code (A-Z)</SelectItem>
                    <SelectItem value="branchcodes:desc">Branch Code (Z-A)</SelectItem>
                    <SelectItem value="branch:asc">Branch (A-Z)</SelectItem>
                    <SelectItem value="branch:desc">Branch (Z-A)</SelectItem>
                    <SelectItem value="created_at:asc">Created (Oldest)</SelectItem>
                    <SelectItem value="created_at:desc">Created (Newest)</SelectItem>
                  </SelectContent>
                </Select>
                <Dialog
                  open={isOpen}
                  onOpenChange={(open) => {
                    setIsOpen(open);
                    if (!open) {
                      reset();
                      setEditingBankCode(null);
                    }
                  }}
                >
                  <DialogTrigger asChild>
                    <Button className="w-full sm:w-auto">
                      <Plus className="h-4 w-4 mr-2" />
                      New Bank Code
                    </Button>
                  </DialogTrigger>
                  <DialogContent className="p-6 sm:max-w-2xl w-full max-h-[80vh] overflow-y-auto">
                    <DialogHeader>
                      <DialogTitle>{editingBankCode ? "Edit Bank Code" : "Create New Bank Code"}</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={handleSubmit}>
                      <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 py-4">
                        <div className="grid gap-2">
                          <Label htmlFor="bankname">Bank Name</Label>
                          <Input
                            id="bankname"
                            value={data.bankname}
                            onChange={(e) => setData("bankname", e.target.value)}
                            required
                            className="w-full"
                          />
                          {errors.bankname && <p className="text-red-500 text-sm">{errors.bankname}</p>}
                        </div>
                        <div className="grid gap-2">
                          <Label htmlFor="branchcodes">Branch Code</Label>
                          <Input
                            id="branchcodes"
                            value={data.branchcodes}
                            onChange={(e) => setData("branchcodes", e.target.value)}
                            required
                            className="w-full"
                          />
                          {errors.branchcodes && <p className="text-red-500 text-sm">{errors.branchcodes}</p>}
                        </div>
                        <div className="grid gap-2 sm:col-span-2">
                          <Label htmlFor="branch">Branch (Optional)</Label>
                          <Input
                            id="branch"
                            value={data.branch}
                            onChange={(e) => setData("branch", e.target.value)}
                            className="w-full"
                          />
                          {errors.branch && <p className="text-red-500 text-sm">{errors.branch}</p>}
                        </div>
                      </div>
                      <DialogFooter className="flex flex-col sm:flex-row sm:justify-end gap-2">
                        <Button type="submit" disabled={processing}>
                          {editingBankCode ? "Update" : "Create"}
                        </Button>
                      </DialogFooter>
                    </form>
                  </DialogContent>
                </Dialog>
              </div>
            </div>
            <div className="flex justify-end">
              <Button
                variant="destructive"
                onClick={handleBulkDelete}
                disabled={selectedIds.length === 0}
                className="w-full sm:w-auto"
              >
                <Trash className="h-4 w-4 mr-2" />
                Delete Selected
              </Button>
            </div>
          </div>

          {bankCodes.data.length > 0 ? (
            <Card>
              <CardHeader>
                <CardTitle>Your Bank Codes</CardTitle>
                <div className="text-sm text-muted-foreground">
                  {searchCount !== null
                    ? `Showing ${searchCount} of ${totalRecords} records matching your search`
                    : `Total Records: ${totalRecords}`}
                </div>
              </CardHeader>
              <CardContent>
                <div className="block md:hidden space-y-4">
                  {bankCodes.data.map((bankCode) => (
                    <div key={bankCode.id} className="border rounded-lg p-4 bg-background">
                      <div className="flex flex-col gap-2">
                        <div className="flex items-center gap-2">
                          <Checkbox
                            checked={selectedIds.includes(bankCode.id)}
                            onCheckedChange={(checked) => handleSelect(bankCode.id, checked as boolean)}
                          />
                          <div className="font-semibold">{bankCode.bankname}</div>
                        </div>
                        <div className="text-sm text-muted-foreground">Branch Code: {bankCode.branchcodes}</div>
                        <div className="text-sm text-muted-foreground">Branch: {bankCode.branch || "N/A"}</div>
                        <div className="flex gap-2">
                          <Button variant="outline" size="sm" onClick={() => handleEdit(bankCode)}>
                            <Pencil className="h-4 w-4" />
                          </Button>
                          <Button variant="destructive" size="sm" onClick={() => handleDelete(bankCode.id)}>
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
                            checked={selectedIds.length === bankCodes.data.length && bankCodes.data.length > 0}
                            onCheckedChange={handleSelectAll}
                          />
                        </TableHead>
                        <TableHead>Bank Name</TableHead>
                        <TableHead>Branch Code</TableHead>
                        <TableHead>Branch</TableHead>
                        <TableHead className="text-right">Actions</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {bankCodes.data.map((bankCode) => (
                        <TableRow key={bankCode.id}>
                          <TableCell>
                            <Checkbox
                              checked={selectedIds.includes(bankCode.id)}
                              onCheckedChange={(checked) => handleSelect(bankCode.id, checked as boolean)}
                            />
                          </TableCell>
                          <TableCell>{bankCode.bankname}</TableCell>
                          <TableCell>{bankCode.branchcodes}</TableCell>
                          <TableCell>{bankCode.branch || "N/A"}</TableCell>
                          <TableCell className="text-right">
                            <div className="flex gap-2 justify-end">
                              <Button variant="outline" size="sm" onClick={() => handleEdit(bankCode)}>
                                <Pencil className="h-4 w-4" />
                              </Button>
                              <Button variant="destructive" size="sm" onClick={() => handleDelete(bankCode.id)}>
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
                    {bankCodes.links.map((link, index) => (
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
                <p className="text-center text-gray-500">No bank code records found. Create a new record to get started!</p>
              </CardContent>
            </Card>
          )}

          <Dialog open={isDeleteOpen} onOpenChange={setIsDeleteOpen}>
            <DialogContent className="p-4 sm:max-w-md w-full">
              <DialogHeader>
                <DialogTitle>Confirm Deletion</DialogTitle>
              </DialogHeader>
              <p>Are you sure you want to delete this bank code record? This action cannot be undone.</p>
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
              <p>Are you sure you want to delete {selectedIds.length} bank code record(s)? This action cannot be undone.</p>
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
