import { Head, useForm, router, InertiaFormProps } from "@inertiajs/react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Plus, Trash, CheckCircle, XCircle, Download, Upload, ArrowUpDown, Send, Key } from "lucide-react";
import AppLayout from "@/layouts/app-layout";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Checkbox } from "@/components/ui/checkbox";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { useState, useEffect, useCallback } from "react";
import { Input as ShadcnInput } from "@/components/ui/input";
import ErrorBoundary from "@/components/ErrorBoundary";
import debounce from "lodash/debounce";

interface EmployeePinRecord {
  id: string;
  staff_id: string;
  client: string;
  pincode: number;
}

interface Props {
  pins: {
    data: EmployeePinRecord[];
    links: any[];
  };
  filters: {
    search: string;
    sort: string;
    order: string;
  };
  totalRecords: number;
  searchCount: number | null;
  clients: string[];
  flash?: {
    success?: string;
    error?: string;
  };
}

interface ExtendedFormProps<T> extends InertiaFormProps<T> {}

const breadcrumbs = [
  { title: "Dashboard", href: "/dashboard" },
  { title: "Database", href: "/employeeportal" },
  { title: "Pin Code", href: "/employeepincode" },
];

export default function EmployeePinIndex({
  pins = { data: [], links: [] },
  filters = { search: "", sort: "id", order: "asc" },
  totalRecords = 0,
  searchCount = null,
  clients = [],
  flash,
}: Props) {
  const [isAddOpen, setIsAddOpen] = useState(false);
  const [isSmsOpen, setIsSmsOpen] = useState(false);
  const [isDeleteOpen, setIsDeleteOpen] = useState(false);
  const [isBulkDeleteOpen, setIsBulkDeleteOpen] = useState(false);
  const [isBulkUploadOpen, setIsBulkUploadOpen] = useState(false);

  const [selectedStaffId, setSelectedStaffId] = useState<string | null>(null);
  const [selectedIds, setSelectedIds] = useState<string[]>([]);

  const [showToast, setShowToast] = useState(false);
  const [toastMessage, setToastMessage] = useState("");
  const [toastType, setToastType] = useState<"success" | "error">("success");

  const [search, setSearch] = useState(filters?.search || "");
  const [sort, setSort] = useState(filters?.sort || "id");
  const [order, setOrder] = useState(filters?.order || "asc");

  // Single Add Form
  const addForm = useForm({
    staffid: "",
    shop: "",
  }) as ExtendedFormProps<{
    staffid: string;
    shop: string;
  }>;

  // Send SMS Form
  const smsForm = useForm({
    staffid: "",
    phone: "",
  }) as ExtendedFormProps<{
    staffid: string;
    phone: string;
  }>;

  // Bulk Delete Form
  const bulkDeleteForm = useForm({
    ids: [] as string[],
  });

  // Bulk Upload Form
  const bulkUploadForm = useForm({
    file: null as File | null,
  });

  // Search Debounce
  const debouncedSearch = useCallback(
    debounce((searchValue: string, sortValue: string, orderValue: string) => {
      router.get(route("employeepincode.index", { search: searchValue, sort: sortValue, order: orderValue }), {}, {
        preserveState: true,
        preserveScroll: true,
      });
    }, 300),
    []
  );

  useEffect(() => {
    bulkDeleteForm.setData("ids", selectedIds);
  }, [selectedIds]);

  useEffect(() => {
    if (flash?.success) {
      setToastMessage(flash.success);
      setToastType("success");
      setShowToast(true);
      const timer = setTimeout(() => setShowToast(false), 5000);
      return () => clearTimeout(timer);
    }
    if (flash?.error) {
      setToastMessage(flash.error);
      setToastType("error");
      setShowToast(true);
      const timer = setTimeout(() => setShowToast(false), 5000);
      return () => clearTimeout(timer);
    }
  }, [flash]);

  const handleSearchChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const value = e.target.value;
    setSearch(value);
    debouncedSearch(value, sort, order);
  };

  const handleSort = (column: string) => {
    const newOrder = sort === column && order === "asc" ? "desc" : "asc";
    setSort(column);
    setOrder(newOrder);
    router.get(route("employeepincode.index", { search, sort: column, order: newOrder }), {}, {
      preserveState: true,
      preserveScroll: true,
    });
  };

  const handleAddSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    addForm.post(route("employeepincode.store"), {
      onSuccess: () => {
        setIsAddOpen(false);
        addForm.reset();
      },
    });
  };

  const handleSmsSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    smsForm.post(route("employeepincode.send-sms"), {
      onSuccess: () => {
        setIsSmsOpen(false);
        smsForm.reset();
      },
    });
  };

  const handleDeleteConfirm = () => {
    if (selectedStaffId) {
      addForm.delete(route("employeepincode.destroy", selectedStaffId), {
        onSuccess: () => {
          setIsDeleteOpen(false);
          setSelectedStaffId(null);
        },
      });
    }
  };

  const handleBulkDeleteConfirm = () => {
    bulkDeleteForm.post(route("employeepincode.bulk-destroy"), {
      onSuccess: () => {
        setIsBulkDeleteOpen(false);
        setSelectedIds([]);
      },
    });
  };

  const handleBulkUploadSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    bulkUploadForm.post(route("employeepincode.bulk-upload"), {
      onSuccess: () => {
        setIsBulkUploadOpen(false);
        bulkUploadForm.reset();
      },
    });
  };

  const handleSelectAll = (checked: boolean) => {
    if (checked) {
      setSelectedIds(pins.data.map((item) => item.id));
    } else {
      setSelectedIds([]);
    }
  };

  const handleSelectOne = (id: string, checked: boolean) => {
    if (checked) {
      setSelectedIds((prev) => [...prev, id]);
    } else {
      setSelectedIds((prev) => prev.filter((item) => item !== id));
    }
  };

  const handleExport = () => {
    window.location.href = route("employeepincode.export", { search, sort, order });
  };

  const handleDownloadTemplate = () => {
    window.location.href = route("employeepincode.download-template");
  };

  const openSmsModal = (staffId: string) => {
    setSelectedStaffId(staffId);
    smsForm.setData({
      staffid: staffId,
      phone: "",
    });
    setIsSmsOpen(true);
  };

  const openDeleteModal = (staffId: string) => {
    setSelectedStaffId(staffId);
    setIsDeleteOpen(true);
  };

  return (
    <ErrorBoundary>
      <AppLayout breadcrumbs={breadcrumbs}>
        <Head title="Employee PIN Codes" />

        <div className="flex h-full flex-1 flex-col gap-4 p-4 md:gap-8 md:p-6">
          {/* Toast Notification */}
          {showToast && (
            <div
              className={`fixed top-4 right-4 z-50 flex items-center gap-2 rounded-lg p-4 text-white shadow-lg transition-all ${
                toastType === "success" ? "bg-emerald-600" : "bg-red-600"
              }`}
            >
              {toastType === "success" ? <CheckCircle className="h-5 w-5" /> : <XCircle className="h-5 w-5" />}
              <span>{toastMessage}</span>
              <button onClick={() => setShowToast(false)} className="ml-4 font-bold">
                ×
              </button>
            </div>
          )}

          {/* Action Bar */}
          <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <h1 className="flex items-center gap-2 text-2xl font-bold tracking-tight text-gray-900">
                <Key className="h-6 w-6 text-primary" />
                Employee PIN Codes
              </h1>
              <p className="text-sm text-gray-500">
                Generate, manage, and dispatch 4-digit PIN codes for employee payslip access.
              </p>
            </div>

            <div className="flex flex-wrap items-center gap-2">
              <Button onClick={() => setIsAddOpen(true)} className="bg-primary text-primary-foreground hover:bg-primary/90">
                <Plus className="mr-2 h-4 w-4" />
                Add Employee PIN
              </Button>

              <Button onClick={() => setIsBulkUploadOpen(true)} variant="outline">
                <Upload className="mr-2 h-4 w-4" />
                Bulk Upload
              </Button>

              <Button onClick={handleExport} variant="outline">
                <Download className="mr-2 h-4 w-4" />
                Export
              </Button>

              {selectedIds.length > 0 && (
                <Button onClick={() => setIsBulkDeleteOpen(true)} variant="destructive">
                  <Trash className="mr-2 h-4 w-4" />
                  Delete ({selectedIds.length})
                </Button>
              )}
            </div>
          </div>

          {/* Search & Stats Card */}
          <Card>
            <CardHeader className="pb-3">
              <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <CardTitle className="text-base font-semibold">
                  Total Records: <span className="text-primary">{totalRecords.toLocaleString()}</span>
                  {searchCount !== null && (
                    <span className="ml-2 text-sm font-normal text-muted-foreground">
                      (Filtered: {searchCount.toLocaleString()})
                    </span>
                  )}
                </CardTitle>

                <div className="w-full sm:w-72">
                  <ShadcnInput
                    type="search"
                    placeholder="Search Staff ID, Shop, PIN..."
                    value={search}
                    onChange={handleSearchChange}
                    className="w-full"
                  />
                </div>
              </div>
            </CardHeader>

            <CardContent>
              <div className="rounded-md border overflow-x-auto">
                <Table>
                  <TableHeader>
                    <TableRow className="bg-muted/50">
                      <TableHead className="w-12 text-center">
                        <Checkbox
                          checked={pins.data.length > 0 && selectedIds.length === pins.data.length}
                          onCheckedChange={(checked) => handleSelectAll(!!checked)}
                        />
                      </TableHead>
                      <TableHead onClick={() => handleSort("id")} className="cursor-pointer hover:bg-muted">
                        <div className="flex items-center gap-1 font-semibold">
                          Staff ID
                          <ArrowUpDown className="h-3 w-3" />
                        </div>
                      </TableHead>
                      <TableHead onClick={() => handleSort("client")} className="cursor-pointer hover:bg-muted">
                        <div className="flex items-center gap-1 font-semibold">
                          Shop / Client
                          <ArrowUpDown className="h-3 w-3" />
                        </div>
                      </TableHead>
                      <TableHead onClick={() => handleSort("pincode")} className="cursor-pointer hover:bg-muted">
                        <div className="flex items-center gap-1 font-semibold">
                          PIN Code
                          <ArrowUpDown className="h-3 w-3" />
                        </div>
                      </TableHead>
                      <TableHead className="text-right font-semibold">Actions</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {pins.data.length === 0 ? (
                      <TableRow>
                        <TableCell colSpan={5} className="h-32 text-center text-muted-foreground">
                          No employee PIN records found.
                        </TableCell>
                      </TableRow>
                    ) : (
                      pins.data.map((row) => (
                        <TableRow key={row.id} className="hover:bg-muted/30">
                          <TableCell className="text-center">
                            <Checkbox
                              checked={selectedIds.includes(row.id)}
                              onCheckedChange={(checked) => handleSelectOne(row.id, !!checked)}
                            />
                          </TableCell>
                          <TableCell className="font-medium text-gray-900">{row.id}</TableCell>
                          <TableCell>{row.client || "—"}</TableCell>
                          <TableCell>
                            <span className="inline-flex items-center rounded-md bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700 ring-1 ring-inset ring-blue-700/10">
                              {row.pincode}
                            </span>
                          </TableCell>
                          <TableCell className="text-right space-x-2">
                            <Button
                              size="sm"
                              variant="outline"
                              onClick={() => openSmsModal(row.id)}
                              className="text-blue-600 hover:text-blue-700 hover:bg-blue-50"
                            >
                              <Send className="mr-1 h-3.5 w-3.5" />
                              Send SMS
                            </Button>
                            <Button
                              size="sm"
                              variant="ghost"
                              onClick={() => openDeleteModal(row.id)}
                              className="text-red-600 hover:text-red-700 hover:bg-red-50"
                            >
                              <Trash className="h-4 w-4" />
                            </Button>
                          </TableCell>
                        </TableRow>
                      ))
                    )}
                  </TableBody>
                </Table>
              </div>

              {/* Pagination Links */}
              {pins.links && pins.links.length > 3 && (
                <div className="mt-4 flex flex-wrap justify-center gap-1">
                  {pins.links.map((link, idx) => (
                    <Button
                      key={idx}
                      variant={link.active ? "default" : "outline"}
                      size="sm"
                      disabled={!link.url}
                      onClick={() => link.url && router.get(link.url, {}, { preserveState: true, preserveScroll: true })}
                      dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                  ))}
                </div>
              )}
            </CardContent>
          </Card>
        </div>

        {/* Add Employee PIN Modal */}
        <Dialog open={isAddOpen} onOpenChange={setIsAddOpen}>
          <DialogContent className="sm:max-w-[425px]">
            <DialogHeader>
              <DialogTitle className="flex items-center gap-2">
                <Key className="h-5 w-5 text-primary" />
                Add New Employee PIN
              </DialogTitle>
            </DialogHeader>

            <form onSubmit={handleAddSubmit} className="space-y-4 py-2">
              <div className="space-y-2">
                <Label htmlFor="staffid">Staff ID *</Label>
                <Input
                  id="staffid"
                  placeholder="e.g. SBP00123"
                  value={addForm.data.staffid}
                  onChange={(e) => addForm.setData("staffid", e.target.value)}
                  required
                />
                {addForm.errors.staffid && (
                  <p className="text-xs text-red-500">{addForm.errors.staffid}</p>
                )}
              </div>

              <div className="space-y-2">
                <Label htmlFor="shop">Shop / Client *</Label>
                {clients && clients.length > 0 ? (
                  <Select
                    value={addForm.data.shop}
                    onValueChange={(val) => addForm.setData("shop", val)}
                  >
                    <SelectTrigger>
                      <SelectValue placeholder="Select Shop / Client" />
                    </SelectTrigger>
                    <SelectContent>
                      {clients.map((c, idx) => (
                        <SelectItem key={idx} value={c}>
                          {c}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                ) : (
                  <Input
                    id="shop"
                    placeholder="Enter Shop / Client Name"
                    value={addForm.data.shop}
                    onChange={(e) => addForm.setData("shop", e.target.value)}
                    required
                  />
                )}
                {addForm.errors.shop && (
                  <p className="text-xs text-red-500">{addForm.errors.shop}</p>
                )}
              </div>

              <div className="rounded-md bg-blue-50 p-3 text-xs text-blue-700 border border-blue-200">
                A unique 4-digit PIN code will be automatically generated upon saving.
              </div>

              <DialogFooter className="pt-2">
                <Button type="button" variant="outline" onClick={() => setIsAddOpen(false)}>
                  Cancel
                </Button>
                <Button type="submit" disabled={addForm.processing}>
                  {addForm.processing ? "Saving..." : "Save & Generate PIN"}
                </Button>
              </DialogFooter>
            </form>
          </DialogContent>
        </Dialog>

        {/* Send SMS Modal */}
        <Dialog open={isSmsOpen} onOpenChange={setIsSmsOpen}>
          <DialogContent className="sm:max-w-[425px]">
            <DialogHeader>
              <DialogTitle className="flex items-center gap-2">
                <Send className="h-5 w-5 text-blue-600" />
                Send PIN via SMS
              </DialogTitle>
            </DialogHeader>

            <form onSubmit={handleSmsSubmit} className="space-y-4 py-2">
              <div className="space-y-2">
                <Label htmlFor="smsStaffId">Staff ID</Label>
                <Input
                  id="smsStaffId"
                  value={smsForm.data.staffid}
                  readOnly
                  className="bg-muted font-mono"
                />
              </div>

              <div className="space-y-2">
                <Label htmlFor="phone">Recipient Phone Number *</Label>
                <Input
                  id="phone"
                  placeholder="e.g. 0241234567"
                  value={smsForm.data.phone}
                  onChange={(e) => smsForm.setData("phone", e.target.value)}
                  required
                />
                <p className="text-xs text-muted-foreground">Must be 10 digits starting with 0.</p>
                {smsForm.errors.phone && (
                  <p className="text-xs text-red-500">{smsForm.errors.phone}</p>
                )}
              </div>

              <DialogFooter className="pt-2">
                <Button type="button" variant="outline" onClick={() => setIsSmsOpen(false)}>
                  Cancel
                </Button>
                <Button type="submit" disabled={smsForm.processing} className="bg-blue-600 hover:bg-blue-700">
                  {smsForm.processing ? "Sending..." : "Send SMS"}
                </Button>
              </DialogFooter>
            </form>
          </DialogContent>
        </Dialog>

        {/* Bulk Upload Modal */}
        <Dialog open={isBulkUploadOpen} onOpenChange={setIsBulkUploadOpen}>
          <DialogContent className="sm:max-w-[500px]">
            <DialogHeader>
              <DialogTitle className="flex items-center gap-2">
                <Upload className="h-5 w-5 text-primary" />
                Bulk Upload Employees (Excel/CSV)
              </DialogTitle>
            </DialogHeader>

            <form onSubmit={handleBulkUploadSubmit} className="space-y-4 py-2">
              <div className="rounded-md border p-4 bg-muted/40 space-y-2">
                <p className="text-xs font-semibold text-gray-700">Spreadsheet Format Instructions:</p>
                <ul className="text-xs text-muted-foreground list-disc pl-4 space-y-1">
                  <li>Column 1: <strong>StaffID</strong> (Required)</li>
                  <li>Column 2: <strong>Shop Name</strong> (Required)</li>
                  <li>Unique 4-digit PINs will be generated automatically for every valid employee.</li>
                </ul>

                <Button
                  type="button"
                  variant="link"
                  size="sm"
                  onClick={handleDownloadTemplate}
                  className="p-0 h-auto text-primary text-xs font-semibold"
                >
                  <Download className="mr-1 h-3 w-3" />
                  Download Sample Template (.xlsx)
                </Button>
              </div>

              <div className="space-y-2">
                <Label htmlFor="fileUpload">Choose Excel/CSV File *</Label>
                <Input
                  id="fileUpload"
                  type="file"
                  accept=".xlsx, .xls, .csv"
                  onChange={(e) => bulkUploadForm.setData("file", e.target.files ? e.target.files[0] : null)}
                  required
                />
                {bulkUploadForm.errors.file && (
                  <p className="text-xs text-red-500">{bulkUploadForm.errors.file}</p>
                )}
              </div>

              <DialogFooter className="pt-2">
                <Button type="button" variant="outline" onClick={() => setIsBulkUploadOpen(false)}>
                  Cancel
                </Button>
                <Button type="submit" disabled={bulkUploadForm.processing}>
                  {bulkUploadForm.processing ? "Uploading & Generating..." : "Upload & Generate PINs"}
                </Button>
              </DialogFooter>
            </form>
          </DialogContent>
        </Dialog>

        {/* Single Delete Confirmation Modal */}
        <Dialog open={isDeleteOpen} onOpenChange={setIsDeleteOpen}>
          <DialogContent className="sm:max-w-[400px]">
            <DialogHeader>
              <DialogTitle className="text-red-600 flex items-center gap-2">
                <Trash className="h-5 w-5" />
                Confirm Deletion
              </DialogTitle>
            </DialogHeader>
            <p className="text-sm text-gray-600 py-2">
              Are you sure you want to delete the PIN record for Staff ID <strong>{selectedStaffId}</strong>?
            </p>
            <DialogFooter>
              <Button variant="outline" onClick={() => setIsDeleteOpen(false)}>
                Cancel
              </Button>
              <Button variant="destructive" onClick={handleDeleteConfirm} disabled={addForm.processing}>
                {addForm.processing ? "Deleting..." : "Yes, Delete"}
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>

        {/* Bulk Delete Confirmation Modal */}
        <Dialog open={isBulkDeleteOpen} onOpenChange={setIsBulkDeleteOpen}>
          <DialogContent className="sm:max-w-[400px]">
            <DialogHeader>
              <DialogTitle className="text-red-600 flex items-center gap-2">
                <Trash className="h-5 w-5" />
                Confirm Bulk Deletion
              </DialogTitle>
            </DialogHeader>
            <p className="text-sm text-gray-600 py-2">
              Are you sure you want to delete <strong>{selectedIds.length}</strong> selected PIN records?
            </p>
            <DialogFooter>
              <Button variant="outline" onClick={() => setIsBulkDeleteOpen(false)}>
                Cancel
              </Button>
              <Button variant="destructive" onClick={handleBulkDeleteConfirm} disabled={bulkDeleteForm.processing}>
                {bulkDeleteForm.processing ? "Deleting..." : "Yes, Delete Selected"}
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>

      </AppLayout>
    </ErrorBoundary>
  );
}
