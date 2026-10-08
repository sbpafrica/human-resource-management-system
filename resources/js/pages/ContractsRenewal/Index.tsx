import { Head, useForm, router } from "@inertiajs/react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
  Plus,
  Pencil,
  Trash,
  CheckCircle,
  XCircle,
  Download,
  Upload,
  ArrowUpDown,
  Eye,
  FileSpreadsheet,
  Search,
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
import { useState, useEffect, useCallback } from "react";
import ErrorBoundary from "@/components/ErrorBoundary";
import debounce from "lodash/debounce";

interface Renewal {
  primary_id: number;
  id: string;
  employeename: string;
  position1: string;
  startdate: string;
  location?: string | null;
  basic?: string | null;
  issuedate: string;
  adjusted?: string | null;
  hremail: string;
  email?: string | null;
  activate?: string | null;
}

interface Props {
  renewals?: {
    data: Renewal[];
    links: any[];
    current_page?: number;
    last_page?: number;
    total?: number;
  };
  filters?: {
    search?: string;
    sort?: string;
    order?: string;
  };
  totalRecords?: number;
  searchCount?: number | null;
  flash?: {
    success?: string;
    error?: string;
  };
}

const breadcrumbs = [
  { title: "Dashboard", href: "/dashboard" },
  { title: "Melcom", href: "#" },
  { title: "Contract Renewals", href: "/contract_renewals" },
];

const formatDate = (date: string | undefined | null) => {
  if (!date) return "—";
  return date.split("T")[0];
};

export default function ContractRenewals({
  renewals = { data: [], links: [] },
  filters = {},
  totalRecords = 0,
  searchCount = null,
  flash,
}: Props) {
  const [isOpen, setIsOpen] = useState(false);
  const [editingRenewal, setEditingRenewal] = useState<Renewal | null>(null);
  const [isDeleteOpen, setIsDeleteOpen] = useState(false);
  const [deleteRenewalId, setDeleteRenewalId] = useState<number | null>(null);
  const [isViewOpen, setIsViewOpen] = useState(false);
  const [viewRenewal, setViewRenewal] = useState<Renewal | null>(null);
  const [isBulkUploadOpen, setIsBulkUploadOpen] = useState(false);
  const [showToast, setShowToast] = useState(false);
  const [toastMessage, setToastMessage] = useState("");
  const [toastType, setToastType] = useState<"success" | "error">("success");
  const [search, setSearch] = useState(filters.search || "");
  const [sort, setSort] = useState(filters.sort || "employeename");
  const [order, setOrder] = useState(filters.order || "asc");

  const {
    data,
    setData,
    post,
    put,
    delete: destroy,
    processing,
    reset,
    errors,
  } = useForm({
    id: "",
    employeename: "",
    position1: "",
    startdate: "",
    location: "",
    basic: "",
    issuedate: "",
    adjusted: "",
    hremail: "",
    email: "",
  });

  const bulkUploadForm = useForm({
    file: null as File | null,
  });

  // Debounced search trigger using router.get
  const triggerSearch = useCallback(
    debounce((searchValue: string, sortValue: string, orderValue: string) => {
      router.get(
        route("contract_renewals.index"),
        { search: searchValue, sort: sortValue, order: orderValue },
        { preserveState: true, preserveScroll: true }
      );
    }, 400),
    []
  );

  const handleSearchChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const value = e.target.value;
    setSearch(value);
    triggerSearch(value, sort, order);
  };

  const handleSort = (column: string) => {
    const newOrder = sort === column && order === "asc" ? "desc" : "asc";
    setSort(column);
    setOrder(newOrder);
    router.get(
      route("contract_renewals.index"),
      { search, sort: column, order: newOrder },
      { preserveState: true, preserveScroll: true }
    );
  };

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
      setToastMessage(Object.values(errors).join(" | "));
      setToastType("error");
      setShowToast(true);
    }
    if (Object.keys(bulkUploadForm.errors).length > 0) {
      setToastMessage(bulkUploadForm.errors.file || "Failed to upload file.");
      setToastType("error");
      setShowToast(true);
    }
  }, [errors, bulkUploadForm.errors]);

  useEffect(() => {
    if (showToast) {
      const timeout = toastType === "error" ? 6000 : 3000;
      const timer = setTimeout(() => setShowToast(false), timeout);
      return () => clearTimeout(timer);
    }
  }, [showToast, toastType]);

  const handleEdit = (renewal: Renewal) => {
    setEditingRenewal(renewal);
    setData({
      id: renewal.id,
      employeename: renewal.employeename,
      position1: renewal.position1,
      startdate: formatDate(renewal.startdate),
      location: renewal.location || "",
      basic: renewal.basic || "",
      issuedate: formatDate(renewal.issuedate),
      adjusted: renewal.adjusted || "",
      hremail: renewal.hremail || "",
      email: renewal.email || "",
    });
    setIsOpen(true);
  };

  const handleView = async (primary_id: number) => {
    try {
      const response = await fetch(route("contract_renewals.show", primary_id));
      const renewalData = await response.json();
      setViewRenewal(renewalData);
      setIsViewOpen(true);
    } catch (error) {
      setToastMessage("Failed to load renewal details.");
      setToastType("error");
      setShowToast(true);
    }
  };

  const handleSubmit = (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    if (editingRenewal) {
      put(route("contract_renewals.update", editingRenewal.primary_id), {
        onSuccess: () => {
          setIsOpen(false);
          reset();
          setEditingRenewal(null);
        },
        onError: (err) => {
          setToastMessage("Failed to update renewal: " + Object.values(err).join(" | "));
          setToastType("error");
          setShowToast(true);
        },
      });
    } else {
      post(route("contract_renewals.store"), {
        onSuccess: () => {
          setIsOpen(false);
          reset();
        },
        onError: (err) => {
          setToastMessage("Failed to create renewal: " + Object.values(err).join(" | "));
          setToastType("error");
          setShowToast(true);
        },
      });
    }
  };

  const handleDelete = (primary_id: number) => {
    setDeleteRenewalId(primary_id);
    setIsDeleteOpen(true);
  };

  const confirmDelete = () => {
    if (deleteRenewalId) {
      destroy(route("contract_renewals.destroy", deleteRenewalId), {
        onSuccess: () => {
          setIsDeleteOpen(false);
          setDeleteRenewalId(null);
        },
        onError: (err) => {
          setToastMessage("Failed to delete renewal: " + Object.values(err).join(" | "));
          setToastType("error");
          setShowToast(true);
        },
      });
    }
  };

  const handleDownload = (primary_id: number) => {
    window.location.href = route("contract_renewals.download", primary_id);
  };

  const handleDownloadTemplate = () => {
    window.location.href = route("contract_renewals.download-template");
  };

  const handleBulkUpload = (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    if (bulkUploadForm.data.file) {
      bulkUploadForm.post(route("contract_renewals.bulk-upload"), {
        forceFormData: true,
        onSuccess: () => {
          setIsBulkUploadOpen(false);
          bulkUploadForm.reset();
        },
        onError: (err) => {
          setToastMessage("Bulk upload failed: " + Object.values(err).join(" | "));
          setToastType("error");
          setShowToast(true);
        },
      });
    }
  };

  const renewalList = renewals?.data || [];

  return (
    <ErrorBoundary>
      <AppLayout breadcrumbs={breadcrumbs}>
        <Head title="Contract Renewals - Melcom" />

        <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
          {/* Toast Notification */}
          {showToast && (
            <div
              className={`fixed top-4 right-4 z-50 flex items-center gap-2 rounded-lg p-4 shadow-lg w-full sm:w-auto max-w-md ${
                toastType === "success" ? "bg-green-600 text-white" : "bg-red-600 text-white"
              } animate-in fade-in slide-in-from-top-5`}
            >
              {toastType === "success" ? (
                <CheckCircle className="h-5 w-5 shrink-0" />
              ) : (
                <XCircle className="h-5 w-5 shrink-0" />
              )}
              <span className="text-sm">{toastMessage}</span>
              <Button
                variant="ghost"
                size="sm"
                className="ml-auto p-1 text-white hover:bg-black/20"
                onClick={() => setShowToast(false)}
              >
                <XCircle className="h-4 w-4" />
              </Button>
            </div>
          )}

          {/* Top Header & Actions */}
          <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <h1 className="text-2xl font-bold tracking-tight">Melcom Contract Renewals</h1>
              <p className="text-sm text-muted-foreground mt-1">
                Manage, renew, and generate Melcom staff employment contract renewals.
              </p>
            </div>

            <div className="flex flex-wrap items-center gap-2">
              <Dialog
                open={isOpen}
                onOpenChange={(open) => {
                  setIsOpen(open);
                  if (!open) {
                    reset();
                    setEditingRenewal(null);
                  }
                }}
              >
                <DialogTrigger asChild>
                  <Button size="sm" className="h-9 gap-1.5">
                    <Plus className="h-4 w-4" />
                    Add Renewal
                  </Button>
                </DialogTrigger>
                <DialogContent className="p-6 sm:max-w-3xl w-full max-h-[85vh] overflow-y-auto">
                  <DialogHeader>
                    <DialogTitle>
                      {editingRenewal ? "Edit Contract Renewal" : "Add New Contract Renewal"}
                    </DialogTitle>
                  </DialogHeader>
                  <form onSubmit={handleSubmit} className="space-y-4 pt-4">
                    <div className="grid sm:grid-cols-2 gap-4">
                      <div className="grid gap-2">
                        <Label htmlFor="id">Staff ID *</Label>
                        <Input
                          id="id"
                          value={data.id}
                          onChange={(e) => setData("id", e.target.value)}
                          placeholder="e.g. SBP0012"
                          required
                          disabled={!!editingRenewal}
                        />
                        {errors.id && <p className="text-red-500 text-xs">{errors.id}</p>}
                      </div>
                      <div className="grid gap-2">
                        <Label htmlFor="employeename">Employee Name *</Label>
                        <Input
                          id="employeename"
                          value={data.employeename}
                          onChange={(e) => setData("employeename", e.target.value)}
                          placeholder="Full Name"
                          required
                        />
                        {errors.employeename && (
                          <p className="text-red-500 text-xs">{errors.employeename}</p>
                        )}
                      </div>
                      <div className="grid gap-2">
                        <Label htmlFor="position1">Position *</Label>
                        <Input
                          id="position1"
                          value={data.position1}
                          onChange={(e) => setData("position1", e.target.value)}
                          placeholder="Job Title"
                          required
                        />
                        {errors.position1 && (
                          <p className="text-red-500 text-xs">{errors.position1}</p>
                        )}
                      </div>
                      <div className="grid gap-2">
                        <Label htmlFor="location">Location / Branch</Label>
                        <Input
                          id="location"
                          value={data.location}
                          onChange={(e) => setData("location", e.target.value)}
                          placeholder="Branch / Department"
                        />
                        {errors.location && (
                          <p className="text-red-500 text-xs">{errors.location}</p>
                        )}
                      </div>
                      <div className="grid gap-2">
                        <Label htmlFor="startdate">Start Date *</Label>
                        <Input
                          id="startdate"
                          type="date"
                          value={data.startdate}
                          onChange={(e) => setData("startdate", e.target.value)}
                          required
                        />
                        {errors.startdate && (
                          <p className="text-red-500 text-xs">{errors.startdate}</p>
                        )}
                      </div>
                      <div className="grid gap-2">
                        <Label htmlFor="issuedate">Issue Date *</Label>
                        <Input
                          id="issuedate"
                          type="date"
                          value={data.issuedate}
                          onChange={(e) => setData("issuedate", e.target.value)}
                          required
                        />
                        {errors.issuedate && (
                          <p className="text-red-500 text-xs">{errors.issuedate}</p>
                        )}
                      </div>
                      <div className="grid gap-2">
                        <Label htmlFor="basic">Basic Salary</Label>
                        <Input
                          id="basic"
                          value={data.basic}
                          onChange={(e) => setData("basic", e.target.value)}
                          placeholder="Salary amount"
                        />
                        {errors.basic && (
                          <p className="text-red-500 text-xs">{errors.basic}</p>
                        )}
                      </div>
                      <div className="grid gap-2">
                        <Label htmlFor="adjusted">Adjusted %</Label>
                        <Input
                          id="adjusted"
                          value={data.adjusted}
                          onChange={(e) => setData("adjusted", e.target.value)}
                          placeholder="Percentage"
                        />
                        {errors.adjusted && (
                          <p className="text-red-500 text-xs">{errors.adjusted}</p>
                        )}
                      </div>
                      <div className="grid gap-2">
                        <Label htmlFor="hremail">HR Email *</Label>
                        <Input
                          id="hremail"
                          type="email"
                          value={data.hremail}
                          onChange={(e) => setData("hremail", e.target.value)}
                          placeholder="hr@sbpafrica.group"
                          required
                        />
                        {errors.hremail && (
                          <p className="text-red-500 text-xs">{errors.hremail}</p>
                        )}
                      </div>
                      <div className="grid gap-2">
                        <Label htmlFor="email">Employee Email</Label>
                        <Input
                          id="email"
                          type="email"
                          value={data.email}
                          onChange={(e) => setData("email", e.target.value)}
                          placeholder="employee@example.com"
                        />
                        {errors.email && (
                          <p className="text-red-500 text-xs">{errors.email}</p>
                        )}
                      </div>
                    </div>
                    <DialogFooter className="pt-4">
                      <Button
                        type="button"
                        variant="outline"
                        onClick={() => setIsOpen(false)}
                      >
                        Cancel
                      </Button>
                      <Button type="submit" disabled={processing}>
                        {editingRenewal ? "Update Renewal" : "Create Renewal"}
                      </Button>
                    </DialogFooter>
                  </form>
                </DialogContent>
              </Dialog>

              <Button
                variant="outline"
                size="sm"
                className="h-9 gap-1.5"
                onClick={handleDownloadTemplate}
              >
                <FileSpreadsheet className="h-4 w-4" />
                Template
              </Button>

              <Dialog
                open={isBulkUploadOpen}
                onOpenChange={(open) => {
                  setIsBulkUploadOpen(open);
                  if (!open) bulkUploadForm.reset();
                }}
              >
                <DialogTrigger asChild>
                  <Button size="sm" className="h-9 gap-1.5 bg-green-600 hover:bg-green-700 text-white">
                    <Upload className="h-4 w-4" />
                    Bulk Upload
                  </Button>
                </DialogTrigger>
                <DialogContent className="p-6 sm:max-w-md w-full">
                  <DialogHeader>
                    <DialogTitle>Bulk Upload Renewals</DialogTitle>
                  </DialogHeader>
                  <form onSubmit={handleBulkUpload} className="space-y-4 pt-2">
                    <div className="grid gap-2">
                      <Label htmlFor="bulk-file">Choose Excel File (.xlsx, .xls)</Label>
                      <Input
                        id="bulk-file"
                        type="file"
                        accept=".xlsx,.xls"
                        onChange={(e) => {
                          const file = e.target.files?.[0];
                          if (file) bulkUploadForm.setData("file", file);
                        }}
                        required
                      />
                      {bulkUploadForm.errors.file && (
                        <p className="text-red-500 text-xs">{bulkUploadForm.errors.file}</p>
                      )}
                    </div>
                    <DialogFooter className="pt-2">
                      <Button
                        type="button"
                        variant="outline"
                        onClick={() => setIsBulkUploadOpen(false)}
                      >
                        Cancel
                      </Button>
                      <Button
                        type="submit"
                        disabled={bulkUploadForm.processing || !bulkUploadForm.data.file}
                        className="bg-green-600 hover:bg-green-700 text-white"
                      >
                        {bulkUploadForm.processing ? "Uploading..." : "Upload File"}
                      </Button>
                    </DialogFooter>
                  </form>
                </DialogContent>
              </Dialog>
            </div>
          </div>

          {/* Preview Modal */}
          <Dialog open={isViewOpen} onOpenChange={setIsViewOpen}>
            <DialogContent className="p-4 sm:max-w-4xl w-full max-h-[85vh] overflow-y-auto">
              <DialogHeader>
                <DialogTitle>Contract Renewal Preview</DialogTitle>
              </DialogHeader>
              {viewRenewal && (
                <div className="mt-2">
                  <iframe
                    src={`${route("contract_renewals.download", viewRenewal.primary_id)}?no_signature=1`}
                    className="w-full h-[600px] border rounded"
                    title="Renewal Preview"
                  />
                </div>
              )}
            </DialogContent>
          </Dialog>

          {/* Delete Confirmation Modal */}
          <Dialog open={isDeleteOpen} onOpenChange={setIsDeleteOpen}>
            <DialogContent className="p-6 sm:max-w-md w-full">
              <DialogHeader>
                <DialogTitle className="text-destructive">Confirm Deletion</DialogTitle>
              </DialogHeader>
              <p className="text-sm text-muted-foreground py-2">
                Are you sure you want to delete this contract renewal record? This action cannot be undone.
              </p>
              <DialogFooter className="gap-2 sm:gap-0">
                <Button variant="outline" onClick={() => setIsDeleteOpen(false)}>
                  Cancel
                </Button>
                <Button variant="destructive" onClick={confirmDelete} disabled={processing}>
                  Delete Record
                </Button>
              </DialogFooter>
            </DialogContent>
          </Dialog>

          {/* Main Table Card */}
          <Card>
            <CardHeader className="border-b px-6 py-4">
              <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                  <CardTitle className="text-lg">Renewals List</CardTitle>
                  <p className="text-xs text-muted-foreground mt-0.5">
                    {searchCount !== null
                      ? `Showing ${searchCount} matching search out of ${totalRecords} total records`
                      : `Total Records: ${totalRecords}`}
                  </p>
                </div>

                <div className="relative w-full sm:w-72">
                  <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                  <Input
                    type="search"
                    placeholder="Search by ID, name, position..."
                    value={search}
                    onChange={handleSearchChange}
                    className="pl-8 h-9 text-sm"
                  />
                </div>
              </div>
            </CardHeader>

            <CardContent className="p-0">
              {renewalList.length === 0 ? (
                <div className="flex flex-col items-center justify-center p-12 text-center">
                  <div className="h-16 w-16 rounded-full bg-muted flex items-center justify-center mb-4">
                    <FileSpreadsheet className="h-8 w-8 text-muted-foreground" />
                  </div>
                  <h3 className="text-lg font-semibold">No Contract Renewals Found</h3>
                  <p className="text-sm text-muted-foreground max-w-md mt-1 mb-6">
                    No Melcom contract renewal records were found. Add a renewal or upload an Excel
                    file to get started.
                  </p>
                  <div className="flex flex-wrap gap-3 justify-center">
                    <Button
                      onClick={handleDownloadTemplate}
                      variant="outline"
                      size="sm"
                      className="gap-2"
                    >
                      <Download className="h-4 w-4" />
                      Download Template
                    </Button>
                    <Button
                      onClick={() => setIsOpen(true)}
                      size="sm"
                      className="gap-2"
                    >
                      <Plus className="h-4 w-4" />
                      Add Renewal
                    </Button>
                  </div>
                </div>
              ) : (
                <>
                  <div className="overflow-x-auto">
                    <Table>
                      <TableHeader className="bg-muted/50">
                        <TableRow>
                          <TableHead className="w-12 text-center font-semibold">#</TableHead>
                          <TableHead className="w-12 text-center font-semibold">PDF</TableHead>
                          <TableHead
                            className="cursor-pointer font-semibold"
                            onClick={() => handleSort("id")}
                          >
                            <div className="flex items-center gap-1">
                              Staff ID
                              <ArrowUpDown className="h-3 w-3" />
                            </div>
                          </TableHead>
                          <TableHead
                            className="cursor-pointer font-semibold"
                            onClick={() => handleSort("employeename")}
                          >
                            <div className="flex items-center gap-1">
                              Employee Name
                              <ArrowUpDown className="h-3 w-3" />
                            </div>
                          </TableHead>
                          <TableHead className="font-semibold">Position</TableHead>
                          <TableHead className="font-semibold">Location</TableHead>
                          <TableHead className="font-semibold">Start Date</TableHead>
                          <TableHead className="font-semibold">Issue Date</TableHead>
                          <TableHead className="font-semibold">HR Email</TableHead>
                          <TableHead className="font-semibold">Employee Email</TableHead>
                          <TableHead className="text-right font-semibold">Actions</TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {renewalList.map((renewal, index) => (
                          <TableRow key={renewal.primary_id || renewal.id} className="hover:bg-muted/40">
                            <TableCell className="text-center text-xs text-muted-foreground">
                              {index + 1}
                            </TableCell>
                            <TableCell className="text-center">
                              {renewal.activate === "1" ? (
                                <Button
                                  variant="ghost"
                                  size="sm"
                                  className="h-8 w-8 p-0 text-blue-600 hover:text-blue-700"
                                  onClick={() => handleDownload(renewal.primary_id)}
                                  title="Download Signed PDF"
                                >
                                  <Download className="h-4 w-4" />
                                </Button>
                              ) : (
                                <span className="text-xs text-muted-foreground">—</span>
                              )}
                            </TableCell>
                            <TableCell className="font-mono text-xs font-medium">
                              {renewal.id}
                            </TableCell>
                            <TableCell className="font-medium">
                              {renewal.employeename}
                            </TableCell>
                            <TableCell className="text-xs">{renewal.position1 || "—"}</TableCell>
                            <TableCell className="text-xs text-muted-foreground">
                              {renewal.location || "—"}
                            </TableCell>
                            <TableCell className="text-xs font-mono">
                              {formatDate(renewal.startdate)}
                            </TableCell>
                            <TableCell className="text-xs font-mono">
                              {formatDate(renewal.issuedate)}
                            </TableCell>
                            <TableCell className="text-xs text-muted-foreground">
                              {renewal.hremail || "—"}
                            </TableCell>
                            <TableCell className="text-xs text-muted-foreground">
                              {renewal.email || "—"}
                            </TableCell>
                            <TableCell className="text-right">
                              <div className="flex gap-1 justify-end">
                                <Button
                                  variant="ghost"
                                  size="sm"
                                  className="h-8 w-8 p-0 text-blue-600 hover:bg-blue-50"
                                  onClick={() => handleView(renewal.primary_id)}
                                  title="Preview Contract"
                                >
                                  <Eye className="h-4 w-4" />
                                </Button>
                                <Button
                                  variant="ghost"
                                  size="sm"
                                  className="h-8 w-8 p-0 text-amber-600 hover:bg-amber-50"
                                  onClick={() => handleEdit(renewal)}
                                  title="Edit Renewal"
                                >
                                  <Pencil className="h-4 w-4" />
                                </Button>
                                <Button
                                  variant="ghost"
                                  size="sm"
                                  className="h-8 w-8 p-0 text-red-600 hover:bg-red-50"
                                  onClick={() => handleDelete(renewal.primary_id)}
                                  title="Delete Renewal"
                                >
                                  <Trash className="h-4 w-4" />
                                </Button>
                              </div>
                            </TableCell>
                          </TableRow>
                        ))}
                      </TableBody>
                    </Table>
                  </div>

                  {/* Pagination Links */}
                  {renewals?.links && renewals.links.length > 3 && (
                    <div className="flex justify-between items-center px-6 py-4 border-t">
                      <p className="text-xs text-muted-foreground">
                        Showing {renewalList.length} of {totalRecords} records
                      </p>
                      <div className="flex gap-1">
                        {renewals.links.map((link, index) => (
                          <Button
                            key={index}
                            variant={link.active ? "default" : "outline"}
                            size="sm"
                            disabled={!link.url}
                            onClick={() =>
                              link.url &&
                              router.get(
                                link.url,
                                { search, sort, order },
                                { preserveState: true, preserveScroll: true }
                              )
                            }
                            dangerouslySetInnerHTML={{ __html: link.label }}
                          />
                        ))}
                      </div>
                    </div>
                  )}
                </>
              )}
            </CardContent>
          </Card>
        </div>
      </AppLayout>
    </ErrorBoundary>
  );
}
