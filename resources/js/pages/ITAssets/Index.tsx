import { Head, Link, useForm, router } from "@inertiajs/react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
  Plus,
  Pencil,
  Trash2,
  Archive,
  Download,
  Search,
  Laptop,
  CheckCircle2,
  AlertCircle,
  Clock,
  Building2,
  Eye,
} from "lucide-react";
import AppLayout from "@/layouts/app-layout";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter,
  DialogDescription,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { useState, useEffect } from "react";
import ErrorBoundary from "@/components/ErrorBoundary";

interface AssetSpec {
  [key: string]: string;
}

interface Asset {
  asset_id: number;
  asset_tag: string;
  category_id: number;
  category_name: string;
  name: string;
  brand: string | null;
  model: string | null;
  serial_number: string | null;
  purchase_date: string | null;
  warranty_expiry: string | null;
  location: string | null;
  allocation_status: string;
  staff_id: string | null;
  staff_name: string | null;
  condition: string;
  notes: string | null;
  specs: AssetSpec;
  created_at: string | null;
}

interface Category {
  category_id: number;
  name: string;
  requires_serial: boolean;
}

interface Props {
  assets: {
    data: Asset[];
    links: any[];
    current_page: number;
    last_page: number;
  };
  categories: Category[];
  totalRecords: number;
  searchCount: number | null;
  filters: {
    search?: string;
    category_id?: string;
    allocation_status?: string;
    condition?: string;
    sort?: string;
    order?: string;
  };
  flash?: {
    success?: string;
    error?: string;
  };
}

const breadcrumbs = [{ title: "IT Assets", href: "/it-assets" }];

export default function ITAssetsIndex({
  assets,
  categories,
  totalRecords,
  searchCount,
  filters,
  flash,
}: Props) {
  const [isOpen, setIsOpen] = useState(false);
  const [editingAsset, setEditingAsset] = useState<Asset | null>(null);
  const [isDeleteOpen, setIsDeleteOpen] = useState(false);
  const [deleteAssetId, setDeleteAssetId] = useState<number | null>(null);
  const [isArchiveOpen, setIsArchiveOpen] = useState(false);
  const [archiveAssetId, setArchiveAssetId] = useState<number | null>(null);
  const [viewingAsset, setViewingAsset] = useState<Asset | null>(null);

  const [showToast, setShowToast] = useState(false);
  const [toastMessage, setToastMessage] = useState("");
  const [toastType, setToastType] = useState<"success" | "error">("success");

  const [search, setSearch] = useState(filters.search || "");
  const [categoryId, setCategoryId] = useState(filters.category_id || "all");
  const [allocationStatus, setAllocationStatus] = useState(filters.allocation_status || "all");
  const [condition, setCondition] = useState(filters.condition || "all");

  const { data, setData, post, put, delete: destroy, processing, errors, reset } = useForm({
    category_id: "1",
    name: "",
    brand: "",
    model: "",
    serial_number: "",
    purchase_date: "",
    warranty_expiry: "",
    location: "",
    allocation_status: "Unallocated",
    staff_id: "",
    staff_name: "",
    condition: "Good",
    last_maintenance_date: "",
    notes: "",
    ram: "",
    storage: "",
    processor: "",
    router_ip: "",
    mac_address: "",
  });

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
      const timer = setTimeout(() => setShowToast(false), 4000);
      return () => clearTimeout(timer);
    }
  }, [showToast]);

  const handleSearch = () => {
    router.get(
      route("itassets.index"),
      {
        search,
        category_id: categoryId === "all" ? "" : categoryId,
        allocation_status: allocationStatus === "all" ? "" : allocationStatus,
        condition: condition === "all" ? "" : condition,
      },
      { preserveState: true, replace: true }
    );
  };

  const openCreateModal = () => {
    setEditingAsset(null);
    reset();
    setIsOpen(true);
  };

  const openEditModal = (asset: Asset) => {
    setEditingAsset(asset);
    setData({
      category_id: String(asset.category_id),
      name: asset.name || "",
      brand: asset.brand || "",
      model: asset.model || "",
      serial_number: asset.serial_number || "",
      purchase_date: asset.purchase_date || "",
      warranty_expiry: asset.warranty_expiry || "",
      location: asset.location || "",
      allocation_status: asset.allocation_status || "Unallocated",
      staff_id: asset.staff_id || "",
      staff_name: asset.staff_name || "",
      condition: asset.condition || "Good",
      last_maintenance_date: "",
      notes: asset.notes || "",
      ram: asset.specs.ram || "",
      storage: asset.specs.storage || "",
      processor: asset.specs.processor || "",
      router_ip: asset.specs.router_ip || "",
      mac_address: asset.specs.mac_address || "",
    });
    setIsOpen(true);
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (editingAsset) {
      put(route("itassets.update", editingAsset.asset_id), {
        onSuccess: () => {
          setIsOpen(false);
          reset();
          setEditingAsset(null);
        },
      });
    } else {
      post(route("itassets.store"), {
        onSuccess: () => {
          setIsOpen(false);
          reset();
        },
      });
    }
  };

  const confirmArchive = (id: number) => {
    setArchiveAssetId(id);
    setIsArchiveOpen(true);
  };

  const handleArchive = () => {
    if (!archiveAssetId) return;
    router.post(
      route("itassets.archive", archiveAssetId),
      {},
      {
        onSuccess: () => {
          setIsArchiveOpen(false);
          setArchiveAssetId(null);
        },
      }
    );
  };

  const confirmDelete = (id: number) => {
    setDeleteAssetId(id);
    setIsDeleteOpen(true);
  };

  const handleDelete = () => {
    if (!deleteAssetId) return;
    destroy(route("itassets.destroy", deleteAssetId), {
      onSuccess: () => {
        setIsDeleteOpen(false);
        setDeleteAssetId(null);
      },
    });
  };

  const handleExport = () => {
    const url = route("itassets.export", {
      search,
      category_id: categoryId === "all" ? "" : categoryId,
      allocation_status: allocationStatus === "all" ? "" : allocationStatus,
      condition: condition === "all" ? "" : condition,
    });
    window.location.href = url;
  };

  const getConditionBadge = (cond: string) => {
    switch (cond) {
      case "New":
        return "bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300";
      case "Excellent":
      case "Good":
        return "bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-300";
      case "Fair":
        return "bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300";
      case "Poor":
      case "Damaged":
        return "bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300";
      default:
        return "bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300";
    }
  };

  const getAllocationBadge = (status: string) => {
    switch (status) {
      case "Allocated":
        return "bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-300";
      case "Under Maintenance":
        return "bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-300";
      case "Retired":
        return "bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300";
      default:
        return "bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300";
    }
  };

  return (
    <ErrorBoundary>
      <AppLayout breadcrumbs={breadcrumbs}>
        <Head title="IT Assets Management" />
        <div className="flex h-full flex-1 flex-col gap-6 p-6 bg-gray-50 dark:bg-gray-900 rounded-xl">
          {/* Toast Notification */}
          {showToast && (
            <div
              className={`fixed top-4 right-4 z-50 flex items-center gap-2 rounded-lg p-4 shadow-lg w-full sm:w-auto max-w-sm ${
                toastType === "success" ? "bg-green-600 text-white" : "bg-red-600 text-white"
              } animate-in fade-in slide-in-from-top-5`}
            >
              {toastType === "success" ? (
                <CheckCircle2 className="h-5 w-5" />
              ) : (
                <AlertCircle className="h-5 w-5" />
              )}
              <span>{toastMessage}</span>
            </div>
          )}

          {/* Top Actions & Header */}
          <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
              <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                <Laptop className="h-7 w-7 text-blue-600 dark:text-blue-400" />
                IT Assets Inventory
              </h1>
              <p className="text-sm text-gray-500 dark:text-gray-400">
                Manage computers, peripherals, network hardware, and staff allocations
              </p>
            </div>
            <div className="flex flex-wrap items-center gap-3 w-full sm:w-auto">
              <Link href={route("itassets.archived")}>
                <Button variant="outline" className="w-full sm:w-auto border-gray-300 dark:border-gray-700">
                  <Archive className="h-4 w-4 mr-2 text-gray-600 dark:text-gray-300" />
                  Archived Assets
                </Button>
              </Link>
              <Button
                variant="outline"
                className="w-full sm:w-auto border-gray-300 dark:border-gray-700"
                onClick={handleExport}
              >
                <Download className="h-4 w-4 mr-2 text-green-600" />
                Export to Excel
              </Button>
              <Button onClick={openCreateModal} className="w-full sm:w-auto bg-blue-600 hover:bg-blue-700 text-white">
                <Plus className="h-4 w-4 mr-2" />
                Add New Asset
              </Button>
            </div>
          </div>

          {/* Filters Card */}
          <Card className="shadow-none border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-800">
            <CardHeader className="pb-3">
              <CardTitle className="text-base font-semibold text-gray-800 dark:text-gray-100">
                Filters & Search
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div className="relative">
                  <Search className="absolute left-3 top-2.5 h-4 w-4 text-gray-400" />
                  <Input
                    placeholder="Tag, name, serial, staff..."
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    onKeyDown={(e) => e.key === "Enter" && handleSearch()}
                    className="pl-9"
                  />
                </div>
                <Select
                  value={categoryId}
                  onValueChange={(val) => {
                    setCategoryId(val);
                    router.get(
                      route("itassets.index"),
                      {
                        search,
                        category_id: val === "all" ? "" : val,
                        allocation_status: allocationStatus === "all" ? "" : allocationStatus,
                        condition: condition === "all" ? "" : condition,
                      },
                      { preserveState: true, replace: true }
                    );
                  }}
                >
                  <SelectTrigger>
                    <SelectValue placeholder="All Categories" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="all">All Categories</SelectItem>
                    {categories.map((c) => (
                      <SelectItem key={c.category_id} value={String(c.category_id)}>
                        {c.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                <Select
                  value={allocationStatus}
                  onValueChange={(val) => {
                    setAllocationStatus(val);
                    router.get(
                      route("itassets.index"),
                      {
                        search,
                        category_id: categoryId === "all" ? "" : categoryId,
                        allocation_status: val === "all" ? "" : val,
                        condition: condition === "all" ? "" : condition,
                      },
                      { preserveState: true, replace: true }
                    );
                  }}
                >
                  <SelectTrigger>
                    <SelectValue placeholder="All Statuses" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="all">All Allocation Statuses</SelectItem>
                    <SelectItem value="Unallocated">Unallocated</SelectItem>
                    <SelectItem value="Allocated">Allocated</SelectItem>
                    <SelectItem value="Under Maintenance">Under Maintenance</SelectItem>
                    <SelectItem value="Retired">Retired</SelectItem>
                  </SelectContent>
                </Select>
                <Select
                  value={condition}
                  onValueChange={(val) => {
                    setCondition(val);
                    router.get(
                      route("itassets.index"),
                      {
                        search,
                        category_id: categoryId === "all" ? "" : categoryId,
                        allocation_status: allocationStatus === "all" ? "" : allocationStatus,
                        condition: val === "all" ? "" : val,
                      },
                      { preserveState: true, replace: true }
                    );
                  }}
                >
                  <SelectTrigger>
                    <SelectValue placeholder="All Conditions" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="all">All Conditions</SelectItem>
                    <SelectItem value="New">New</SelectItem>
                    <SelectItem value="Excellent">Excellent</SelectItem>
                    <SelectItem value="Good">Good</SelectItem>
                    <SelectItem value="Fair">Fair</SelectItem>
                    <SelectItem value="Poor">Poor</SelectItem>
                    <SelectItem value="Damaged">Damaged</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </CardContent>
          </Card>

          {/* Asset List Table */}
          <Card className="shadow-none border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-800">
            <CardHeader className="flex flex-row items-center justify-between pb-3">
              <CardTitle className="text-base font-semibold text-gray-800 dark:text-gray-100">
                Active Inventory ({searchCount !== null ? searchCount : totalRecords} records)
              </CardTitle>
            </CardHeader>
            <CardContent>
              {assets.data.length > 0 ? (
                <div className="overflow-x-auto">
                  <Table>
                    <TableHeader>
                      <TableRow className="bg-gray-50 dark:bg-gray-700/50">
                        <TableHead className="font-semibold">Asset Tag</TableHead>
                        <TableHead className="font-semibold">Category & Name</TableHead>
                        <TableHead className="font-semibold">Brand / Model</TableHead>
                        <TableHead className="font-semibold">Serial Number</TableHead>
                        <TableHead className="font-semibold">Location</TableHead>
                        <TableHead className="font-semibold">Condition</TableHead>
                        <TableHead className="font-semibold">Allocation</TableHead>
                        <TableHead className="font-semibold text-right">Actions</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {assets.data.map((asset) => (
                        <TableRow key={asset.asset_id} className="hover:bg-gray-50/80 dark:hover:bg-gray-700/50">
                          <TableCell className="font-mono text-xs font-semibold text-blue-600 dark:text-blue-400">
                            {asset.asset_tag}
                          </TableCell>
                          <TableCell>
                            <div className="font-medium text-gray-900 dark:text-gray-100">{asset.name}</div>
                            <div className="text-xs text-gray-500 dark:text-gray-400">{asset.category_name}</div>
                          </TableCell>
                          <TableCell className="text-sm">
                            {asset.brand || "-"} {asset.model ? `(${asset.model})` : ""}
                          </TableCell>
                          <TableCell className="font-mono text-xs text-gray-600 dark:text-gray-300">
                            {asset.serial_number || "-"}
                          </TableCell>
                          <TableCell className="text-sm">{asset.location || "-"}</TableCell>
                          <TableCell>
                            <span
                              className={`px-2 py-0.5 text-xs font-medium rounded-full ${getConditionBadge(
                                asset.condition
                              )}`}
                            >
                              {asset.condition}
                            </span>
                          </TableCell>
                          <TableCell>
                            <span
                              className={`px-2 py-0.5 text-xs font-medium rounded-full ${getAllocationBadge(
                                asset.allocation_status
                              )}`}
                            >
                              {asset.allocation_status}
                            </span>
                            {asset.allocation_status === "Allocated" && asset.staff_name && (
                              <div className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                {asset.staff_name} ({asset.staff_id})
                              </div>
                            )}
                          </TableCell>
                          <TableCell className="text-right">
                            <div className="flex items-center justify-end gap-1">
                              <Button
                                variant="ghost"
                                size="sm"
                                title="View Details"
                                onClick={() => setViewingAsset(asset)}
                              >
                                <Eye className="h-4 w-4 text-gray-600 dark:text-gray-300" />
                              </Button>
                              <Button
                                variant="ghost"
                                size="sm"
                                title="Edit Asset"
                                onClick={() => openEditModal(asset)}
                              >
                                <Pencil className="h-4 w-4 text-blue-600 dark:text-blue-400" />
                              </Button>
                              <Button
                                variant="ghost"
                                size="sm"
                                title="Archive Asset"
                                onClick={() => confirmArchive(asset.asset_id)}
                              >
                                <Archive className="h-4 w-4 text-amber-600 dark:text-amber-400" />
                              </Button>
                              <Button
                                variant="ghost"
                                size="sm"
                                title="Delete Asset"
                                onClick={() => confirmDelete(asset.asset_id)}
                              >
                                <Trash2 className="h-4 w-4 text-red-600 dark:text-red-400" />
                              </Button>
                            </div>
                          </TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                </div>
              ) : (
                <div className="text-center py-10 text-gray-500 dark:text-gray-400">
                  No IT assets found matching the selected criteria.
                </div>
              )}

              {/* Pagination */}
              {assets.links && assets.links.length > 3 && (
                <div className="flex justify-end gap-2 mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                  {assets.links.map((link, i) => (
                    <Button
                      key={i}
                      variant={link.active ? "default" : "outline"}
                      size="sm"
                      disabled={!link.url}
                      onClick={() => link.url && router.get(link.url, {}, { preserveState: true })}
                      dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                  ))}
                </div>
              )}
            </CardContent>
          </Card>

          {/* View Details Modal */}
          <Dialog open={!!viewingAsset} onOpenChange={() => setViewingAsset(null)}>
            <DialogContent className="sm:max-w-md">
              <DialogHeader>
                <DialogTitle className="flex items-center gap-2">
                  <Laptop className="h-5 w-5 text-blue-600" />
                  Asset Details: {viewingAsset?.asset_tag}
                </DialogTitle>
                <DialogDescription>Full specification and allocation information</DialogDescription>
              </DialogHeader>
              {viewingAsset && (
                <div className="space-y-3 text-sm text-gray-700 dark:text-gray-200 py-2">
                  <div className="grid grid-cols-2 gap-2 border-b pb-2">
                    <div>
                      <span className="font-semibold text-gray-500">Name:</span> {viewingAsset.name}
                    </div>
                    <div>
                      <span className="font-semibold text-gray-500">Category:</span> {viewingAsset.category_name}
                    </div>
                    <div>
                      <span className="font-semibold text-gray-500">Brand:</span> {viewingAsset.brand || "N/A"}
                    </div>
                    <div>
                      <span className="font-semibold text-gray-500">Model:</span> {viewingAsset.model || "N/A"}
                    </div>
                    <div>
                      <span className="font-semibold text-gray-500">Serial No:</span> {viewingAsset.serial_number || "N/A"}
                    </div>
                    <div>
                      <span className="font-semibold text-gray-500">Location:</span> {viewingAsset.location || "N/A"}
                    </div>
                  </div>
                  <div className="grid grid-cols-2 gap-2 border-b pb-2">
                    <div>
                      <span className="font-semibold text-gray-500">Condition:</span> {viewingAsset.condition}
                    </div>
                    <div>
                      <span className="font-semibold text-gray-500">Allocation:</span> {viewingAsset.allocation_status}
                    </div>
                    {viewingAsset.allocation_status === "Allocated" && (
                      <>
                        <div>
                          <span className="font-semibold text-gray-500">Staff ID:</span> {viewingAsset.staff_id || "N/A"}
                        </div>
                        <div>
                          <span className="font-semibold text-gray-500">Staff Name:</span> {viewingAsset.staff_name || "N/A"}
                        </div>
                      </>
                    )}
                  </div>
                  {Object.keys(viewingAsset.specs).length > 0 && (
                    <div className="border-b pb-2">
                      <div className="font-semibold text-gray-500 mb-1">Specifications:</div>
                      <div className="grid grid-cols-2 gap-1 text-xs font-mono bg-gray-50 dark:bg-gray-900 p-2 rounded">
                        {Object.entries(viewingAsset.specs).map(([k, v]) => (
                          <div key={k}>
                            <span className="text-gray-500 uppercase">{k}:</span> {v}
                          </div>
                        ))}
                      </div>
                    </div>
                  )}
                  {viewingAsset.notes && (
                    <div>
                      <span className="font-semibold text-gray-500">Notes:</span>
                      <p className="text-xs text-gray-600 dark:text-gray-400 mt-1">{viewingAsset.notes}</p>
                    </div>
                  )}
                </div>
              )}
              <DialogFooter>
                <Button variant="outline" onClick={() => setViewingAsset(null)}>
                  Close
                </Button>
              </DialogFooter>
            </DialogContent>
          </Dialog>

          {/* Create / Edit Modal */}
          <Dialog open={isOpen} onOpenChange={setIsOpen}>
            <DialogContent className="sm:max-w-2xl max-h-[85vh] overflow-y-auto">
              <DialogHeader>
                <DialogTitle>{editingAsset ? "Edit IT Asset" : "Add New IT Asset"}</DialogTitle>
                <DialogDescription>Fill out the details for this IT hardware asset</DialogDescription>
              </DialogHeader>
              <form onSubmit={handleSubmit} className="space-y-4 py-2">
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <Label htmlFor="category_id">Category *</Label>
                    <Select
                      value={data.category_id}
                      onValueChange={(val) => setData("category_id", val)}
                      disabled={!!editingAsset}
                    >
                      <SelectTrigger className="mt-1">
                        <SelectValue placeholder="Select Category" />
                      </SelectTrigger>
                      <SelectContent>
                        {categories.map((c) => (
                          <SelectItem key={c.category_id} value={String(c.category_id)}>
                            {c.name}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  </div>
                  <div>
                    <Label htmlFor="name">Asset Name *</Label>
                    <Input
                      id="name"
                      value={data.name}
                      onChange={(e) => setData("name", e.target.value)}
                      placeholder="e.g. HP EliteBook 840 G8"
                      className="mt-1"
                      required
                    />
                    {errors.name && <p className="text-xs text-red-500 mt-1">{errors.name}</p>}
                  </div>
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                  <div>
                    <Label htmlFor="brand">Brand</Label>
                    <Input
                      id="brand"
                      value={data.brand}
                      onChange={(e) => setData("brand", e.target.value)}
                      placeholder="e.g. HP, Dell, Apple"
                      className="mt-1"
                    />
                  </div>
                  <div>
                    <Label htmlFor="model">Model</Label>
                    <Input
                      id="model"
                      value={data.model}
                      onChange={(e) => setData("model", e.target.value)}
                      placeholder="e.g. Latitude 5420"
                      className="mt-1"
                    />
                  </div>
                  <div>
                    <Label htmlFor="serial_number">Serial Number</Label>
                    <Input
                      id="serial_number"
                      value={data.serial_number}
                      onChange={(e) => setData("serial_number", e.target.value)}
                      placeholder="e.g. 5CG123456"
                      className="mt-1"
                    />
                    {errors.serial_number && (
                      <p className="text-xs text-red-500 mt-1">{errors.serial_number}</p>
                    )}
                  </div>
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                  <div>
                    <Label htmlFor="location">Location</Label>
                    <Input
                      id="location"
                      value={data.location}
                      onChange={(e) => setData("location", e.target.value)}
                      placeholder="e.g. HQ - Floor 2"
                      className="mt-1"
                    />
                  </div>
                  <div>
                    <Label htmlFor="condition">Condition</Label>
                    <Select value={data.condition} onValueChange={(val) => setData("condition", val)}>
                      <SelectTrigger className="mt-1">
                        <SelectValue placeholder="Select Condition" />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="New">New</SelectItem>
                        <SelectItem value="Excellent">Excellent</SelectItem>
                        <SelectItem value="Good">Good</SelectItem>
                        <SelectItem value="Fair">Fair</SelectItem>
                        <SelectItem value="Poor">Poor</SelectItem>
                        <SelectItem value="Damaged">Damaged</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                  <div>
                    <Label htmlFor="allocation_status">Allocation Status</Label>
                    <Select
                      value={data.allocation_status}
                      onValueChange={(val) => setData("allocation_status", val)}
                    >
                      <SelectTrigger className="mt-1">
                        <SelectValue placeholder="Select Allocation" />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="Unallocated">Unallocated</SelectItem>
                        <SelectItem value="Allocated">Allocated</SelectItem>
                        <SelectItem value="Under Maintenance">Under Maintenance</SelectItem>
                        <SelectItem value="Retired">Retired</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                </div>

                {data.allocation_status === "Allocated" && (
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 bg-blue-50/50 dark:bg-blue-950/20 p-3 rounded-lg border border-blue-100 dark:border-blue-900">
                    <div>
                      <Label htmlFor="staff_id">Allocated Staff ID *</Label>
                      <Input
                        id="staff_id"
                        value={data.staff_id}
                        onChange={(e) => setData("staff_id", e.target.value)}
                        placeholder="e.g. SBP/ACC/012"
                        className="mt-1"
                      />
                      {errors.staff_id && <p className="text-xs text-red-500 mt-1">{errors.staff_id}</p>}
                    </div>
                    <div>
                      <Label htmlFor="staff_name">Allocated Staff Name *</Label>
                      <Input
                        id="staff_name"
                        value={data.staff_name}
                        onChange={(e) => setData("staff_name", e.target.value)}
                        placeholder="e.g. John Doe"
                        className="mt-1"
                      />
                      {errors.staff_name && <p className="text-xs text-red-500 mt-1">{errors.staff_name}</p>}
                    </div>
                  </div>
                )}

                {/* Specific Specs Section */}
                {["1", "2", "3"].includes(data.category_id) && (
                  <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 border-t pt-3">
                    <div>
                      <Label htmlFor="ram">RAM</Label>
                      <Input
                        id="ram"
                        value={data.ram}
                        onChange={(e) => setData("ram", e.target.value)}
                        placeholder="e.g. 16GB"
                        className="mt-1"
                      />
                    </div>
                    <div>
                      <Label htmlFor="storage">Storage</Label>
                      <Input
                        id="storage"
                        value={data.storage}
                        onChange={(e) => setData("storage", e.target.value)}
                        placeholder="e.g. 512GB SSD"
                        className="mt-1"
                      />
                    </div>
                    <div>
                      <Label htmlFor="processor">Processor</Label>
                      <Input
                        id="processor"
                        value={data.processor}
                        onChange={(e) => setData("processor", e.target.value)}
                        placeholder="e.g. Core i7"
                        className="mt-1"
                      />
                    </div>
                  </div>
                )}

                {data.category_id === "5" && (
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 border-t pt-3">
                    <div>
                      <Label htmlFor="router_ip">Router IP Address *</Label>
                      <Input
                        id="router_ip"
                        value={data.router_ip}
                        onChange={(e) => setData("router_ip", e.target.value)}
                        placeholder="e.g. 192.168.1.1"
                        className="mt-1"
                      />
                      {errors.router_ip && <p className="text-xs text-red-500 mt-1">{errors.router_ip}</p>}
                    </div>
                    <div>
                      <Label htmlFor="mac_address">MAC Address</Label>
                      <Input
                        id="mac_address"
                        value={data.mac_address}
                        onChange={(e) => setData("mac_address", e.target.value)}
                        placeholder="e.g. 00:1A:2B:3C:4D:5E"
                        className="mt-1"
                      />
                    </div>
                  </div>
                )}

                <div>
                  <Label htmlFor="notes">Notes & Additional Details</Label>
                  <Textarea
                    id="notes"
                    value={data.notes}
                    onChange={(e) => setData("notes", e.target.value)}
                    placeholder="Additional notes..."
                    className="mt-1"
                    rows={2}
                  />
                </div>

                <DialogFooter className="pt-2">
                  <Button variant="outline" type="button" onClick={() => setIsOpen(false)}>
                    Cancel
                  </Button>
                  <Button type="submit" disabled={processing} className="bg-blue-600 hover:bg-blue-700 text-white">
                    {editingAsset ? "Save Changes" : "Create Asset"}
                  </Button>
                </DialogFooter>
              </form>
            </DialogContent>
          </Dialog>

          {/* Archive Modal */}
          <Dialog open={isArchiveOpen} onOpenChange={setIsArchiveOpen}>
            <DialogContent className="sm:max-w-md">
              <DialogHeader>
                <DialogTitle>Archive IT Asset</DialogTitle>
                <DialogDescription>
                  Are you sure you want to move this asset to the archive? It will be removed from active inventory but can be restored later.
                </DialogDescription>
              </DialogHeader>
              <DialogFooter>
                <Button variant="outline" onClick={() => setIsArchiveOpen(false)}>
                  Cancel
                </Button>
                <Button variant="default" className="bg-amber-600 hover:bg-amber-700 text-white" onClick={handleArchive}>
                  Archive Asset
                </Button>
              </DialogFooter>
            </DialogContent>
          </Dialog>

          {/* Delete Modal */}
          <Dialog open={isDeleteOpen} onOpenChange={setIsDeleteOpen}>
            <DialogContent className="sm:max-w-md">
              <DialogHeader>
                <DialogTitle>Delete IT Asset</DialogTitle>
                <DialogDescription>
                  Are you sure you want to permanently delete this IT asset and its specs? This action cannot be undone.
                </DialogDescription>
              </DialogHeader>
              <DialogFooter>
                <Button variant="outline" onClick={() => setIsDeleteOpen(false)}>
                  Cancel
                </Button>
                <Button variant="destructive" onClick={handleDelete}>
                  Permanently Delete
                </Button>
              </DialogFooter>
            </DialogContent>
          </Dialog>
        </div>
      </AppLayout>
    </ErrorBoundary>
  );
}
