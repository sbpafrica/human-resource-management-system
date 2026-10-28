import { Head, useForm } from "@inertiajs/react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Plus, Pencil, Trash, CheckCircle, XCircle, Download, Upload, ArrowUpDown, Eye, FileSpreadsheet } from "lucide-react";
import AppLayout from "@/layouts/app-layout";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger, DialogFooter } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { useState, useEffect, useCallback } from "react";
import ErrorBoundary from "@/components/ErrorBoundary";
import debounce from "lodash/debounce";

interface Contract {
  id: string;
  employeename: string;
  client: string;
  location: string;
  position1: string;
  startdate: string;
  enddate: string;
  basic: string | null;
  employername: string;
  issuedate: string;
  hremail: string;
  email: string | null;
  ghanacard: string | null;
  digiaddress: string | null;
  signature: string | null;
  activate: string;
}

interface Props {
  contracts: {
    data: Contract[];
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
  { title: "Contracts", href: "/contracts" },
];

export default function Contracts({ contracts, filters, totalRecords, searchCount, flash }: Props) {
  const [isOpen, setIsOpen] = useState(false);
  const [editingContract, setEditingContract] = useState<Contract | null>(null);
  const [isDeleteOpen, setIsDeleteOpen] = useState(false);
  const [deleteContractId, setDeleteContractId] = useState<string | null>(null);
  const [isViewOpen, setIsViewOpen] = useState(false);
  const [viewContract, setViewContract] = useState<Contract | null>(null);
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
    startdate: "",
    enddate: "",
    basic: "",
    employername: "",
    issuedate: "",
    hremail: "",
    email: "",
    ghanacard: "",
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
      get(route("contracts.index", { search: searchValue, sort: sortValue, order: orderValue }), {
        preserveState: true,
        preserveScroll: true,
        onError: (err) => {
          setToastMessage("Search failed: " + Object.values(err).join(" | "));
          setToastType("error");
          setShowToast(true);
        },
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
    if (Object.keys(errors).length > 0) {
      setToastMessage(Object.values(errors).join(' | '));
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

  useEffect(() => {
    debouncedSearch(search, sort, order);
  }, [search, sort, order, debouncedSearch]);

  const handleEdit = (contract: Contract) => {
    setEditingContract(contract);
    setData({
      id: contract.id,
      employeename: contract.employeename,
      client: contract.client,
      location: contract.location,
      position1: contract.position1,
      startdate: contract.startdate,
      enddate: contract.enddate,
      basic: contract.basic || "",
      employername: contract.employername,
      issuedate: contract.issuedate,
      hremail: contract.hremail,
      email: contract.email || "",
      ghanacard: contract.ghanacard || "",
    });
    setIsOpen(true);
  };

  const handleView = async (id: string) => {
    try {
      const response = await fetch(route("contracts.show", id));
      const contract = await response.json();
      setViewContract(contract);
      setIsViewOpen(true);
    } catch (error) {
      setToastMessage("Failed to load contract details.");
      setToastType("error");
      setShowToast(true);
    }
  };

  const handleSubmit = (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    if (editingContract) {
      put(route("contracts.update", editingContract.id), {
        onSuccess: () => {
          setIsOpen(false);
          reset();
          setEditingContract(null);
        },
        onError: (err) => {
          setToastMessage("Failed to update contract: " + Object.values(err).join(" | "));
          setToastType("error");
          setShowToast(true);
        },
      });
    } else {
      post(route("contracts.store"), {
        onSuccess: () => {
          setIsOpen(false);
          reset();
        },
        onError: (err) => {
          setToastMessage("Failed to create contract: " + Object.values(err).join(" | "));
          setToastType("error");
          setShowToast(true);
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
      destroy(route("contracts.destroy", deleteContractId), {
        onSuccess: () => {
          setIsDeleteOpen(false);
          setDeleteContractId(null);
        },
        onError: (err) => {
          setToastMessage("Failed to delete contract: " + Object.values(err).join(" | "));
          setToastType("error");
          setShowToast(true);
        },
      });
    }
  };

  const handleDownload = (id: string) => {
    const url = route("contracts.download", id);
    const link = document.createElement("a");
    link.href = url;
    link.download = `contract_${id}.pdf`;
    link.click();
  };

  const handleDownloadTemplate = () => {
    const url = route("contracts.download-template");
    const link = document.createElement("a");
    link.href = url;
    link.download = "contract_template.xlsx";
    link.click();
  };

  const handleBulkUpload = (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    if (bulkUploadForm.data.file) {
      bulkUploadForm.post(route("contracts.bulk-upload"), {
        forceFormData: true,
        onSuccess: () => {
          setIsBulkUploadOpen(false);
          bulkUploadForm.reset();
        },
        onError: (err) => {
          setToastMessage("Failed to upload file: " + Object.values(err).join(" | "));
          setToastType("error");
          setShowToast(true);
        },
      });
    }
  };

  return (
    <ErrorBoundary>
      <AppLayout breadcrumbs={breadcrumbs}>
        <Head title="Contracts" />
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
            <h1 className="text-2xl font-bold">Employee Contracts</h1>
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
                <DialogContent className="p-4 sm:max-w-4xl w-full max-h-[80vh] overflow-y-auto">
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
                          className="w-full"
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
                          className="w-full"
                        />
                        {errors.employeename && <p className="text-red-500 text-sm">{errors.employeename}</p>}
                      </div>
                      <div className="grid gap-2">
                        <Label htmlFor="client">Client</Label>
                        <Input
                          id="client"
                          value={data.client}
                          onChange={(e) => setData("client", e.target.value)}
                          required
                          className="w-full"
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
                          className="w-full"
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
                          className="w-full"
                        />
                        {errors.position1 && <p className="text-red-500 text-sm">{errors.position1}</p>}
                      </div>
                      <div className="grid gap-2">
                        <Label htmlFor="startdate">Start Date</Label>
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
                        <Label htmlFor="enddate">End Date</Label>
                        <Input
                          id="enddate"
                          type="date"
                          value={data.enddate}
                          onChange={(e) => setData("enddate", e.target.value)}
                          required
                          className="w-full"
                        />
                        {errors.enddate && <p className="text-red-500 text-sm">{errors.enddate}</p>}
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
                        <Label htmlFor="employername">Employer Name</Label>
                        <Input
                          id="employername"
                          value={data.employername}
                          onChange={(e) => setData("employername", e.target.value)}
                          required
                          className="w-full"
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
                          className="w-full"
                        />
                        {errors.issuedate && <p className="text-red-500 text-sm">{errors.issuedate}</p>}
                      </div>
                      <div className="grid gap-2">
                        <Label htmlFor="hremail">HR Email (Optional)</Label>
                        <Input
                          id="hremail"
                          type="email"
                          value={data.hremail}
                          onChange={(e) => setData("hremail", e.target.value)}
                          placeholder="Defaults to your email or hr@sbpafrica.com"
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
                      <div className="grid gap-2">
                        <Label htmlFor="ghanacard">Ghana Card</Label>
                        <Input
                          id="ghanacard"
                          value={data.ghanacard}
                          onChange={(e) => setData("ghanacard", e.target.value)}
                          className="w-full"
                        />
                        {errors.ghanacard && <p className="text-red-500 text-sm">{errors.ghanacard}</p>}
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
                    <DialogTitle>Bulk Upload Contracts</DialogTitle>
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
                            if (file) {
                              bulkUploadForm.setData('file', file);
                            }
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

          <Dialog open={isViewOpen} onOpenChange={setIsViewOpen}>
    <DialogContent className="p-4 sm:max-w-4xl w-full max-h-[80vh] overflow-y-auto">
        <DialogHeader>
            <DialogTitle>Contract Preview</DialogTitle>
        </DialogHeader>
        {viewContract && (
            <div className="mt-4">
                <iframe
                    src={`${route("contracts.download", viewContract.id)}?no_signature=1`}
                    className="w-full h-[600px] border rounded"
                    title="Contract Preview"
                />
            </div>
        )}
    </DialogContent>
</Dialog>

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
                <CardTitle>Employee Contracts</CardTitle>
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
                      <TableHead>
                        <Button variant="ghost" onClick={() => handleSort("client")} className="flex items-center gap-1">
                          Client
                          <ArrowUpDown className="h-4 w-4" />
                        </Button>
                      </TableHead>
                      <TableHead>
                        <Button variant="ghost" onClick={() => handleSort("location")} className="flex items-center gap-1">
                          Location
                          <ArrowUpDown className="h-4 w-4" />
                        </Button>
                      </TableHead>
                      <TableHead>Position</TableHead>
                      <TableHead>Start Date</TableHead>
                      <TableHead>End Date</TableHead>
                      <TableHead>Basic</TableHead>
                      <TableHead>Issue Date</TableHead>
                      <TableHead>Email</TableHead>
                      <TableHead className="text-right">Actions</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {contracts.data.map((contract, index) => (
                      <TableRow key={contract.id}>
                        <TableCell>{index + 1}</TableCell>
                        <TableCell>
                          {contract.activate === '1' && (
                            <Button variant="outline" size="sm" onClick={() => handleDownload(contract.id)}>
                              <Download className="h-4 w-4" />
                            </Button>
                          )}
                        </TableCell>
                        <TableCell>{contract.id}</TableCell>
                        <TableCell>{contract.employeename}</TableCell>
                        <TableCell>{contract.client}</TableCell>
                        <TableCell>{contract.location}</TableCell>
                        <TableCell>{contract.position1}</TableCell>
                        <TableCell>{contract.startdate}</TableCell>
                        <TableCell>{contract.enddate}</TableCell>
                        <TableCell>{contract.basic || 'N/A'}</TableCell>
                        <TableCell>{contract.issuedate}</TableCell>
                        <TableCell>{contract.email || 'N/A'}</TableCell>
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
                <p className="text-center text-gray-500">No contracts found. Create a new contract to get started!</p>
              </CardContent>
            </Card>
          )}
        </div>
      </AppLayout>
    </ErrorBoundary>
  );
}
