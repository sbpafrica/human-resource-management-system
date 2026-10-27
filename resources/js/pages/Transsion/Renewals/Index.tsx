// resources/js/Pages/Transsion/Renewals/Index.tsx

import { Head, router, useForm } from "@inertiajs/react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
  Pencil,
  Trash,
  Download,
  ArrowUpDown,
  Eye,
  Plus,
  Upload,
  FileSpreadsheet,
  CheckCircle,
  XCircle,
} from "lucide-react";
import AppLayout from "@/layouts/app-layout";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter,
  DialogTrigger,
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
import { useState, useEffect } from "react";
import debounce from "lodash/debounce";

interface Renewal {
  contract_id: number;
  id: string; // original contract ID
  employeename: string;
  position1: string;
  department: string;
  startdate: string;      // e.g. "2026-01-02T00:00:00.000000Z"
  enddate: string;
  expireddate: string;
  employername: string;
  issuedate: string;
  hremail: string;
  email: string | null;
  copymail: string | null;
  signature: string | null;
  activate: string;
  created_at: string;
  updated_at: string;
}

interface PaginationMeta {
  current_page: number;
  from: number | null;
  last_page: number;
  links: { url: string | null; label: string; active: boolean }[];
  path: string;
  per_page: number;
  to: number | null;
  total: number;
}

interface Props {
  renewals: {
    data: Renewal[];
  } & PaginationMeta;
  filters: {
    search?: string;
    sort?: string;
    order?: string;
  };
  flash?: {
    success?: string;
    error?: string;
  };
}

const breadcrumbs = [
  { title: "Good Ease", href: "/transsion" },
  { title: "Contract Renewals", href: "/transsion/contract-renewals" },
];

export default function TranssionContractRenewalsIndex({
  renewals,
  filters,
  flash,
}: Props) {
  const [search, setSearch] = useState(filters?.search ?? "");

  const currentSort = filters?.sort ?? "issuedate";
  const currentOrder = filters?.order ?? "desc";

  const [showToast, setShowToast] = useState(false);
  const [toastMessage, setToastMessage] = useState("");
  const [toastType, setToastType] = useState<"success" | "error">("success");

  const [isDeleteOpen, setIsDeleteOpen] = useState(false);
  const [deleteId, setDeleteId] = useState<number | null>(null);
  const [isViewOpen, setIsViewOpen] = useState(false);
  const [viewRenewal, setViewRenewal] = useState<Renewal | null>(null);

  // Bulk Upload
  const [isBulkOpen, setIsBulkOpen] = useState(false);
  const bulkForm = useForm<{ file: File | null }>({
    file: null,
  });

  // Single Create
  const [isCreateOpen, setIsCreateOpen] = useState(false);
  const createForm = useForm({
    id: "",
    employeename: "",
    position1: "",
    department: "",
    startdate: "",
    enddate: "",
    expireddate: "",
    employername: "",
    issuedate: "",
    hremail: "",
    email: "",
    copymail: "",
  });

  // Edit
  const [isEditOpen, setIsEditOpen] = useState(false);
  const [editingRenewal, setEditingRenewal] = useState<Renewal | null>(null);
  const editForm = useForm({
    employeename: "",
    position1: "",
    department: "",
    startdate: "",
    enddate: "",
    expireddate: "",
    employername: "",
    issuedate: "",
    hremail: "",
    email: "",
    copymail: "",
  });

  // Search debounce
  const debouncedUpdate = debounce((newSearch: string) => {
    router.get(
      route("transsion.contract-renewals.index"),
      { search: newSearch, sort: currentSort, order: currentOrder },
      { preserveState: true, preserveScroll: true, replace: true }
    );
  }, 400);

  useEffect(() => {
    debouncedUpdate(search);
    return () => debouncedUpdate.cancel();
  }, [search]);

  // Toast
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
      const timer = setTimeout(() => setShowToast(false), toastType === "success" ? 3000 : 5000);
      return () => clearTimeout(timer);
    }
  }, [showToast, toastType]);

  const handleSort = (column: string) => {
    const newOrder = currentSort === column && currentOrder === "asc" ? "desc" : "asc";
    router.get(
      route("transsion.contract-renewals.index"),
      { search, sort: column, order: newOrder },
      { preserveState: true, preserveScroll: true }
    );
  };

  const handleView = (renewal: Renewal) => {
    if (!renewal.id) {
      setToastMessage("Cannot preview: Missing original contract ID");
      setToastType("error");
      setShowToast(true);
      console.error("Preview failed - missing ID", renewal);
      return;
    }
    console.log("Opening preview for ID:", renewal.id);
    setViewRenewal(renewal);
    setIsViewOpen(true);
  };

  const handleDownload = (contractId: number) => {
    if (!contractId) {
      setToastMessage("Cannot download: Missing renewal ID");
      setToastType("error");
      setShowToast(true);
      return;
    }
    window.open(route("transsion.contract-renewals.download", contractId), "_blank");
  };

  const handleActivate = (contractId: number) => {
    if (!contractId) {
      setToastMessage("Cannot activate: Missing renewal ID");
      setToastType("error");
      setShowToast(true);
      return;
    }

    router.patch(
      `/transsion/contract-renewals/${contractId}/activate`,
      {},
      {
        preserveScroll: true,
        onSuccess: () => {
          setToastMessage("Renewal document activated successfully.");
          setToastType("success");
          setShowToast(true);
        },
        onError: () => {
          setToastMessage("Failed to activate renewal document.");
          setToastType("error");
          setShowToast(true);
        },
      }
    );
  };

  const handleDelete = (contract_id: number) => {
    setDeleteId(contract_id);
    setIsDeleteOpen(true);
  };

  const confirmDelete = () => {
    if (deleteId) {
      router.delete(route("transsion.contract-renewals.destroy", deleteId), {
        onSuccess: () => setIsDeleteOpen(false),
      });
    }
  };

  const handleBulkSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    bulkForm.post(route("transsion.contract-renewals.bulk-upload"), {
      forceFormData: true,
      onSuccess: () => {
        setIsBulkOpen(false);
        bulkForm.reset();
      },
    });
  };

  const handleCreateSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    createForm.post(route("transsion.contract-renewals.store"), {
      onSuccess: () => setIsCreateOpen(false),
    });
  };

  const handleEdit = (renewal: Renewal) => {
    console.log("Editing renewal:", renewal); // Debug

    // Format dates for input type="date" (YYYY-MM-DD)
    const formatDate = (dateStr: string) => dateStr ? dateStr.split("T")[0] : "";

    editForm.setData({
      employeename: renewal.employeename || "",
      position1: renewal.position1 || "",
      department: renewal.department || "",
      startdate: formatDate(renewal.startdate),
      enddate: formatDate(renewal.enddate),
      expireddate: formatDate(renewal.expireddate),
      employername: renewal.employername || "",
      issuedate: formatDate(renewal.issuedate),
      hremail: renewal.hremail || "",
      email: renewal.email || "",
      copymail: renewal.copymail || "",
    });

    setEditingRenewal(renewal);
    setIsEditOpen(true);
  };

  const handleEditSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (editingRenewal) {
      editForm.put(route("transsion.contract-renewals.update", editingRenewal.contract_id), {
        onSuccess: () => setIsEditOpen(false),
      });
    }
  };

  const handleDownloadTemplate = () => {
    window.location.href = route("transsion.contract-renewals.download-template");
  };

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Good Ease Contract Renewals" />

      {/* Toast */}
      {showToast && (
        <div
          className={`fixed top-4 right-4 z-50 flex items-center gap-3 rounded-lg p-4 shadow-xl text-white ${
            toastType === "success" ? "bg-green-600" : "bg-red-600"
          }`}
        >
          {toastType === "success" ? <CheckCircle className="h-5 w-5" /> : <XCircle className="h-5 w-5" />}
          <span>{toastMessage}</span>
        </div>
      )}

      <div className="container mx-auto p-6">
        <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
          <h1 className="text-2xl font-bold">Contract Renewals</h1>

          <div className="flex flex-col sm:flex-row gap-3">
            <Input
              placeholder="Search by original ID or name..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="w-full sm:w-72"
            />

            <Button onClick={() => setIsCreateOpen(true)}>
              <Plus className="h-4 w-4 mr-2" />
              Add Renewal
            </Button>

            <Button variant="outline" onClick={handleDownloadTemplate}>
              <FileSpreadsheet className="h-4 w-4 mr-2" />
              Template
            </Button>

            <Button variant="outline" onClick={() => setIsBulkOpen(true)}>
              <Upload className="h-4 w-4 mr-2" />
              Bulk Upload
            </Button>
          </div>
        </div>

        {/* View Preview Modal */}
        <Dialog open={isViewOpen} onOpenChange={setIsViewOpen}>
          <DialogContent className="max-w-5xl w-full h-[90vh]">
            <DialogHeader>
              <DialogTitle>Renewal Preview</DialogTitle>
            </DialogHeader>
            {viewRenewal ? (
              viewRenewal.contract_id ? (
                <iframe
                  src={`${route("transsion.contract-renewals.download", viewRenewal.contract_id)}?no_signature=1`}
                  className="w-full h-full border-0 rounded"
                  title="Renewal Preview"
                  onError={() => {
                    console.error("Preview iframe failed - Contract ID:", viewRenewal.contract_id);
                    setToastMessage("Failed to load preview (server error)");
                    setToastType("error");
                    setShowToast(true);
                  }}
                />
              ) : (
                <div className="h-full flex items-center justify-center text-red-600 font-medium">
                  Cannot load preview — missing renewal ID
                </div>
              )
            ) : (
              <div className="h-full flex items-center justify-center text-gray-500">
                No renewal selected
              </div>
            )}
          </DialogContent>
        </Dialog>

        {/* Delete Confirmation */}
        <Dialog open={isDeleteOpen} onOpenChange={setIsDeleteOpen}>
          <DialogContent>
            <DialogHeader>
              <DialogTitle>Confirm Deletion</DialogTitle>
            </DialogHeader>
            <p className="py-4">This action cannot be undone.</p>
            <DialogFooter>
              <Button variant="outline" onClick={() => setIsDeleteOpen(false)}>
                Cancel
              </Button>
              <Button variant="destructive" onClick={confirmDelete}>
                Delete
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>

        {/* Create Modal */}
        <Dialog open={isCreateOpen} onOpenChange={setIsCreateOpen}>
          <DialogContent className="max-w-4xl">
            <DialogHeader>
              <DialogTitle>Add New Renewal</DialogTitle>
            </DialogHeader>
            <form onSubmit={handleCreateSubmit} className="space-y-4">
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <Label>Original Contract ID *</Label>
                  <Input
                    value={createForm.data.id}
                    onChange={(e) => createForm.setData("id", e.target.value)}
                    required
                  />
                </div>
                <div>
                  <Label>Employee Name *</Label>
                  <Input
                    value={createForm.data.employeename}
                    onChange={(e) => createForm.setData("employeename", e.target.value)}
                    required
                  />
                </div>
                <div>
                  <Label>Position *</Label>
                  <Input
                    value={createForm.data.position1}
                    onChange={(e) => createForm.setData("position1", e.target.value)}
                    required
                  />
                </div>
                <div>
                  <Label>Department *</Label>
                  <Input
                    value={createForm.data.department}
                    onChange={(e) => createForm.setData("department", e.target.value)}
                    required
                  />
                </div>
                <div>
                  <Label>Start Date *</Label>
                  <Input
                    type="date"
                    value={createForm.data.startdate}
                    onChange={(e) => createForm.setData("startdate", e.target.value)}
                    required
                  />
                </div>
                <div>
                  <Label>End Date *</Label>
                  <Input
                    type="date"
                    value={createForm.data.enddate}
                    onChange={(e) => createForm.setData("enddate", e.target.value)}
                    required
                  />
                </div>
                <div>
                  <Label>Expired Date *</Label>
                  <Input
                    type="date"
                    value={createForm.data.expireddate}
                    onChange={(e) => createForm.setData("expireddate", e.target.value)}
                    required
                  />
                </div>
                <div>
                  <Label>Employer Name *</Label>
                  <Input
                    value={createForm.data.employername}
                    onChange={(e) => createForm.setData("employername", e.target.value)}
                    required
                  />
                </div>
                <div>
                  <Label>Issue Date *</Label>
                  <Input
                    type="date"
                    value={createForm.data.issuedate}
                    onChange={(e) => createForm.setData("issuedate", e.target.value)}
                    required
                  />
                </div>
                <div>
                  <Label>HR Email *</Label>
                  <Input
                    type="email"
                    value={createForm.data.hremail}
                    onChange={(e) => createForm.setData("hremail", e.target.value)}
                    required
                  />
                </div>
                <div>
                  <Label>Employee Email</Label>
                  <Input
                    type="email"
                    value={createForm.data.email}
                    onChange={(e) => createForm.setData("email", e.target.value)}
                  />
                </div>
                <div>
                  <Label>Copy Email</Label>
                  <Input
                    type="email"
                    value={createForm.data.copymail}
                    onChange={(e) => createForm.setData("copymail", e.target.value)}
                  />
                </div>
              </div>
              <DialogFooter>
                <Button type="submit" disabled={createForm.processing}>
                  Create Renewal
                </Button>
              </DialogFooter>
            </form>
          </DialogContent>
        </Dialog>

        {/* Edit Modal */}
        <Dialog open={isEditOpen} onOpenChange={setIsEditOpen}>
          <DialogContent className="max-w-4xl">
            <DialogHeader>
              <DialogTitle>Edit Renewal</DialogTitle>
            </DialogHeader>

            <form onSubmit={handleEditSubmit} className="space-y-4">
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <Label>Employee Name</Label>
                  <Input
                    value={editForm.data.employeename}
                    onChange={(e) => editForm.setData("employeename", e.target.value)}
                  />
                </div>
                <div>
                  <Label>Position</Label>
                  <Input
                    value={editForm.data.position1}
                    onChange={(e) => editForm.setData("position1", e.target.value)}
                  />
                </div>
                <div>
                  <Label>Department</Label>
                  <Input
                    value={editForm.data.department}
                    onChange={(e) => editForm.setData("department", e.target.value)}
                  />
                </div>
                <div>
                  <Label>Start Date</Label>
                  <Input
                    type="date"
                    value={editForm.data.startdate}
                    onChange={(e) => editForm.setData("startdate", e.target.value)}
                  />
                </div>
                <div>
                  <Label>End Date</Label>
                  <Input
                    type="date"
                    value={editForm.data.enddate}
                    onChange={(e) => editForm.setData("enddate", e.target.value)}
                  />
                </div>
                <div>
                  <Label>Expired Date</Label>
                  <Input
                    type="date"
                    value={editForm.data.expireddate}
                    onChange={(e) => editForm.setData("expireddate", e.target.value)}
                  />
                </div>
                <div>
                  <Label>Employer Name</Label>
                  <Input
                    value={editForm.data.employername}
                    onChange={(e) => editForm.setData("employername", e.target.value)}
                  />
                </div>
                <div>
                  <Label>Issue Date</Label>
                  <Input
                    type="date"
                    value={editForm.data.issuedate}
                    onChange={(e) => editForm.setData("issuedate", e.target.value)}
                  />
                </div>
                <div>
                  <Label>HR Email</Label>
                  <Input
                    type="email"
                    value={editForm.data.hremail}
                    onChange={(e) => editForm.setData("hremail", e.target.value)}
                  />
                </div>
                <div>
                  <Label>Employee Email</Label>
                  <Input
                    type="email"
                    value={editForm.data.email}
                    onChange={(e) => editForm.setData("email", e.target.value)}
                  />
                </div>
                <div>
                  <Label>Copy Email</Label>
                  <Input
                    type="email"
                    value={editForm.data.copymail}
                    onChange={(e) => editForm.setData("copymail", e.target.value)}
                  />
                </div>

              </div>
              <DialogFooter>
                <Button type="submit" disabled={editForm.processing}>
                  Update Renewal
                </Button>
              </DialogFooter>
            </form>
          </DialogContent>
        </Dialog>

        {/* Bulk Upload Modal */}
        <Dialog open={isBulkOpen} onOpenChange={setIsBulkOpen}>
          <DialogContent>
            <DialogHeader>
              <DialogTitle>Bulk Upload Renewals</DialogTitle>
            </DialogHeader>
            <form onSubmit={handleBulkSubmit}>
              <div className="py-4">
                <Label htmlFor="file">Upload Excel File</Label>
                <Input
                  id="file"
                  type="file"
                  accept=".xlsx,.xls,.csv,.ods"
                  onChange={(e) => bulkForm.setData("file", e.target.files?.[0] ?? null)}
                />
              </div>
              <DialogFooter>
                <Button type="submit" disabled={bulkForm.processing || !bulkForm.data.file}>
                  Upload
                </Button>
              </DialogFooter>
            </form>
          </DialogContent>
        </Dialog>

        <Card>
          <CardHeader>
            <CardTitle>Renewal Addendums</CardTitle>
          </CardHeader>
          <CardContent>
            {renewals.data.length === 0 ? (
              <div className="text-center py-12 text-muted-foreground">
                No renewals found.
              </div>
            ) : (
              <>
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead className="w-12">#</TableHead>
                      <TableHead>Download</TableHead>
                      <TableHead>Status</TableHead>
                      <TableHead>
                        <Button
                          variant="ghost"
                          onClick={() => handleSort("id")}
                          className="flex items-center gap-1 p-0 h-auto"
                        >
                          Original ID
                          <ArrowUpDown className="h-4 w-4" />
                        </Button>
                      </TableHead>
                      <TableHead>Employee Name</TableHead>
                      <TableHead>Position</TableHead>
                      <TableHead>Department</TableHead>
                      <TableHead>Start Date</TableHead>
                      <TableHead>End Date</TableHead>
                      <TableHead>Issue Date</TableHead>
                      <TableHead className="text-right">Actions</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {renewals.data.map((renewal, index) => (
                      <TableRow key={renewal.contract_id}>
                        <TableCell>{(renewals.from ?? 1) + index}</TableCell>
                        <TableCell>
                          <Button
                            variant="outline"
                            size="sm"
                            onClick={() => handleDownload(renewal.contract_id)}
                            title="Download Renewal Document & CV"
                          >
                            <Download className="h-4 w-4 text-green-600" />
                          </Button>
                        </TableCell>
                        <TableCell>
                          {renewal.signature ? (
                            <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-950/60 dark:text-green-300">
                              <CheckCircle className="h-3 w-3 mr-1" /> Signed
                            </span>
                          ) : (
                            <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-950/60 dark:text-green-300">
                              <CheckCircle className="h-3 w-3 mr-1" /> Activated
                            </span>
                          )}
                        </TableCell>
                        <TableCell>{renewal.id || "—"}</TableCell>
                        <TableCell>{renewal.employeename}</TableCell>
                        <TableCell>{renewal.position1}</TableCell>
                        <TableCell>{renewal.department}</TableCell>
                        <TableCell>{renewal.startdate?.substring(0, 10) || "—"}</TableCell>
                        <TableCell>{renewal.enddate?.substring(0, 10) || "—"}</TableCell>
                        <TableCell>{renewal.issuedate?.substring(0, 10) || "—"}</TableCell>
                        <TableCell className="text-right">
                          <div className="flex gap-2 justify-end">

                            {/* Preview */}
                            <Button
                              variant="outline"
                              size="sm"
                              onClick={() => handleView(renewal)}
                              title="Preview"
                            >
                              <Eye className="h-4 w-4 text-blue-500" />
                            </Button>

                            {/* Edit */}
                            <Button
                              variant="outline"
                              size="sm"
                              onClick={() => handleEdit(renewal)}
                              title="Edit renewal"
                            >
                              <Pencil className="h-4 w-4" />
                            </Button>

                            {/* Delete */}
                            <Button
                              variant="destructive"
                              size="sm"
                              onClick={() => handleDelete(renewal.contract_id)}
                              title="Delete"
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
                    {renewals.links.map((link, index) => (
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
              </>
            )}
          </CardContent>
        </Card>
      </div>
    </AppLayout>
  );
}