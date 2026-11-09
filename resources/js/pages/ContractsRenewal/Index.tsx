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
  clientName?: string;
  flash?: {
    success?: string;
    error?: string;
  };
}

const formatDate = (date: string | undefined | null) => {
  if (!date) return "—";
  return date.split("T")[0];
};

export default function ContractRenewals({
  renewals = { data: [], links: [] },
  filters = {},
  totalRecords = 0,
  searchCount = null,
  clientName,
  flash,
}: Props) {
  const pageTitle = clientName ? `${clientName} Contract Renewals` : "Employee Contract Renewals";
  const breadcrumbs = [
    { title: "Contract Renewals", href: "/contract_renewals" },
    ...(clientName ? [{ title: clientName, href: "#" }] : []),
  ];

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

  const triggerSearch = useCallback(
    debounce((searchValue: string, sortValue: string, orderValue: string) => {
      router.get(
        window.location.pathname,
        { search: searchValue, sort: sortValue, order: orderValue },
        { preserveState: true, preserveScroll: true, replace: true }
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
      window.location.pathname,
      { search, sort: column, order: newOrder },
      { preserveState: true, preserveScroll: true, replace: true }
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
      startdate: renewal.startdate ? renewal.startdate.split("T")[0] : "",
      location: renewal.location || "",
      basic: renewal.basic || "",
      issuedate: renewal.issuedate ? renewal.issuedate.split("T")[0] : "",
      adjusted: renewal.adjusted || "",
      hremail: renewal.hremail || "",
      email: renewal.email || "",
    });
    setIsOpen(true);
  };

  const handleView = async (primary_id: number) => {
    try {
      const response = await fetch(route("contract_renewals.show", primary_id));
      if (!response.ok) throw new Error("Network response was not ok");
      const renewal = await response.json();
      setViewRenewal(renewal);
      setIsViewOpen(true);
    } catch {
      setToastMessage("Failed to load contract renewal details.");
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
          setToastMessage("Contract renewal updated successfully.");
          setToastType("success");
          setShowToast(true);
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
          setToastMessage("Contract renewal created successfully.");
          setToastType("success");
          setShowToast(true);
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
          setToastMessage("Contract renewal deleted successfully.");
          setToastType("success");
          setShowToast(true);
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
          setToastMessage("Contract renewals bulk uploaded successfully.");
          setToastType("success");
          setShowToast(true);
        },
        onError: (err) => {
          setToastMessage("Failed to upload file: " + Object.values(err).join(" | "));
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
        <Head title={pageTitle} />
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

          {/* Top Header & Actions Matching Employee Contracts */}
          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <h1 className="text-2xl font-bold">{pageTitle}</h1>
            <div className="flex flex-col sm:flex-row gap-4">
              <Input
                placeholder="Search by staff ID, employee name, or position..."
                value={search}
                onChange={handleSearchChange}
                className="w-full sm:w-64"
              />
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
                  <Button className="w-full sm:w-auto">
                    <Plus className="h-4 w-4 mr-2" />
                    Add Renewal
                  </Button>
                </DialogTrigger>
                <DialogContent className="p-4 sm:max-w-4xl w-full max-h-[80vh] overflow-y-auto">
                  <DialogHeader>
                    <DialogTitle>{editingRenewal ? "Edit Contract Renewal" : "Add New Contract Renewal"}</DialogTitle>
                  </DialogHeader>
                  <form onSubmit={handleSubmit}>
                    <div className="grid sm:grid-cols-2 gap-4 py-4">
                      <div className="grid gap-2">
                        <Label htmlFor="id">Staff ID *</Label>
                        <Input
                          id="id"
                          value={data.id}
                          onChange={(e) => setData("id", e.target.value)}
                          placeholder="e.g. SBP0012"
                          required
                          disabled={!!editingRenewal}
                          className="w-full"
                        />
                        {errors.id && <p className="text-red-500 text-sm">{errors.id}</p>}
                      </div>
                      <div className="grid gap-2">
                        <Label htmlFor="employeename">Employee Name *</Label>
                        <Input
                          id="employeename"
                          value={data.employeename}
                          onChange={(e) => setData("employeename", e.target.value)}
                          placeholder="Full Name"
                          required
                          className="w-full"
                        />
                        {errors.employeename && <p className="text-red-500 text-sm">{errors.employeename}</p>}
                      </div>
                      <div className="grid gap-2">
                        <Label htmlFor="position1">Position *</Label>
                        <Input
                          id="position1"
                          value={data.position1}
                          onChange={(e) => setData("position1", e.target.value)}
                          placeholder="Job Title"
                          required
                          className="w-full"
                        />
                        {errors.position1 && <p className="text-red-500 text-sm">{errors.position1}</p>}
                      </div>
                      <div className="grid gap-2">
                        <Label htmlFor="location">Location / Branch</Label>
                        <Input
                          id="location"
                          value={data.location}
                          onChange={(e) => setData("location", e.target.value)}
                          placeholder="Branch / Department"
                          className="w-full"
                        />
                        {errors.location && <p className="text-red-500 text-sm">{errors.location}</p>}
                      </div>
                      <div className="grid gap-2">
                        <Label htmlFor="startdate">Start Date *</Label>
                        <Input
                          id="startdate"
                          type="date"
                          value={data.startdate}
                          onChange={(e) => setData("startdate", e.target.value)}
                          required
                          className="w-full"
                        />
                        {errors.startdate && <p className="text-red-500 text-sm">{errors.startdate}</p>}
                      </div>
                      <div className="grid gap-2">
                        <Label htmlFor="issuedate">Issue Date *</Label>
                        <Input
                          id="issuedate"
                          type="date"
                          value={data.issuedate}
                          onChange={(e) => setData("issuedate", e.target.value)}
                          required
                          className="w-full"
                        />
                        {errors.issuedate && <p className="text-red-500 text-sm">{errors.issuedate}</p>}
                      </div>
                      <div className="grid gap-2">
                        <Label htmlFor="basic">Basic Salary</Label>
                        <Input
                          id="basic"
                          value={data.basic}
                          onChange={(e) => setData("basic", e.target.value)}
                          className="w-full"
                        />
                        {errors.basic && <p className="text-red-500 text-sm">{errors.basic}</p>}
                      </div>
                      <div className="grid gap-2">
                        <Label htmlFor="adjusted">Adjusted</Label>
                        <Input
                          id="adjusted"
                          value={data.adjusted}
                          onChange={(e) => setData("adjusted", e.target.value)}
                          className="w-full"
                        />
                        {errors.adjusted && <p className="text-red-500 text-sm">{errors.adjusted}</p>}
                      </div>
                      <div className="grid gap-2">
                        <Label htmlFor="hremail">HR Email *</Label>
                        <Input
                          id="hremail"
                          type="email"
                          value={data.hremail}
                          onChange={(e) => setData("hremail", e.target.value)}
                          required
                          className="w-full"
                        />
                        {errors.hremail && <p className="text-red-500 text-sm">{errors.hremail}</p>}
                      </div>
                      <div className="grid gap-2">
                        <Label htmlFor="email">Employee Email</Label>
                        <Input
                          id="email"
                          type="email"
                          value={data.email}
                          onChange={(e) => setData("email", e.target.value)}
                          className="w-full"
                        />
                        {errors.email && <p className="text-red-500 text-sm">{errors.email}</p>}
                      </div>
                    </div>
                    <DialogFooter className="flex flex-col sm:flex-row sm:justify-end gap-2">
                      <Button type="submit" disabled={processing}>
                        {editingRenewal ? "Update" : "Create"}
                      </Button>
                    </DialogFooter>
                  </form>
                </DialogContent>
              </Dialog>

              <Button
                className="w-full sm:w-auto bg-blue-500 hover:bg-blue-600"
                onClick={handleDownloadTemplate}
              >
                <FileSpreadsheet className="h-4 w-4 mr-2" />
                Download Template
              </Button>

              <Dialog
                open={isBulkUploadOpen}
                onOpenChange={(open) => {
                  setIsBulkUploadOpen(open);
                  if (!open) {
                    bulkUploadForm.reset();
                  }
                }}
              >
                <DialogTrigger asChild>
                  <Button className="w-full sm:w-auto bg-green-500 hover:bg-green-600">
                    <Upload className="h-4 w-4 mr-2" />
                    Bulk Upload
                  </Button>
                </DialogTrigger>
                <DialogContent className="p-4 sm:max-w-md w-full">
                  <DialogHeader>
                    <DialogTitle>Bulk Upload Contract Renewals</DialogTitle>
                  </DialogHeader>
                  <form onSubmit={handleBulkUpload}>
                    <div className="grid gap-4 py-4">
                      <div className="grid gap-2">
                        <Label htmlFor="file">Upload Excel File</Label>
                        <Input
                          id="file"
                          type="file"
                          accept=".xlsx,.xls,.csv,.ods"
                          onChange={(e) => {
                            const file = e.target.files?.[0];
                            if (file) bulkUploadForm.setData("file", file);
                          }}
                        />
                        {bulkUploadForm.errors.file && (
                          <p className="text-red-500 text-sm">{bulkUploadForm.errors.file}</p>
                        )}
                      </div>
                    </div>
                    <DialogFooter className="flex flex-col sm:flex-row sm:justify-end gap-2">
                      <Button type="submit" disabled={bulkUploadForm.processing || !bulkUploadForm.data.file}>
                        Upload
                      </Button>
                    </DialogFooter>
                  </form>
                </DialogContent>
              </Dialog>
            </div>
          </div>

          {/* View Preview Dialog */}
          <Dialog open={isViewOpen} onOpenChange={setIsViewOpen}>
            <DialogContent className="p-4 sm:max-w-4xl w-full max-h-[80vh] overflow-y-auto">
              <DialogHeader>
                <DialogTitle>Contract Renewal Preview</DialogTitle>
              </DialogHeader>
              {viewRenewal && (
                <div className="mt-4">
                  <iframe
                    src={`${route("contract_renewals.download", viewRenewal.primary_id)}?no_signature=1`}
                    className="w-full h-[600px] border rounded"
                    title="Contract Renewal Preview"
                  />
                </div>
              )}
            </DialogContent>
          </Dialog>

          {/* Delete Dialog */}
          <Dialog open={isDeleteOpen} onOpenChange={setIsDeleteOpen}>
            <DialogContent className="p-4 sm:max-w-md w-full">
              <DialogHeader>
                <DialogTitle>Confirm Deletion</DialogTitle>
              </DialogHeader>
              <p>Are you sure you want to delete this contract renewal? This action cannot be undone.</p>
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

          {/* Main Table Card */}
          {renewalList.length > 0 ? (
            <Card>
              <CardHeader>
                <CardTitle>{pageTitle}</CardTitle>
                <div className="text-sm text-muted-foreground">
                  {searchCount !== null && searchCount !== undefined
                    ? `Showing ${searchCount} of ${totalRecords} records matching your search`
                    : `Total Records: ${totalRecords}`}
                </div>
              </CardHeader>
              <CardContent>
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>No.</TableHead>
                      <TableHead>Download</TableHead>
                      <TableHead>
                        <Button
                          variant="ghost"
                          onClick={() => handleSort("id")}
                          className="flex items-center gap-1"
                        >
                          Staff ID
                          <ArrowUpDown className="h-4 w-4" />
                        </Button>
                      </TableHead>
                      <TableHead>
                        <Button
                          variant="ghost"
                          onClick={() => handleSort("employeename")}
                          className="flex items-center gap-1"
                        >
                          Employee Name
                          <ArrowUpDown className="h-4 w-4" />
                        </Button>
                      </TableHead>
                      <TableHead>
                        <Button
                          variant="ghost"
                          onClick={() => handleSort("position1")}
                          className="flex items-center gap-1"
                        >
                          Position
                          <ArrowUpDown className="h-4 w-4" />
                        </Button>
                      </TableHead>
                      <TableHead>
                        <Button
                          variant="ghost"
                          onClick={() => handleSort("location")}
                          className="flex items-center gap-1"
                        >
                          Location
                          <ArrowUpDown className="h-4 w-4" />
                        </Button>
                      </TableHead>
                      <TableHead>Start Date</TableHead>
                      <TableHead>Issue Date</TableHead>
                      <TableHead>HR Email</TableHead>
                      <TableHead>Employee Email</TableHead>
                      <TableHead className="text-right">Actions</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {renewalList.map((renewal, index) => (
                      <TableRow key={renewal.primary_id || renewal.id}>
                        <TableCell>{index + 1}</TableCell>
                        <TableCell>
                          {renewal.activate === "1" && (
                            <Button
                              variant="outline"
                              size="sm"
                              onClick={() => handleDownload(renewal.primary_id)}
                            >
                              <Download className="h-4 w-4" />
                            </Button>
                          )}
                        </TableCell>
                        <TableCell>{renewal.id}</TableCell>
                        <TableCell>{renewal.employeename}</TableCell>
                        <TableCell>{renewal.position1 || "—"}</TableCell>
                        <TableCell>{renewal.location || "—"}</TableCell>
                        <TableCell>{formatDate(renewal.startdate)}</TableCell>
                        <TableCell>{formatDate(renewal.issuedate)}</TableCell>
                        <TableCell>{renewal.hremail || "—"}</TableCell>
                        <TableCell>{renewal.email || "—"}</TableCell>
                        <TableCell className="text-right">
                          <div className="flex gap-2 justify-end">
                            <Button
                              variant="outline"
                              size="sm"
                              onClick={() => handleView(renewal.primary_id)}
                            >
                              <Eye className="h-4 w-4 text-blue-500" />
                            </Button>
                            <Button
                              variant="outline"
                              size="sm"
                              onClick={() => handleEdit(renewal)}
                            >
                              <Pencil className="h-4 w-4" />
                            </Button>
                            <Button
                              variant="destructive"
                              size="sm"
                              onClick={() => handleDelete(renewal.primary_id)}
                            >
                              <Trash className="h-4 w-4" />
                            </Button>
                          </div>
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
                <div className="flex justify-end mt-4">
                  <div className="flex gap-2">
                    {renewals.links?.map((link, index) => (
                      <Button
                        key={index}
                        variant={link.active ? "default" : "outline"}
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
                </div>
              </CardContent>
            </Card>
          ) : (
            <Card>
              <CardContent className="pt-6">
                <p className="text-center text-gray-500">
                  {clientName
                    ? `No contract renewals found for ${clientName}. Create a new contract renewal to get started!`
                    : "No contract renewals found. Create a new contract renewal to get started!"}
                </p>
              </CardContent>
            </Card>
          )}
        </div>
      </AppLayout>
    </ErrorBoundary>
  );
}
