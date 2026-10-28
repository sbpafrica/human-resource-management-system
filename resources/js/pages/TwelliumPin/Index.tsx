import { Head, useForm, router, InertiaFormProps } from "@inertiajs/react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Plus, Trash, CheckCircle, XCircle, Download, Upload, ArrowUpDown, Send, Key, RefreshCw, Smartphone } from "lucide-react";
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

interface TwelliumPinRecord {
  id: string;
  staff_id: string;
  name: string;
  company: string;
  shop: string;
  phone: string;
  password: string;
  pincode: string;
}

interface Props {
  pins: {
    data: TwelliumPinRecord[];
    links: any[];
  };
  filters: {
    search: string;
    sort: string;
    order: string;
  };
  totalRecords: number;
  searchCount: number | null;
  companies: string[];
  flash?: {
    success?: string;
    error?: string;
  };
}

interface ExtendedFormProps<T> extends InertiaFormProps<T> {}

const breadcrumbs = [
  { title: "Dashboard", href: "/dashboard" },
  { title: "Database", href: "/employeeportal" },
  { title: "Twellium Pin Code", href: "/twelliumpincode" },
];

export default function TwelliumPinIndex({
  pins = { data: [], links: [] },
  filters = { search: "", sort: "staff_id", order: "asc" },
  totalRecords = 0,
  searchCount = null,
  companies = [],
  flash,
}: Props) {
  const [isAddOpen, setIsAddOpen] = useState(false);
  const [isChangePinOpen, setIsChangePinOpen] = useState(false);
  const [isSingleSmsOpen, setIsSingleSmsOpen] = useState(false);
  const [isDeleteOpen, setIsDeleteOpen] = useState(false);
  const [isBulkDeleteOpen, setIsBulkDeleteOpen] = useState(false);
  const [isBulkUploadOpen, setIsBulkUploadOpen] = useState(false);

  const [selectedStaffId, setSelectedStaffId] = useState<string | null>(null);
  const [selectedPhone, setSelectedPhone] = useState<string>("");
  const [selectedIds, setSelectedIds] = useState<string[]>([]);

  const [showToast, setShowToast] = useState(false);
  const [toastMessage, setToastMessage] = useState("");
  const [toastType, setToastType] = useState<"success" | "error">("success");

  const [search, setSearch] = useState(filters?.search || "");
  const [sort, setSort] = useState(filters?.sort || "staff_id");
  const [order, setOrder] = useState(filters?.order || "asc");

  // Single Add Form
  const addForm = useForm({
    staff_id: "",
    company: "Twellium",
  }) as ExtendedFormProps<{
    staff_id: string;
    company: string;
  }>;

  // Change PIN Form
  const changePinForm = useForm({
    staff_id: "",
    send_sms: true,
  }) as ExtendedFormProps<{
    staff_id: string;
    send_sms: boolean;
  }>;

  // Single SMS Form
  const smsForm = useForm({
    message_type: "selected",
    selected_users: [] as string[],
  });

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
      router.get(route("twelliumpincode.index", { search: searchValue, sort: sortValue, order: orderValue }), {}, {
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
    router.get(route("twelliumpincode.index", { search, sort: column, order: newOrder }), {}, {
      preserveState: true,
      preserveScroll: true,
    });
  };

  const handleAddSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    addForm.post(route("twelliumpincode.store"), {
      onSuccess: () => {
        setIsAddOpen(false);
        addForm.reset();
      },
    });
  };

  const handleChangePinSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    changePinForm.post(route("twelliumpincode.change-pin"), {
      onSuccess: () => {
        setIsChangePinOpen(false);
        changePinForm.reset();
      },
    });
  };

  const handleSendSmsAll = () => {
    if (confirm("Are you sure you want to send PIN SMS to ALL registered employees in Twellium PINs?")) {
      smsForm.setData({
        message_type: "all",
        selected_users: [],
      });
      smsForm.post(route("twelliumpincode.send-sms"), {
        preserveScroll: true,
      });
    }
  };

  const handleSendSmsSelected = () => {
    if (selectedIds.length === 0) {
      alert("Please select at least one employee to send SMS.");
      return;
    }
    smsForm.setData({
      message_type: "selected",
      selected_users: selectedIds,
    });
    smsForm.post(route("twelliumpincode.send-sms"), {
      preserveScroll: true,
      onSuccess: () => {
        setSelectedIds([]);
      },
    });
  };

  const handleSendSingleSms = (staffId: string) => {
    smsForm.setData({
      message_type: "selected",
      selected_users: [staffId],
    });
    smsForm.post(route("twelliumpincode.send-sms"), {
      preserveScroll: true,
      onSuccess: () => {
        setIsSingleSmsOpen(false);
      },
    });
  };

  const handleDeleteConfirm = () => {
    if (selectedStaffId) {
      addForm.delete(route("twelliumpincode.destroy", selectedStaffId), {
        onSuccess: () => {
          setIsDeleteOpen(false);
          setSelectedStaffId(null);
        },
      });
    }
  };

  const handleBulkDeleteConfirm = () => {
    bulkDeleteForm.post(route("twelliumpincode.bulk-destroy"), {
      onSuccess: () => {
        setIsBulkDeleteOpen(false);
        setSelectedIds([]);
      },
    });
  };

  const handleBulkUploadSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    bulkUploadForm.post(route("twelliumpincode.bulk-upload"), {
      onSuccess: () => {
        setIsBulkUploadOpen(false);
        bulkUploadForm.reset();
      },
    });
  };

  const handleSelectAll = (checked: boolean) => {
    if (checked) {
      setSelectedIds(pins.data.map((item) => item.staff_id || item.id));
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
    window.location.href = route("twelliumpincode.export", { search, sort, order });
  };

  const handleDownloadTemplate = () => {
    window.location.href = route("twelliumpincode.download-template");
  };

  const openChangePinModal = (staffId: string) => {
    setSelectedStaffId(staffId);
    changePinForm.setData({
      staff_id: staffId,
      send_sms: true,
    });
    setIsChangePinOpen(true);
  };

  const openDeleteModal = (staffId: string) => {
    setSelectedStaffId(staffId);
    setIsDeleteOpen(true);
  };

  return (
    <ErrorBoundary>
      <AppLayout breadcrumbs={breadcrumbs}>
        <Head title="Twellium PIN Codes" />

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
                Twellium PIN Management
              </h1>
              <p className="text-sm text-gray-500">
                Manage, regenerate, and dispatch unique 4-digit PIN codes for Twellium and group employees.
              </p>
            </div>

            <div className="flex flex-wrap items-center gap-2">
              <Button onClick={() => setIsAddOpen(true)} className="bg-primary text-primary-foreground hover:bg-primary/90">
                <Plus className="mr-2 h-4 w-4" />
                Add Single User
              </Button>

              <Button onClick={() => setIsBulkUploadOpen(true)} variant="outline">
                <Upload className="mr-2 h-4 w-4" />
                Bulk Upload
              </Button>

              <Button onClick={handleExport} variant="outline">
                <Download className="mr-2 h-4 w-4" />
                Export
              </Button>

              <Button onClick={handleSendSmsAll} className="bg-green-600 hover:bg-green-700 text-white">
                <Send className="mr-2 h-4 w-4" />
                Send SMS to All
              </Button>

              {selectedIds.length > 0 && (
                <>
                  <Button onClick={handleSendSmsSelected} className="bg-blue-600 hover:bg-blue-700 text-white">
                    <Smartphone className="mr-2 h-4 w-4" />
                    Send SMS ({selectedIds.length})
                  </Button>
                  <Button onClick={() => setIsBulkDeleteOpen(true)} variant="destructive">
                    <Trash className="mr-2 h-4 w-4" />
                    Delete ({selectedIds.length})
                  </Button>
                </>
              )}
            </div>
          </div>

          {/* Search & Stats Card */}
          <Card>
            <CardHeader className="pb-3">
              <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <CardTitle className="text-base font-semibold">
                  Total Registered Users: <span className="text-primary">{totalRecords.toLocaleString()}</span>
                  {searchCount !== null && (
                    <span className="ml-2 text-sm font-normal text-muted-foreground">
                      (Filtered: {searchCount.toLocaleString()})
                    </span>
                  )}
                </CardTitle>

                <div className="w-full sm:w-80">
                  <ShadcnInput
                    type="search"
                    placeholder="Search Staff ID, Name, Company, Shop, Phone..."
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
                      <TableHead onClick={() => handleSort("staff_id")} className="cursor-pointer hover:bg-muted">
                        <div className="flex items-center gap-1 font-semibold">
                          Staff ID
                          <ArrowUpDown className="h-3 w-3" />
                        </div>
                      </TableHead>
                      <TableHead onClick={() => handleSort("name")} className="cursor-pointer hover:bg-muted">
                        <div className="flex items-center gap-1 font-semibold">
                          Name
                          <ArrowUpDown className="h-3 w-3" />
                        </div>
                      </TableHead>
                      <TableHead onClick={() => handleSort("company")} className="cursor-pointer hover:bg-muted">
                        <div className="flex items-center gap-1 font-semibold">
                          Company
                          <ArrowUpDown className="h-3 w-3" />
                        </div>
                      </TableHead>
                      <TableHead onClick={() => handleSort("shop")} className="cursor-pointer hover:bg-muted">
                        <div className="flex items-center gap-1 font-semibold">
                          Shop
                          <ArrowUpDown className="h-3 w-3" />
                        </div>
                      </TableHead>
                      <TableHead onClick={() => handleSort("phone")} className="cursor-pointer hover:bg-muted">
                        <div className="flex items-center gap-1 font-semibold">
                          Phone
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
                        <TableCell colSpan={8} className="h-32 text-center text-muted-foreground">
                          No Twellium PIN records found.
                        </TableCell>
                      </TableRow>
                    ) : (
                      pins.data.map((row) => {
                        const rowKey = row.staff_id || row.id;
                        return (
                          <TableRow key={rowKey} className="hover:bg-muted/30">
                            <TableCell className="text-center">
                              <Checkbox
                                checked={selectedIds.includes(rowKey)}
                                onCheckedChange={(checked) => handleSelectOne(rowKey, !!checked)}
                              />
                            </TableCell>
                            <TableCell className="font-mono font-medium text-gray-900">{row.staff_id || row.id}</TableCell>
                            <TableCell>{row.name || "—"}</TableCell>
                            <TableCell>{row.company || "—"}</TableCell>
                            <TableCell>{row.shop || "—"}</TableCell>
                            <TableCell className="font-mono text-sm">{row.phone || "—"}</TableCell>
                            <TableCell>
                              <span className="inline-flex items-center rounded-md bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-700/10 font-mono">
                                {row.password || row.pincode}
                              </span>
                            </TableCell>
                            <TableCell className="text-right space-x-2 whitespace-nowrap">
                              <Button
                                size="sm"
                                variant="outline"
                                onClick={() => openChangePinModal(rowKey)}
                                className="text-amber-600 hover:text-amber-700 hover:bg-amber-50"
                              >
                                <RefreshCw className="mr-1 h-3.5 w-3.5" />
                                Change PIN
                              </Button>
                              <Button
                                size="sm"
                                variant="outline"
                                onClick={() => handleSendSingleSms(rowKey)}
                                className="text-blue-600 hover:text-blue-700 hover:bg-blue-50"
                              >
                                <Send className="mr-1 h-3.5 w-3.5" />
                                Send SMS
                              </Button>
                              <Button
                                size="sm"
                                variant="ghost"
                                onClick={() => openDeleteModal(rowKey)}
                                className="text-red-600 hover:text-red-700 hover:bg-red-50"
                              >
                                <Trash className="h-4 w-4" />
                              </Button>
                            </TableCell>
                          </TableRow>
                        );
                      })
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

        {/* Add Single User Modal */}
        <Dialog open={isAddOpen} onOpenChange={setIsAddOpen}>
          <DialogContent className="sm:max-w-[425px]">
            <DialogHeader>
              <DialogTitle className="flex items-center gap-2">
                <Key className="h-5 w-5 text-primary" />
                Add Single User
              </DialogTitle>
            </DialogHeader>

            <form onSubmit={handleAddSubmit} className="space-y-4 py-2">
              <div className="space-y-2">
                <Label htmlFor="staff_id">Staff ID *</Label>
                <Input
                  id="staff_id"
                  placeholder="e.g. SBP/TWI/001"
                  value={addForm.data.staff_id}
                  onChange={(e) => addForm.setData("staff_id", e.target.value)}
                  required
                />
                {addForm.errors.staff_id && (
                  <p className="text-xs text-red-500">{addForm.errors.staff_id}</p>
                )}
              </div>

              <div className="space-y-2">
                <Label htmlFor="company">Company *</Label>
                {companies && companies.length > 0 ? (
                  <Select
                    value={addForm.data.company}
                    onValueChange={(val) => addForm.setData("company", val)}
                  >
                    <SelectTrigger>
                      <SelectValue placeholder="Select Company" />
                    </SelectTrigger>
                    <SelectContent>
                      {companies.map((c, idx) => (
                        <SelectItem key={idx} value={c}>
                          {c}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                ) : (
                  <Input
                    id="company"
                    placeholder="e.g. Twellium"
                    value={addForm.data.company}
                    onChange={(e) => addForm.setData("company", e.target.value)}
                    required
                  />
                )}
                {addForm.errors.company && (
                  <p className="text-xs text-red-500">{addForm.errors.company}</p>
                )}
              </div>

              <div className="rounded-md bg-blue-50 p-3 text-xs text-blue-700 border border-blue-200">
                A unique 4-digit PIN code will be automatically generated and assigned upon saving.
              </div>

              <DialogFooter className="pt-2">
                <Button type="button" variant="outline" onClick={() => setIsAddOpen(false)}>
                  Cancel
                </Button>
                <Button type="submit" disabled={addForm.processing}>
                  {addForm.processing ? "Adding..." : "Add User"}
                </Button>
              </DialogFooter>
            </form>
          </DialogContent>
        </Dialog>

        {/* Change PIN Modal */}
        <Dialog open={isChangePinOpen} onOpenChange={setIsChangePinOpen}>
          <DialogContent className="sm:max-w-[425px]">
            <DialogHeader>
              <DialogTitle className="flex items-center gap-2">
                <RefreshCw className="h-5 w-5 text-amber-600" />
                Change PIN Code
              </DialogTitle>
            </DialogHeader>

            <form onSubmit={handleChangePinSubmit} className="space-y-4 py-2">
              <p className="text-sm text-gray-700">
                Are you sure you want to generate a new PIN code for Staff ID:{" "}
                <strong className="font-mono text-primary">{selectedStaffId}</strong>?
              </p>

              <div className="flex items-center space-x-2 pt-2">
                <Checkbox
                  id="sendSMSPin"
                  checked={changePinForm.data.send_sms}
                  onCheckedChange={(checked) => changePinForm.setData("send_sms", !!checked)}
                />
                <Label htmlFor="sendSMSPin" className="text-sm font-medium cursor-pointer">
                  Send new PIN via SMS to employee phone number
                </Label>
              </div>

              <DialogFooter className="pt-4">
                <Button type="button" variant="outline" onClick={() => setIsChangePinOpen(false)}>
                  Cancel
                </Button>
                <Button type="submit" disabled={changePinForm.processing} className="bg-amber-600 hover:bg-amber-700 text-white">
                  {changePinForm.processing ? "Updating..." : "Change PIN"}
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
                Bulk Upload Users (Excel/CSV)
              </DialogTitle>
            </DialogHeader>

            <form onSubmit={handleBulkUploadSubmit} className="space-y-4 py-2">
              <div className="rounded-md border p-4 bg-muted/40 space-y-2">
                <p className="text-xs font-semibold text-gray-700">Spreadsheet Instructions:</p>
                <ul className="text-xs text-muted-foreground list-disc pl-4 space-y-1">
                  <li>Excel file must have headers: <code>staffid</code> and <code>company</code>.</li>
                  <li>A unique 4-digit PIN code will be generated automatically for each user.</li>
                  <li>Supported companies: Twellium, Transsion, Honda, Shefer, PZ Cussons, Transsnet, etc.</li>
                  <li>SMS will be automatically dispatched if phone numbers are found in contact records.</li>
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
                  accept=".xlsx,.xls,.csv,.ods"
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
                  {bulkUploadForm.processing ? "Uploading & Processing..." : "Upload & Generate PINs"}
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
              Are you sure you want to delete <strong>{selectedIds.length}</strong> selected Twellium PIN records?
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
