// resources/js/pages/Olamfood/Contracts/Index.tsx

import { Head, useForm } from "@inertiajs/react";
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

interface OlamfoodContract {
  id: string;
  employeename: string;
  client: string | null;
  location: string | null;
  position1: string | null;
  duration: number | null;
  startdate: string | null;
  enddate: string | null;
  basic: string | null;
  employername: string | null;
  issuedate: string | null;
  hremail: string | null;
  ghanacard: string | null;
  digiaddress: string | null;
  insurance?: string | null;
  signature: string | null;
  activate: string;
  created_at: string;
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
  contracts: {
    data: OlamfoodContract[];
  } & PaginationMeta;
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
  { title: "Olam Food Contracts", href: "/olamfood/contracts" },
];

export default function OlamfoodContracts({
  contracts,
  filters,
  totalRecords,
  searchCount,
  flash,
}: Props) {
  const [isOpen, setIsOpen] = useState(false);
  const [editingContract, setEditingContract] = useState<OlamfoodContract | null>(null);
  const [isDeleteOpen, setIsDeleteOpen] = useState(false);
  const [deleteContractId, setDeleteContractId] = useState<string | null>(null);
  const [isViewOpen, setIsViewOpen] = useState(false);
  const [viewContract, setViewContract] = useState<{
  contract: OlamfoodContract;
  headerImg?: string;
  footerImg?: string;
  employerSigImg?: string;
  employeeSigImg?: string;
} | null>(null);
  const [isBulkUploadOpen, setIsBulkUploadOpen] = useState(false);
  const [showToast, setShowToast] = useState(false);
  const [toastMessage, setToastMessage] = useState("");
  const [toastType, setToastType] = useState<"success" | "error">("success");
  const [search, setSearch] = useState(filters.search || "");
  const [sort, setSort] = useState(filters.sort || "employeename");
  const [order, setOrder] = useState(filters.order || "asc");

  const { data, setData, get, post, put, delete: destroy, processing, reset, errors } = useForm({
    id: "",
    employeename: "",
    client: "",
    location: "",
    position1: "",
    duration: "",
    startdate: "",
    enddate: "",
    basic: "",
    employername: "",
    issuedate: "",
    hremail: "",
    ghanacard: "",
    digiaddress: "",
    insurance: "",
  });

  const bulkUploadForm = useForm({
    file: null as File | null,
  });

  const handleSort = (column: string) => {
    const newOrder = sort === column && order === "asc" ? "desc" : "asc";
    setSort(column);
    setOrder(newOrder);
  };

  const debouncedSearch = useCallback(
    debounce((searchValue: string, sortValue: string, orderValue: string) => {
      get(route("olamfood.contracts.index", { search: searchValue, sort: sortValue, order: orderValue }), {
        preserveState: true,
        preserveScroll: true,
      });
    }, 300),
    []
  );

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
    if (Object.keys(errors).length > 0 || Object.keys(bulkUploadForm.errors).length > 0) {
      const allErrors = [...Object.values(errors), ...Object.values(bulkUploadForm.errors)];
      setToastMessage(allErrors.join(" | "));
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

  useEffect(() => {
    debouncedSearch(search, sort, order);
  }, [search, sort, order, debouncedSearch]);

  const handleEdit = (contract: OlamfoodContract) => {
    setEditingContract(contract);

    const formatDate = (dateStr: string | null | undefined) => {
      if (!dateStr) return "";
      return dateStr.split("T")[0].split(" ")[0];
    };

    setData({
      id: contract.id,
      employeename: contract.employeename || "",
      client: contract.client || "",
      location: contract.location || "",
      position1: contract.position1 || "",
      duration: contract.duration?.toString() || "",
      startdate: formatDate(contract.startdate),
      enddate: formatDate(contract.enddate),
      basic: contract.basic || "",
      employername: contract.employername || "",
      issuedate: formatDate(contract.issuedate),
      hremail: contract.hremail || "",
      ghanacard: contract.ghanacard || "",
      digiaddress: contract.digiaddress || "",
      insurance: contract.insurance || "",
    });

    setIsOpen(true);
  };

  const handleView = async (id: string) => {
  try {
    setViewContract(null); // Show loading state
    const response = await fetch(route("olamfood.contracts.show", id));
    if (!response.ok) throw new Error("Failed to fetch contract");
    const data = await response.json();
    setViewContract(data); // data has { contract: {...}, headerImg: ..., ... }
    setIsViewOpen(true);
  } catch (error) {
    console.error("Preview error:", error);
    setToastMessage("Failed to load contract details.");
    setToastType("error");
    setShowToast(true);
  }
};

  const handleSubmit = (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    if (editingContract) {
      put(route("olamfood.contracts.update", editingContract.id), {
        onSuccess: () => {
          setIsOpen(false);
          reset();
          setEditingContract(null);
        },
      });
    } else {
      post(route("olamfood.contracts.store"), {
        onSuccess: () => {
          setIsOpen(false);
          reset();
        },
      });
    }
  };

  const handleDelete = (id: string) => {
    setDeleteContractId(id);
    setIsDeleteOpen(true);
  };

  const confirmDelete = () => {
    if (deleteContractId) {
      destroy(route("olamfood.contracts.destroy", deleteContractId), {
        onSuccess: () => {
          setIsDeleteOpen(false);
          setDeleteContractId(null);
        },
      });
    }
  };

  const handleDownload = (id: string) => {
    const url = route("olamfood.contracts.download", id);
    const link = document.createElement("a");
    link.href = url;
    link.download = `olamfood_contract_${id}.pdf`;
    link.click();
  };

  const handleDownloadTemplate = () => {
    const url = route("olamfood.contracts.download-template");
    const link = document.createElement("a");
    link.href = url;
    link.download = "olamfood_contract_template.xlsx";
    link.click();
  };

  const handleBulkUpload = (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    if (bulkUploadForm.data.file) {
      bulkUploadForm.post(route("olamfood.contracts.bulk-upload"), {
        forceFormData: true,
        onSuccess: () => {
          setIsBulkUploadOpen(false);
          bulkUploadForm.reset();
        },
      });
    }
  };

  return (
    <ErrorBoundary>
      <AppLayout breadcrumbs={breadcrumbs}>
        <Head title="Olam Food Contracts" />

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

          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <h1 className="text-2xl font-bold">Olam Food Employee Contracts</h1>
            <div className="flex flex-col sm:flex-row gap-4">
              <Input
                placeholder="Search by staff ID, employee name, or client..."
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                className="w-full sm:w-64"
              />

              <Dialog
                open={isOpen}
                onOpenChange={(open) => {
                  setIsOpen(open);
                  if (!open) {
                    reset();
                    setEditingContract(null);
                  }
                }}
              >
                <DialogTrigger asChild>
                  <Button className="w-full sm:w-auto">
                    <Plus className="h-4 w-4 mr-2" />
                    Add Contract
                  </Button>
                </DialogTrigger>
                <DialogContent className="p-4 sm:max-w-5xl w-full max-h-[80vh] overflow-y-auto">
                  <DialogHeader>
                    <DialogTitle>{editingContract ? "Edit Contract" : "Add New Contract"}</DialogTitle>
                  </DialogHeader>
                  <form onSubmit={handleSubmit}>
                    <div className="grid sm:grid-cols-2 gap-4 py-4">
                      <div className="grid gap-2">
                        <Label htmlFor="id">Staff ID</Label>
                        <Input
                          id="id"
                          value={data.id}
                          onChange={(e) => setData("id", e.target.value)}
                          required
                          disabled={!!editingContract}
                        />
                        {errors.id && <p className="text-red-500 text-sm">{errors.id}</p>}
                      </div>

                      <div className="grid gap-2">
                        <Label htmlFor="employeename">Employee Name</Label>
                        <Input
                          id="employeename"
                          value={data.employeename}
                          onChange={(e) => setData("employeename", e.target.value)}
                          required
                        />
                        {errors.employeename && <p className="text-red-500 text-sm">{errors.employeename}</p>}
                      </div>

                      <div className="grid gap-2">
                        <Label htmlFor="client">Client</Label>
                        <Input
                          id="client"
                          value={data.client}
                          onChange={(e) => setData("client", e.target.value)}
                        />
                        {errors.client && <p className="text-red-500 text-sm">{errors.client}</p>}
                      </div>

                      <div className="grid gap-2">
                        <Label htmlFor="location">Location</Label>
                        <Input
                          id="location"
                          value={data.location}
                          onChange={(e) => setData("location", e.target.value)}
                          required
                        />
                        {errors.location && <p className="text-red-500 text-sm">{errors.location}</p>}
                      </div>

                      <div className="grid gap-2">
                        <Label htmlFor="position1">Position</Label>
                        <Input
                          id="position1"
                          value={data.position1}
                          onChange={(e) => setData("position1", e.target.value)}
                          required
                        />
                        {errors.position1 && <p className="text-red-500 text-sm">{errors.position1}</p>}
                      </div>

                      <div className="grid gap-2">
                        <Label htmlFor="duration">Duration (Months)</Label>
                        <Input
                          id="duration"
                          type="number"
                          value={data.duration}
                          onChange={(e) => setData("duration", e.target.value)}
                          required
                        />
                        {errors.duration && <p className="text-red-500 text-sm">{errors.duration}</p>}
                      </div>

                      <div className="grid gap-2">
                        <Label htmlFor="startdate">Start Date</Label>
                        <Input
                          id="startdate"
                          type="date"
                          value={data.startdate}
                          onChange={(e) => setData("startdate", e.target.value)}
                          required
                        />
                        {errors.startdate && <p className="text-red-500 text-sm">{errors.startdate}</p>}
                      </div>

                      <div className="grid gap-2">
                        <Label htmlFor="enddate">End Date</Label>
                        <Input
                          id="enddate"
                          type="date"
                          value={data.enddate}
                          onChange={(e) => setData("enddate", e.target.value)}
                          required
                        />
                        {errors.enddate && <p className="text-red-500 text-sm">{errors.enddate}</p>}
                      </div>

                      <div className="grid gap-2">
                        <Label htmlFor="basic">Basic Salary</Label>
                        <Input
                          id="basic"
                          value={data.basic}
                          onChange={(e) => setData("basic", e.target.value)}
                          required
                        />
                        {errors.basic && <p className="text-red-500 text-sm">{errors.basic}</p>}
                      </div>

                      <div className="grid gap-2">
                        <Label htmlFor="employername">Employer Name</Label>
                        <Input
                          id="employername"
                          value={data.employername}
                          onChange={(e) => setData("employername", e.target.value)}
                          required
                        />
                        {errors.employername && <p className="text-red-500 text-sm">{errors.employername}</p>}
                      </div>

                      <div className="grid gap-2">
                        <Label htmlFor="issuedate">Issue Date</Label>
                        <Input
                          id="issuedate"
                          type="date"
                          value={data.issuedate}
                          onChange={(e) => setData("issuedate", e.target.value)}
                          required
                        />
                        {errors.issuedate && <p className="text-red-500 text-sm">{errors.issuedate}</p>}
                      </div>

                      <div className="grid gap-2">
                        <Label htmlFor="hremail">HR Email</Label>
                        <Input
                          id="hremail"
                          type="email"
                          value={data.hremail}
                          onChange={(e) => setData("hremail", e.target.value)}
                          required
                        />
                        {errors.hremail && <p className="text-red-500 text-sm">{errors.hremail}</p>}
                      </div>

                      <div className="grid gap-2">
                        <Label htmlFor="ghanacard">Ghana Card</Label>
                        <Input
                          id="ghanacard"
                          value={data.ghanacard}
                          onChange={(e) => setData("ghanacard", e.target.value)}
                        />
                        {errors.ghanacard && <p className="text-red-500 text-sm">{errors.ghanacard}</p>}
                      </div>

                      <div className="grid gap-2">
                        <Label htmlFor="digiaddress">Digital Address</Label>
                        <Input
                          id="digiaddress"
                          value={data.digiaddress}
                          onChange={(e) => setData("digiaddress", e.target.value)}
                        />
                        {errors.digiaddress && <p className="text-red-500 text-sm">{errors.digiaddress}</p>}
                      </div>

                      <div className="grid gap-2">
                        <Label htmlFor="insurance">Insurance (Optional)</Label>
                        <Input
                          id="insurance"
                          value={data.insurance}
                          onChange={(e) => setData("insurance", e.target.value)}
                          placeholder="e.g. Medical & Life Insurance"
                        />
                        {errors.insurance && <p className="text-red-500 text-sm">{errors.insurance}</p>}
                      </div>
                    </div>

                    <DialogFooter className="flex flex-col sm:flex-row sm:justify-end gap-2">
                      <Button type="submit" disabled={processing}>
                        {editingContract ? "Update" : "Create"}
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
                  if (!open) bulkUploadForm.reset();
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
                    <DialogTitle>Bulk Upload Olam Food Contracts</DialogTitle>
                  </DialogHeader>
                  <form onSubmit={handleBulkUpload}>
                    <div className="grid gap-4 py-4">
                      <div className="grid gap-2">
                        <Label htmlFor="file">Upload Excel File</Label>
                        <Input
                          id="file"
                          type="file"
                          accept=".xlsx,.xls"
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
                    <DialogFooter>
                      <Button
                        type="submit"
                        disabled={bulkUploadForm.processing || !bulkUploadForm.data.file}
                      >
                        Upload
                      </Button>
                    </DialogFooter>
                  </form>
                </DialogContent>
              </Dialog>
            </div>
          </div>

          {/* Preview Dialog */}
<Dialog open={isViewOpen} onOpenChange={setIsViewOpen}>
  <DialogContent className="p-4 sm:max-w-4xl w-full max-h-[80vh] overflow-y-auto">
    <DialogHeader>
      <DialogTitle>Contract Preview</DialogTitle>
    </DialogHeader>

    {viewContract && viewContract.contract ? (
      <div className="mt-4">
        <iframe
          src={`${route("olamfood.contracts.download", viewContract.contract.id)}?no_signature=1`}
          className="w-full h-[600px] border rounded"
          title="Olam Food Contract Preview"
        />
      </div>
    ) : (
      <div className="flex items-center justify-center h-[600px]">
        <div className="text-gray-500 animate-pulse">Loading preview...</div>
      </div>
    )}
  </DialogContent>
</Dialog>

          {/* Delete Confirmation */}
          <Dialog open={isDeleteOpen} onOpenChange={setIsDeleteOpen}>
            <DialogContent className="p-4 sm:max-w-md w-full">
              <DialogHeader>
                <DialogTitle>Confirm Deletion</DialogTitle>
              </DialogHeader>
              <p>Are you sure you want to delete this contract? This action cannot be undone.</p>
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

          {contracts.data.length > 0 ? (
            <Card>
              <CardHeader>
                <CardTitle>Olam Food Employee Contracts</CardTitle>
                <div className="text-sm text-muted-foreground">
                  {searchCount !== null
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
                        <Button variant="ghost" onClick={() => handleSort("id")} className="flex items-center gap-1">
                          Staff ID
                          <ArrowUpDown className="h-4 w-4" />
                        </Button>
                      </TableHead>
                      <TableHead>
                        <Button variant="ghost" onClick={() => handleSort("employeename")} className="flex items-center gap-1">
                          Employee Name
                          <ArrowUpDown className="h-4 w-4" />
                        </Button>
                      </TableHead>
                      <TableHead>Client</TableHead>
                      <TableHead>Location</TableHead>
                      <TableHead>Position</TableHead>
                      <TableHead>Duration</TableHead>
                      <TableHead>Start Date</TableHead>
                      <TableHead>End Date</TableHead>
                      <TableHead>Basic Salary</TableHead>
                      <TableHead>Insurance</TableHead>
                      <TableHead className="text-right">Actions</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {contracts.data.map((contract, index) => (
                      <TableRow key={contract.id}>
                        <TableCell>{(contracts.from ?? 1) + index}</TableCell>
                        <TableCell>
                          {contract.activate === "1" && (
                            <Button variant="outline" size="sm" onClick={() => handleDownload(contract.id)}>
                              <Download className="h-4 w-4" />
                            </Button>
                          )}
                        </TableCell>
                        <TableCell>{contract.id || '—'}</TableCell>
                        <TableCell>{contract.employeename}</TableCell>
                        <TableCell>{contract.client || '—'}</TableCell>
                        <TableCell>{contract.location || '—'}</TableCell>
                        <TableCell>{contract.position1 || '—'}</TableCell>
                        <TableCell>{contract.duration || '—'}</TableCell>
                        <TableCell>{contract.startdate?.substring(0, 10) || '—'}</TableCell>
                        <TableCell>{contract.enddate?.substring(0, 10) || '—'}</TableCell>
                        <TableCell>{contract.basic || '—'}</TableCell>
                        <TableCell>{contract.insurance || '—'}</TableCell>
                        <TableCell className="text-right">
                          <div className="flex gap-2 justify-end">
                            <Button variant="outline" size="sm" onClick={() => handleView(contract.id)}>
                              <Eye className="h-4 w-4 text-blue-500" />
                            </Button>
                            <Button variant="outline" size="sm" onClick={() => handleEdit(contract)}>
                              <Pencil className="h-4 w-4" />
                            </Button>
                            <Button variant="destructive" size="sm" onClick={() => handleDelete(contract.id)}>
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
                    {contracts.links.map((link, index) => (
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
                  No Olam Food contracts found. Create a new contract to get started!
                </p>
              </CardContent>
            </Card>
          )}
        </div>
      </AppLayout>
    </ErrorBoundary>
  );
}
