import { Head, Link, useForm, InertiaFormProps } from "@inertiajs/react";
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

interface Ssnit {
    staffid: string;
    shop: string;
    lastname: string;
    firstname: string;
    othername: string | null;
    ssnit1a: string;
    national: string | null;
}

interface Props {
    ssnits: {
        data: Ssnit[];
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

interface FormErrors {
    [key: string]: string;
}

// Extend InertiaFormProps to include destroy
interface ExtendedFormProps<T> extends InertiaFormProps<T> {
    destroy: (url: string, options?: { onSuccess?: () => void; onError?: (errors: FormErrors) => void }) => void;
}

const breadcrumbs = [
    { title: "SSNIT Inhouse Details", href: "/ssnitdetailsinhouse" },
];

export default function SsnitInhouseIndex({ ssnits, filters, totalRecords, searchCount, flash }: Props) {
    const [isOpen, setIsOpen] = useState(false);
    const [editingSsnit, setEditingSsnit] = useState<Ssnit | null>(null);
    const [isDeleteOpen, setIsDeleteOpen] = useState(false);
    const [deleteSsnitId, setDeleteSsnitId] = useState<string | null>(null);
    const [isBulkDeleteOpen, setIsBulkDeleteOpen] = useState(false);
    const [selectedStaffIds, setSelectedStaffIds] = useState<string[]>([]);
    const [showToast, setShowToast] = useState(false);
    const [toastMessage, setToastMessage] = useState("");
    const [toastType, setToastType] = useState<"success" | "error">("success");
    const [search, setSearch] = useState(filters.search || "");
    const [sort, setSort] = useState(filters.sort || "staffid");
    const [order, setOrder] = useState(filters.order || "asc");
    const [isBulkUploadOpen, setIsBulkUploadOpen] = useState(false);

    const { data, setData, get, post, put, destroy, processing, reset, errors } = useForm({
        staffid: "",
        shop: "",
        lastname: "",
        firstname: "",
        othername: "",
        ssnit1a: "",
        national: "",
    }) as ExtendedFormProps<{
        staffid: string;
        shop: string;
        lastname: string;
        firstname: string;
        othername: string;
        ssnit1a: string;
        national: string;
    }>;

    const bulkDeleteForm = useForm({
        staffids: [] as string[],
    });

    const bulkUploadForm = useForm({
        file: null as File | null,
    });

    useEffect(() => {
        bulkDeleteForm.setData('staffids', selectedStaffIds);
        console.log("Updated bulkDeleteForm.staffids:", selectedStaffIds);
    }, [selectedStaffIds]);

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
        if (Object.keys(bulkDeleteForm.errors).length > 0) {
            const errorMessage = bulkDeleteForm.errors.staffids || "Failed to delete selected records.";
            setToastMessage(errorMessage);
            setToastType("error");
            setShowToast(true);
        }
        if (Object.keys(bulkUploadForm.errors).length > 0) {
            const errorMessage = bulkUploadForm.errors.file || "Failed to upload file.";
            setToastMessage(errorMessage);
            setToastType("error");
            setShowToast(true);
        }
        if (Object.keys(errors).length > 0) {
            const errorMessage = Object.values(errors).join(' | ');
            setToastMessage(errorMessage);
            setToastType("error");
            setShowToast(true);
        }
    }, [bulkDeleteForm.errors, bulkUploadForm.errors, errors]);

    useEffect(() => {
        if (showToast) {
            const timeout = toastType === "error" ? 60000 : 3000;
            const timer = setTimeout(() => setShowToast(false), timeout);
            return () => clearTimeout(timer);
        }
    }, [showToast, toastType]);

    const handleEdit = (ssnit: Ssnit) => {
        console.log('Editing SSNIT record:', ssnit);
        setEditingSsnit(ssnit);
        setData({
            staffid: ssnit.staffid || "",
            shop: ssnit.shop || "",
            lastname: ssnit.lastname || "",
            firstname: ssnit.firstname || "",
            othername: ssnit.othername || "",
            ssnit1a: ssnit.ssnit1a || "",
            national: ssnit.national || "",
        });
        setIsOpen(true);
    };

    const handleSubmit = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        console.log("Submitting form data:", data);
        if (editingSsnit) {
            put(route("ssnitdetailsinhouse.update", editingSsnit.staffid), {
                onSuccess: () => {
                    console.log("Update successful");
                    setIsOpen(false);
                    reset();
                    setEditingSsnit(null);
                },
                onError: (err: FormErrors) => {
                    console.log("Update failed with errors:", err);
                },
            });
        } else {
            post(route("ssnitdetailsinhouse.store"), {
                onSuccess: () => {
                    console.log("Create successful");
                    setIsOpen(false);
                    reset();
                },
                onError: (err: FormErrors) => {
                    console.log("Create failed with errors:", err);
                },
            });
        }
    };

    const handleDelete = (staffid: string) => {
        setDeleteSsnitId(staffid);
        setIsDeleteOpen(true);
    };

    const confirmDelete = () => {
        if (deleteSsnitId) {
            destroy(route("ssnitdetailsinhouse.destroy", deleteSsnitId), {
                onSuccess: () => {
                    console.log("Delete successful");
                    setIsDeleteOpen(false);
                    setDeleteSsnitId(null);
                },
                onError: (err: FormErrors) => {
                    console.log("Delete failed with errors:", err);
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
        setSelectedStaffIds(checked ? ssnits.data.map((ssnit) => ssnit.staffid) : []);
    };

    const handleBulkDelete = () => {
        if (selectedStaffIds.length > 0) {
            setIsBulkDeleteOpen(true);
        }
    };

    const confirmBulkDelete = () => {
        console.log("Bulk delete triggered with staffids:", bulkDeleteForm.data.staffids);
        bulkDeleteForm.post(route("ssnitdetailsinhouse.bulk-destroy"), {
            onSuccess: () => {
                console.log("Bulk delete successful");
                setIsBulkDeleteOpen(false);
                setSelectedStaffIds([]);
            },
            onError: (err: FormErrors) => {
                console.log("Bulk delete failed with errors:", err);
            },
        });
    };

    const handleSearchSort = (overrideSearch?: string, overrideSort?: string, overrideOrder?: string) => {
        const s = overrideSearch !== undefined ? overrideSearch : search;
        const st = overrideSort !== undefined ? overrideSort : sort;
        const ord = overrideOrder !== undefined ? overrideOrder : order;

        get(route("ssnitdetailsinhouse.index", { search: s, sort: st, order: ord }), {
            preserveState: true,
            preserveScroll: true,
            onError: (err: FormErrors) => {
                console.log("Search/sort failed with errors:", err);
            },
        });
    };

    const handleExport = () => {
        const url = route("ssnitdetailsinhouse.export", { search, sort, order });
        const link = document.createElement("a");
        link.href = url;
        link.download = "ssnit_inhouse_records.xlsx";
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    };

    const handleDownloadTemplate = () => {
        const url = route("ssnitdetailsinhouse.download-template");
        const link = document.createElement("a");
        link.href = url;
        link.download = "ssnit_inhouse_template.xlsx";
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    };

    const handleBulkUpload = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        if (bulkUploadForm.data.file) {
            console.log("Uploading file:", bulkUploadForm.data.file.name);
            bulkUploadForm.post(route("ssnitdetailsinhouse.bulk-upload"), {
                onSuccess: () => {
                    console.log("Bulk upload successful");
                    setIsBulkUploadOpen(false);
                    bulkUploadForm.reset();
                },
                onError: (err: FormErrors) => {
                    console.log("Bulk upload failed with errors:", err);
                },
            });
        }
    };

    const shopOptions = [
        'SBP AFRICA',
        'SBP AFRICA LTD',
        'SBP AFRICA LTD.',
        'SBP SOLUTIONS',
        'SBP SOLUTIONS LTD'
    ];

    return (
        <ErrorBoundary>
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="SSNIT Inhouse Details" />
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
                            <h1 className="text-2xl font-bold">SSNIT Inhouse Details</h1>
                            <div className="flex flex-col sm:flex-row gap-4">
                                <ShadcnInput
                                    placeholder="Search by staff ID, name, SSNIT, or shop..."
                                    value={search}
                                    onChange={(e) => {
                                        const val = e.target.value;
                                        setSearch(val);
                                        handleSearchSort(val);
                                    }}
                                    onKeyDown={(e) => {
                                        if (e.key === "Enter") {
                                            handleSearchSort(search);
                                        }
                                    }}
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
                                            setEditingSsnit(null);
                                        }
                                    }}
                                >
                                    <DialogTrigger asChild>
                                        <Button className="w-full sm:w-auto">
                                            <Plus className="h-4 w-4 mr-2" />
                                            New Inhouse SSNIT
                                        </Button>
                                    </DialogTrigger>
                                    <DialogContent className="p-4 sm:max-w-md w-full max-h-[80vh] overflow-y-auto">
                                        <DialogHeader>
                                            <DialogTitle>{editingSsnit ? "Edit Inhouse SSNIT Record" : "Create New Inhouse SSNIT Record"}</DialogTitle>
                                        </DialogHeader>
                                        <form onSubmit={handleSubmit}>
                                            <div className="grid gap-4 py-4">
                                                <div className="grid gap-2">
                                                    <Label htmlFor="staffid">Staff ID</Label>
                                                    <Input
                                                        id="staffid"
                                                        value={data.staffid}
                                                        onChange={(e) => setData("staffid", e.target.value)}
                                                        required
                                                        className="w-full"
                                                        disabled={!!editingSsnit} // Disable staffid for updates
                                                    />
                                                    {errors.staffid && <p className="text-red-500 text-sm">{errors.staffid}</p>}
                                                </div>
                                                <div className="grid gap-2">
                                                    <Label htmlFor="shop">Shop</Label>
                                                    <Select
                                                        value={data.shop}
                                                        onValueChange={(value) => setData("shop", value)}
                                                        required
                                                    >
                                                        <SelectTrigger className="w-full">
                                                            <SelectValue placeholder="Select a shop" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {shopOptions.map((shop) => (
                                                                <SelectItem key={shop} value={shop}>
                                                                    {shop}
                                                                </SelectItem>
                                                            ))}
                                                        </SelectContent>
                                                    </Select>
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
                                                    <Label htmlFor="ssnit1a">SSNIT Number</Label>
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
                                                    <Label htmlFor="national">National ID</Label>
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
                                                <Button type="button" onClick={() => console.log("Current form data:", data)}>
                                                    Log Form Data
                                                </Button>
                                                <Button type="submit" disabled={processing}>
                                                    {editingSsnit ? "Update" : "Create"}
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
                                        <DialogTitle>Bulk Upload Inhouse SSNIT Records</DialogTitle>
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

                    {ssnits.data.length > 0 ? (
                        <Card>
                            <CardHeader>
                                <CardTitle>Your Inhouse SSNIT Records</CardTitle>
                                <div className="text-sm text-muted-foreground">
                                    {searchCount !== null
                                        ? `Showing ${searchCount} of ${totalRecords} records matching your search`
                                        : `Total Records: ${totalRecords}`}
                                </div>
                            </CardHeader>
                            <CardContent>
                                <div className="block md:hidden space-y-4">
                                    {ssnits.data.map((ssnit) => (
                                        <div key={ssnit.staffid} className="border rounded-lg p-4 bg-background">
                                            <div className="flex flex-col gap-2">
                                                <div className="flex items-center gap-2">
                                                    <Checkbox
                                                        checked={selectedStaffIds.includes(ssnit.staffid)}
                                                        onCheckedChange={(checked) => handleSelect(ssnit.staffid, checked as boolean)}
                                                    />
                                                    <div className="font-semibold">{ssnit.staffid}</div>
                                                </div>
                                                <div className="text-sm text-muted-foreground">Shop: {ssnit.shop}</div>
                                                <div className="text-sm text-muted-foreground">Name: {ssnit.lastname}, {ssnit.firstname} {ssnit.othername || ""}</div>
                                                <div className="text-sm text-muted-foreground">SSNIT: {ssnit.ssnit1a}</div>
                                                <div className="text-sm text-muted-foreground">National ID: {ssnit.national || "N/A"}</div>
                                                <div className="flex gap-2">
                                                    <Button variant="outline" size="sm" onClick={() => handleEdit(ssnit)}>
                                                        <Pencil className="h-4 w-4" />
                                                    </Button>
                                                    <Button variant="destructive" size="sm" onClick={() => handleDelete(ssnit.staffid)}>
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
                                                        checked={selectedStaffIds.length === ssnits.data.length && ssnits.data.length > 0}
                                                        onCheckedChange={handleSelectAll}
                                                    />
                                                </TableHead>
                                                <TableHead>Staff ID</TableHead>
                                                <TableHead>Shop</TableHead>
                                                <TableHead>Last Name</TableHead>
                                                <TableHead>First Name</TableHead>
                                                <TableHead>Other Name</TableHead>
                                                <TableHead>SSNIT Number</TableHead>
                                                <TableHead>National ID</TableHead>
                                                <TableHead className="text-right">Actions</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {ssnits.data.map((ssnit) => (
                                                <TableRow key={ssnit.staffid}>
                                                    <TableCell>
                                                        <Checkbox
                                                            checked={selectedStaffIds.includes(ssnit.staffid)}
                                                            onCheckedChange={(checked) => handleSelect(ssnit.staffid, checked as boolean)}
                                                        />
                                                    </TableCell>
                                                    <TableCell>{ssnit.staffid}</TableCell>
                                                    <TableCell>{ssnit.shop}</TableCell>
                                                    <TableCell>{ssnit.lastname}</TableCell>
                                                    <TableCell>{ssnit.firstname}</TableCell>
                                                    <TableCell>{ssnit.othername || "N/A"}</TableCell>
                                                    <TableCell>{ssnit.ssnit1a}</TableCell>
                                                    <TableCell>{ssnit.national || "N/A"}</TableCell>
                                                    <TableCell className="text-right">
                                                        <div className="flex gap-2 justify-end">
                                                            <Button variant="outline" size="sm" onClick={() => handleEdit(ssnit)}>
                                                                <Pencil className="h-4 w-4" />
                                                            </Button>
                                                            <Button variant="destructive" size="sm" onClick={() => handleDelete(ssnit.staffid)}>
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
                                        {ssnits.links.map((link, index) => (
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
                                <p className="text-center text-gray-500">No inhouse SSNIT records found. Create a new record to get started!</p>
                            </CardContent>
                        </Card>
                    )}

                    <Dialog open={isDeleteOpen} onOpenChange={setIsDeleteOpen}>
                        <DialogContent className="p-4 sm:max-w-md w-full">
                            <DialogHeader>
                                <DialogTitle>Confirm Deletion</DialogTitle>
                            </DialogHeader>
                            <p>Are you sure you want to delete this inhouse SSNIT record? This action cannot be undone.</p>
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
                            <p>Are you sure you want to delete {selectedStaffIds.length} inhouse SSNIT record(s)? This action cannot be undone.</p>
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
