import { Head, Link, useForm } from "@inertiajs/react";
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

interface Petra {
  staffid: string;
  shop: string;
  lastname: string;
  firstname: string;
  othername: string | null;
  ssnit1a: string;
  national: string | null;
}

interface Props {
  petras: {
    data: Petra[];
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
  { title: "Petra Details", href: "/petradetails" },
];

export default function PetraIndex({ petras, filters, totalRecords, searchCount, flash }: Props) {
  const [isOpen, setIsOpen] = useState(false);
  const [editingPetra, setEditingPetra] = useState<Petra | null>(null);
  const [isDeleteOpen, setIsDeleteOpen] = useState(false);
  const [deletePetraId, setDeletePetraId] = useState<string | null>(null);
  const [isBulkDeleteOpen, setIsBulkDeleteOpen] = useState(false);
  const [selectedStaffIds, setSelectedStaffIds] = useState<string[]>([]);
  const [showToast, setShowToast] = useState(false);
  const [toastMessage, setToastMessage] = useState("");
  const [toastType, setToastType] = useState<"success" | "error">("success");
  const [search, setSearch] = useState(filters.search || "");
  const [sort, setSort] = useState(filters.sort || "staffid");
  const [order, setOrder] = useState(filters.order || "asc");
  const [isBulkUploadOpen, setIsBulkUploadOpen] = useState(false);

  const { data, setData, post, put, processing, reset, delete: destroy, get, errors } = useForm({
    staffid: "",
    shop: "",
    lastname: "",
    firstname: "",
    othername: "",
    ssnit1a: "",
    national: "",
  });

  const bulkDeleteForm = useForm({
    staffids: [] as string[],
  });

  const bulkUploadForm = useForm({
    file: null as File | null,
  });

  // Sync bulkDeleteForm.staffids with selectedStaffIds
  useEffect(() => {
    bulkDeleteForm.setData('staffids', selectedStaffIds);
  }, [selectedStaffIds]);

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

  // Handle bulk delete and upload form errors
  useEffect(() => {
    if (Object.keys(bulkDeleteForm.errors).length > 0) {
      const errorMessage = bulkDeleteForm.errors.staffids || "Failed to delete selected records. Please try again.";
      setToastMessage(errorMessage);
      setToastType("error");
      setShowToast(true);
    }
    if (Object.keys(bulkUploadForm.errors).length > 0) {
      const errorMessage = bulkUploadForm.errors.file || "Failed to upload file. Please try again.";
      setToastMessage(errorMessage);
      setToastType("error");
      setShowToast(true);
    }
  }, [bulkDeleteForm.errors, bulkUploadForm.errors]);

  // Handle toast timeout
  useEffect(() => {
    if (showToast) {
      const timeout = toastType === "error" ? 60000 : 3000;
      const timer = setTimeout(() => setShowToast(false), timeout);
      return () => clearTimeout(timer);
    }
  }, [showToast, toastType]);

  const handleEdit = (petra: Petra) => {
    console.log('Editing Petra:', petra); // Debug log
    setEditingPetra(petra);
    setData({
      staffid: petra.staffid || '',
      shop: petra.shop || '',
      lastname: petra.lastname || '',
      firstname: petra.firstname || '',
      othername: petra.othername || '',
      ssnit1a: petra.ssnit1a || '',
      national: petra.national || '',
    });
    setIsOpen(true);
  };

  const handleSubmit = (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    console.log('Form Data:', data); // Debug log
    if (editingPetra) {
      put(route("petradetails.update", editingPetra.staffid), {
        onSuccess: () => {
          setIsOpen(false);
          reset();
          setEditingPetra(null);
        },
      });
    } else {
      post(route("petradetails.store"), {
        onSuccess: () => {
          setIsOpen(false);
          reset();
        },
      });
    }
  };

  const handleDelete = (staffid: string) => {
    setDeletePetraId(staffid);
    setIsDeleteOpen(true);
  };

  const confirmDelete = () => {
    if (deletePetraId) {
      destroy(route("petradetails.destroy", deletePetraId), {
        onSuccess: () => {
          setIsDeleteOpen(false);
          setDeletePetraId(null);
        },
      });
    }
  };

  const handleSelect = (staffid: string, checked: boolean) => {
    setSelectedStaffIds((prev) =>
      checked ? [...prev, staffid] : prev.filter((id) => id !== staffid)
    );
  };

  const handleSelectAll = (checked: boolean) => {
    setSelectedStaffIds(checked ? petras.data.map((petra) => petra.staffid) : []);
  };

  const handleBulkDelete = () => {
    if (selectedStaffIds.length > 0) {
      setIsBulkDeleteOpen(true);
    }
  };

  const confirmBulkDelete = () => {
    bulkDeleteForm.post(route("petradetails.bulk-destroy"), {
      onSuccess: () => {
        setIsBulkDeleteOpen(false);
        setSelectedStaffIds([]);
      },
      preserveState: true,
      preserveScroll: true,
    });
  };

  const handleSearchSort = () => {
    get(route("petradetails.index", { search, sort, order }), {
      preserveState: true,
      preserveScroll: true,
    });
  };

  const handleExport = () => {
    const url = route("petradetails.export", { search, sort, order });
    const link = document.createElement("a");
    link.href = url;
    link.download = "petra_records.xlsx";
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  };

  const handleDownloadTemplate = () => {
    const url = route("petradetails.download-template");
    const link = document.createElement("a");
    link.href = url;
    link.download = "petra_template.xlsx";
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  };

  const handleBulkUpload = (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    if (bulkUploadForm.data.file) {
      bulkUploadForm.post(route("petradetails.bulk-upload"), {
        onSuccess: () => {
          setIsBulkUploadOpen(false);
          bulkUploadForm.reset();
        },
        forceFormData: true,
      });
    }
  };

  return (
    <ErrorBoundary>
      <AppLayout breadcrumbs={breadcrumbs}>
        <Head title="Petra Details" />
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
              <h1 className="text-2xl font-bold">Petra Details</h1>
              <div className="flex flex-col sm:flex-row gap-4">
                <ShadcnInput
                  placeholder="Search by staff ID, name, or shop..."
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
                    <SelectItem value="staffid:asc">Staff ID (A-Z)</SelectItem>
                    <SelectItem value="staffid:desc">Staff ID (Z-A)</SelectItem>
                    <SelectItem value="lastname:asc">Last Name (A-Z)</SelectItem>
                    <SelectItem value="lastname:desc">Last Name (Z-A)</SelectItem>
                    <SelectItem value="firstname:asc">First Name (A-Z)</SelectItem>
                    <SelectItem value="firstname:desc">First Name (Z-A)</SelectItem>
                    <SelectItem value="shop:asc">Shop (A-Z)</SelectItem>
                    <SelectItem value="shop:desc">Shop (Z-A)</SelectItem>
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
                      setEditingPetra(null);
                    }
                  }}
                >
                  <DialogTrigger asChild>
                    <Button className="w-full sm:w-auto">
                      <Plus className="h-4 w-4 mr-2" />
                      New Petra
                    </Button>
                  </DialogTrigger>
                  <DialogContent className="p-4 sm:max-w-3xl w-full max-h-[80vh] overflow-y-auto">
                    <DialogHeader>
                      <DialogTitle>{editingPetra ? "Edit Petra Record" : "Create New Petra Record"}</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={handleSubmit}>
                      <div className="grid sm:grid-cols-2 gap-4 py-4">
                        <div className="grid gap-2">
                          <Label htmlFor="staffid">Staff ID</Label>
                          <Input
                            id="staffid"
                            value={data.staffid}
                            onChange={(e) => setData("staffid", e.target.value)}
                            required
                            className="w-full"
                          />
                          {errors.staffid && <p className="text-red-500 text-sm">{errors.staffid}</p>}
                        </div>
                        <div className="grid gap-2">
                          <Label htmlFor="shop">Shop</Label>
                          <Input
                            id="shop"
                            value={data.shop}
                            onChange={(e) => setData("shop", e.target.value)}
                            required
                            className="w-full"
                          />
                          {errors.shop && <p className="text-red-500 text-sm">{errors.shop}</p>}
                        </div>
                        <div className="grid gap-2">
                          <Label htmlFor="lastname">Last Name</Label>
                          <Input
                            id="lastname"
                            value={data.lastname}
                            onChange={(e) => setData("lastname", e.target.value)}
                            required
                            className="w-full"
                          />
                          {errors.lastname && <p className="text-red-500 text-sm">{errors.lastname}</p>}
                        </div>
                        <div className="grid gap-2">
                          <Label htmlFor="firstname">First Name</Label>
                          <Input
                            id="firstname"
                            value={data.firstname}
                            onChange={(e) => setData("firstname", e.target.value)}
                            required
                            className="w-full"
                          />
                          {errors.firstname && <p className="text-red-500 text-sm">{errors.firstname}</p>}
                        </div>
                        <div className="grid gap-2">
                          <Label htmlFor="othername">Other Name</Label>
                          <Input
                            id="othername"
                            value={data.othername}
                            onChange={(e) => setData("othername", e.target.value)}
                            className="w-full"
                          />
                          {errors.othername && <p className="text-red-500 text-sm">{errors.othername}</p>}
                        </div>
                        <div className="grid gap-2">
                          <Label htmlFor="ssnit1a">Petra ID</Label>
                          <Input
                            id="ssnit1a"
                            value={data.ssnit1a}
                            onChange={(e) => setData("ssnit1a", e.target.value)}
                            required
                            className="w-full"
                          />
                          {errors.ssnit1a && <p className="text-red-500 text-sm">{errors.ssnit1a}</p>}
                        </div>
                        <div className="grid gap-2">
                          <Label htmlFor="national">Ghana Card ID</Label>
                          <Input
                            id="national"
                            value={data.national}
                            onChange={(e) => setData("national", e.target.value)}
                            className="w-full"
                          />
                          {errors.national && <p className="text-red-500 text-sm">{errors.national}</p>}
                        </div>
                      </div>
                      <DialogFooter className="flex flex-col sm:flex-row sm:justify-end gap-2">
                        <Button type="submit" disabled={processing}>
                          {editingPetra ? "Update" : "Create"}
                        </Button>
                      </DialogFooter>
                    </form>
                  </DialogContent>
                </Dialog>
              </div>
            </div>
            <div className="flex flex-col sm:flex-row gap-4 justify-end">
              <Button
                onClick={handleDownloadTemplate}
                className="w-full sm:w-auto bg-blue-500 hover:bg-blue-600"
              >
                <Download className="h-4 w-4 mr-2" />
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
                    <DialogTitle>Bulk Upload Petra Records</DialogTitle>
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
              <Button
                variant="destructive"
                onClick={handleBulkDelete}
                disabled={selectedStaffIds.length === 0}
                className="w-full sm:w-auto"
              >
                <Trash className="h-4 w-4 mr-2" />
                Delete Selected
              </Button>
            </div>
          </div>

          {petras.data.length > 0 ? (
            <Card>
              <CardHeader>
                <CardTitle>Your Petra Records</CardTitle>
                <div className="text-sm text-muted-foreground">
                  {searchCount !== null
                    ? `Showing ${searchCount} of ${totalRecords} records matching your search`
                    : `Total Records: ${totalRecords}`}
                </div>
              </CardHeader>
              <CardContent>
                <div className="block md:hidden space-y-4">
                  {petras.data.map((petra) => (
                    <div key={petra.staffid} className="border rounded-lg p-4 bg-background">
                      <div className="flex flex-col gap-2">
                        <div className="flex items-center gap-2">
                          <Checkbox
                            checked={selectedStaffIds.includes(petra.staffid)}
                            onCheckedChange={(checked) => handleSelect(petra.staffid, checked as boolean)}
                          />
                          <div className="font-semibold">{petra.staffid}</div>
                        </div>
                        <div className="text-sm text-muted-foreground">Shop: {petra.shop}</div>
                        <div className="text-sm text-muted-foreground">Name: {petra.lastname}, {petra.firstname} {petra.othername || ""}</div>
                        <div className="text-sm text-muted-foreground">Petra ID: {petra.ssnit1a}</div>
                        <div className="text-sm text-muted-foreground">Ghana Card ID: {petra.national || "N/A"}</div>
                        <div className="flex gap-2">
                          <Button variant="outline" size="sm" onClick={() => handleEdit(petra)}>
                            <Pencil className="h-4 w-4" />
                          </Button>
                          <Button variant="destructive" size="sm" onClick={() => handleDelete(petra.staffid)}>
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
                            checked={selectedStaffIds.length === petras.data.length && petras.data.length > 0}
                            onCheckedChange={handleSelectAll}
                          />
                        </TableHead>
                        <TableHead>Staff ID</TableHead>
                        <TableHead>Shop</TableHead>
                        <TableHead>Last Name</TableHead>
                        <TableHead>First Name</TableHead>
                        <TableHead>Other Name</TableHead>
                        <TableHead>Petra ID</TableHead>
                        <TableHead>Ghana Card ID</TableHead>
                        <TableHead className="text-right">Actions</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {petras.data.map((petra) => (
                        <TableRow key={petra.staffid}>
                          <TableCell>
                            <Checkbox
                              checked={selectedStaffIds.includes(petra.staffid)}
                              onCheckedChange={(checked) => handleSelect(petra.staffid, checked as boolean)}
                            />
                          </TableCell>
                          <TableCell>{petra.staffid}</TableCell>
                          <TableCell>{petra.shop}</TableCell>
                          <TableCell>{petra.lastname}</TableCell>
                          <TableCell>{petra.firstname}</TableCell>
                          <TableCell>{petra.othername || "N/A"}</TableCell>
                          <TableCell>{petra.ssnit1a}</TableCell>
                          <TableCell>{petra.national || "N/A"}</TableCell>
                          <TableCell className="text-right">
                            <div className="flex gap-2 justify-end">
                              <Button variant="outline" size="sm" onClick={() => handleEdit(petra)}>
                                <Pencil className="h-4 w-4" />
                              </Button>
                              <Button variant="destructive" size="sm" onClick={() => handleDelete(petra.staffid)}>
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
                    {petras.links.map((link, index) => (
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
                <p className="text-center text-gray-500">No Petra records found. Create a new record to get started!</p>
              </CardContent>
            </Card>
          )}

          <Dialog open={isDeleteOpen} onOpenChange={setIsDeleteOpen}>
            <DialogContent className="p-4 sm:max-w-md w-full">
              <DialogHeader>
                <DialogTitle>Confirm Deletion</DialogTitle>
              </DialogHeader>
              <p>Are you sure you want to delete this Petra record? This action cannot be undone.</p>
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
              <p>Are you sure you want to delete {selectedStaffIds.length} Petra record(s)? This action cannot be undone.</p>
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
