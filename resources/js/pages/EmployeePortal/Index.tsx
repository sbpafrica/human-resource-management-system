import { Head, Link, useForm, InertiaFormProps, router } from "@inertiajs/react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Plus, Pencil, Trash, CheckCircle, XCircle, Download, Upload, ArrowUpDown, Eye, Archive } from "lucide-react";

import AppLayout from "@/layouts/app-layout";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger, DialogFooter } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Checkbox } from "@/components/ui/checkbox";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { useState, useEffect, useCallback } from "react";
import { Input as ShadcnInput } from "@/components/ui/input";
import ErrorBoundary from "@/components/ErrorBoundary";
import debounce from "lodash/debounce";

interface Employee {
  staffid: string;
  employeename: string;
  company: string | null;
  shop: string | null;
  position: string | null;
  qualification: string | null;
  startdate: string | null;
  dateofbirth: string | null;
  ghcard: string | null;
  phone1: string | null;
  phone2: string | null;
  gender: string | null;
  nationality: string | null;
  email: string | null;
  nextofkin: string | null;
  relation: string | null;
  contact: string | null;
  guarantor: string | null;
  contactgua: string | null;
  profilepic: string | null;
  ghanafront: string | null;
  ghanaback: string | null;
  gpsaddress: string | null;
}

interface Props {
  employees: {
    data: Employee[];
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

interface FormErrors {
  [key: string]: string;
}

interface ExtendedFormProps<T> extends InertiaFormProps<T> {}

const breadcrumbs = [
  { title: "Employee Portal", href: "/employeeportal" },
];

const qualificationOptions = [
  "BECE",
  "WASSCE",
  "Diploma",
  "HND",
  "Bachelor’s Degree",
  "Postgraduate Diploma",
  "Master’s Degree",
  "PhD",
];

export default function EmployeePortal({ employees, filters, totalRecords, searchCount, clients, flash }: Props) {
  const [isOpen, setIsOpen] = useState(false);
  const [editingEmployee, setEditingEmployee] = useState<Employee | null>(null);
  const [isDeleteOpen, setIsDeleteOpen] = useState(false);
  const [deleteEmployeeId, setDeleteEmployeeId] = useState<string | null>(null);
  const [isBulkDeleteOpen, setIsBulkDeleteOpen] = useState(false);
  const [isArchiveOpen, setIsArchiveOpen] = useState(false);
  const [archiveEmployee, setArchiveEmployee] = useState<Employee | null>(null);
  const [isBulkArchiveOpen, setIsBulkArchiveOpen] = useState(false);
  const [selectedIds, setSelectedIds] = useState<string[]>([]);
  const [showToast, setShowToast] = useState(false);
  const [toastMessage, setToastMessage] = useState("");
  const [toastType, setToastType] = useState<"success" | "error">("success");
  const [search, setSearch] = useState(filters.search || "");
  const [sort, setSort] = useState(filters.sort || "employeename");
  const [order, setOrder] = useState(filters.order || "asc");
  const [isBulkUploadOpen, setIsBulkUploadOpen] = useState(false);

  const { data, setData, get, post, put, delete: destroy, processing, reset, errors } = useForm({
    staffid: "",
    employeename: "",
    company: "",
    shop: "",
    position: "",
    qualification: "",
    startdate: "",
    dateofbirth: "",
    ghcard: "",
    phone1: "",
    phone2: "",
    gender: "",
    nationality: "",
    email: "",
    nextofkin: "",
    relation: "",
    contact: "",
    guarantor: "",
    contactgua: "",
    profilepic: "",
    ghanafront: "",
    ghanaback: "",
    gpsaddress: "",
  }) as ExtendedFormProps<{
    staffid: string;
    employeename: string;
    company: string;
    shop: string;
    position: string;
    qualification: string;
    startdate: string;
    dateofbirth: string;
    ghcard: string;
    phone1: string;
    phone2: string;
    gender: string;
    nationality: string;
    email: string;
    nextofkin: string;
    relation: string;
    contact: string;
    guarantor: string;
    contactgua: string;
    profilepic: string;
    ghanafront: string;
    ghanaback: string;
    gpsaddress: string;
  }>;

  const bulkDeleteForm = useForm({
    ids: [] as string[],
  });

  const bulkArchiveForm = useForm({
    ids: [] as string[],
  });

  const bulkUploadForm = useForm({
    file: null as File | null,
  });


  // Debounced search function
  const debouncedSearch = useCallback(
    debounce((searchValue: string, sortValue: string, orderValue: string) => {
      get(route("employeeportal.index", { search: searchValue, sort: sortValue, order: orderValue }), {
        preserveState: true,
        preserveScroll: true,
        onError: (err: FormErrors) => {
          console.log("Search failed with errors:", err);
          setToastMessage("Search failed: " + Object.values(err).join(" | "));
          setToastType("error");
          setShowToast(true);
        },
      });
    }, 300),
    []
  );

  useEffect(() => {
    bulkDeleteForm.setData('ids', selectedIds);
    bulkArchiveForm.setData('ids', selectedIds);
    console.log("Updated selectedIds:", selectedIds);
  }, [selectedIds]);

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
      const errorMessage = bulkDeleteForm.errors.ids || "Failed to delete selected records.";
      setToastMessage(errorMessage);
      setToastType("error");
      setShowToast(true);
    }
    if (Object.keys(bulkArchiveForm.errors).length > 0) {
      const errorMessage = bulkArchiveForm.errors.ids || "Failed to archive selected records.";
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
  }, [bulkDeleteForm.errors, bulkArchiveForm.errors, bulkUploadForm.errors, errors]);


  useEffect(() => {
    if (showToast) {
      const timeout = toastType === "error" ? 60000 : 3000;
      const timer = setTimeout(() => setShowToast(false), timeout);
      return () => clearTimeout(timer);
    }
  }, [showToast, toastType]);

  useEffect(() => {
    debouncedSearch(search, sort, order);
  }, [search, sort, order, debouncedSearch]);

  const handleEdit = (employee: Employee) => {
    console.log('Editing Employee record:', employee);
    setEditingEmployee(employee);
    setData({
      staffid: employee.staffid || "",
      employeename: employee.employeename || "",
      company: employee.company || "",
      shop: employee.shop || "",
      position: employee.position || "",
      qualification: employee.qualification || "",
      startdate: employee.startdate || "",
      dateofbirth: employee.dateofbirth || "",
      ghcard: employee.ghcard || "",
      phone1: employee.phone1 || "",
      phone2: employee.phone2 || "",
      gender: employee.gender || "",
      nationality: employee.nationality || "",
      email: employee.email || "",
      nextofkin: employee.nextofkin || "",
      relation: employee.relation || "",
      contact: employee.contact || "",
      guarantor: employee.guarantor || "",
      contactgua: employee.contactgua || "",
      profilepic: employee.profilepic || "",
      ghanafront: employee.ghanafront || "",
      ghanaback: employee.ghanaback || "",
      gpsaddress: employee.gpsaddress || "",
    });
    setIsOpen(true);
  };

  const handleSubmit = (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    console.log("Submitting form data:", data);
    if (editingEmployee) {
      put(route("employeeportal.update", editingEmployee.staffid), {
        onSuccess: () => {
          console.log("Update successful");
          setIsOpen(false);
          reset();
          setEditingEmployee(null);
        },
        onError: (err: FormErrors) => {
          console.log("Update failed with errors:", err);
          setToastMessage("Failed to update employee: " + Object.values(err).join(" | "));
          setToastType("error");
          setShowToast(true);
        },
      });
    } else {
      post(route("employeeportal.store"), {
        onSuccess: () => {
          console.log("Create successful");
          setIsOpen(false);
          reset();
        },
        onError: (err: FormErrors) => {
          console.log("Create failed with errors:", err);
          setToastMessage("Failed to create employee: " + Object.values(err).join(" | "));
          setToastType("error");
          setShowToast(true);
        },
      });
    }
  };

  const handleDelete = (id: string) => {
    setDeleteEmployeeId(id);
    setIsDeleteOpen(true);
  };

  const confirmDelete = () => {
    console.log("Confirm delete called with ID:", deleteEmployeeId);
    if (deleteEmployeeId) {
      const url = route("employeeportal.destroy", deleteEmployeeId);
      console.log("Destroy URL:", url);
      destroy(url, {
        onSuccess: () => {
          console.log("Delete successful");
          setIsDeleteOpen(false);
          setDeleteEmployeeId(null);
        },
        onError: (err: FormErrors) => {
          console.log("Delete failed with errors:", err);
          setToastMessage("Failed to delete employee: " + Object.values(err).join(" | "));
          setToastType("error");
          setShowToast(true);
        },
      });
    }
  };

  const handleSelect = (id: string, checked: boolean) => {
    setSelectedIds((prev) =>
      checked ? [...prev, id] : prev.filter((selectedId) => selectedId !== id)
    );
  };

  const handleSelectAll = (checked: boolean) => {
    setSelectedIds(checked ? employees.data.map((employee) => employee.staffid) : []);
  };

  const handleBulkDelete = () => {
    if (selectedIds.length > 0) {
      setIsBulkDeleteOpen(true);
    }
  };

  const confirmBulkDelete = () => {
    console.log("Bulk delete triggered with ids:", bulkDeleteForm.data.ids);
    bulkDeleteForm.post(route("employeeportal.bulk-destroy"), {
      onSuccess: () => {
        console.log("Bulk delete successful");
        setIsBulkDeleteOpen(false);
        setSelectedIds([]);
      },
      onError: (err: FormErrors) => {
        console.log("Bulk delete failed with errors:", err);
        setToastMessage("Failed to delete selected employees: " + Object.values(err).join(" | "));
        setToastType("error");
        setShowToast(true);
      },
      preserveState: true,
      preserveScroll: true,
    });
  };

  const handleArchive = (employee: Employee) => {
    setArchiveEmployee(employee);
    setIsArchiveOpen(true);
  };

  const confirmArchive = () => {
    if (archiveEmployee) {
      router.post(
        route("employeeportal.archive", archiveEmployee.staffid),
        {},
        {
          onSuccess: () => {
            console.log("Archive successful");
            setIsArchiveOpen(false);
            setArchiveEmployee(null);
          },
          onError: (err: FormErrors) => {
            console.log("Archive failed with errors:", err);
            setToastMessage("Failed to archive employee: " + Object.values(err).join(" | "));
            setToastType("error");
            setShowToast(true);
          },
        }
      );
    }
  };

  const handleBulkArchive = () => {
    if (selectedIds.length > 0) {
      setIsBulkArchiveOpen(true);
    }
  };

  const confirmBulkArchive = () => {
    console.log("Bulk archive triggered with ids:", bulkArchiveForm.data.ids);
    bulkArchiveForm.post(route("employeeportal.bulk-archive"), {
      onSuccess: () => {
        console.log("Bulk archive successful");
        setIsBulkArchiveOpen(false);
        setSelectedIds([]);
      },
      onError: (err: FormErrors) => {
        console.log("Bulk archive failed with errors:", err);
        setToastMessage("Failed to archive selected employees: " + Object.values(err).join(" | "));
        setToastType("error");
        setShowToast(true);
      },
      preserveState: true,
      preserveScroll: true,
    });
  };


  const handleSort = (column: string) => {
    const newOrder = sort === column && order === "asc" ? "desc" : "asc";
    setSort(column);
    setOrder(newOrder);
  };

  // EmployeePortal.tsx (or equivalent React component)
const handleExport = () => {
  try {
    const url = route("employeeportal.export", { search, sort, order });
    console.log('Export URL:', url); // Debug: Log the URL
    const link = document.createElement("a");
    link.href = url;
    link.download = "employees.xlsx";
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  } catch (error) {
    console.error('Export error:', error);
    setToastMessage('Failed to initiate export: Please ensure you are logged in.');
    setToastType('error');
    setShowToast(true);
  }
};

const handleDownloadTemplate = () => {
  try {
    const url = route("employeeportal.download-template");
    console.log('Download Template URL:', url); // Debug: Log the URL
    const link = document.createElement("a");
    link.href = url;
    link.download = "employees_template.xlsx";
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  } catch (error) {
    console.error('Download template error:', error);
    setToastMessage('Failed to initiate template download: Please ensure you are logged in.');
    setToastType('error');
    setShowToast(true);
  }
};

  const handleBulkUpload = (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    if (bulkUploadForm.data.file) {
      console.log("Uploading file:", bulkUploadForm.data.file.name);
      bulkUploadForm.post(route("employeeportal.bulk-upload"), {
        forceFormData: true,
        onSuccess: () => {
          console.log("Bulk upload successful");
          setIsBulkUploadOpen(false);
          bulkUploadForm.reset();
        },
        onError: (err: FormErrors) => {
          console.log("Bulk upload failed with errors:", err);
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
        <Head title="Employee Portal" />
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

          <div className="flex flex-col gap-4">
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
              <h1 className="text-2xl font-bold">Employee Portal</h1>
              <div className="flex flex-col sm:flex-row gap-4">
                <ShadcnInput
                  placeholder="Search by employee name, staff ID, or company..."
                  value={search}
                  onChange={(e) => setSearch(e.target.value)}
                  className="w-full sm:w-64"
                />
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
                      setEditingEmployee(null);
                    }
                  }}
                >
                  <DialogTrigger asChild>
                    <Button className="w-full sm:w-auto">
                      <Plus className="h-4 w-4 mr-2" />
                      New Employee
                    </Button>
                  </DialogTrigger>
                  <DialogContent className="p-4 sm:max-w-4xl w-full max-h-[80vh] overflow-y-auto">
                    <DialogHeader>
                      <DialogTitle>{editingEmployee ? "Edit Employee" : "Create New Employee"}</DialogTitle>
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
                            disabled={!!editingEmployee}
                          />
                          {errors.staffid && <p className="text-red-500 text-sm">{errors.staffid}</p>}
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
                          <Label htmlFor="company">Company</Label>
                          <Select
                            value={data.company}
                            onValueChange={(value) => setData("company", value)}
                          >
                            <SelectTrigger id="company">
                              <SelectValue placeholder="Select a company" />
                            </SelectTrigger>
                            <SelectContent>
                              {clients.map((client) => (
                                <SelectItem key={client} value={client}>
                                  {client}
                                </SelectItem>
                              ))}
                            </SelectContent>
                          </Select>
                          {errors.company && <p className="text-red-500 text-sm">{errors.company}</p>}
                        </div>
                        <div className="grid gap-2">
                          <Label htmlFor="shop">Shop</Label>
                          <Input
                            id="shop"
                            value={data.shop}
                            onChange={(e) => setData("shop", e.target.value)}
                            className="w-full"
                          />
                          {errors.shop && <p className="text-red-500 text-sm">{errors.shop}</p>}
                        </div>
                        <div className="grid gap-2">
                          <Label htmlFor="position">Position</Label>
                          <Input
                            id="position"
                            value={data.position}
                            onChange={(e) => setData("position", e.target.value)}
                            className="w-full"
                          />
                          {errors.position && <p className="text-red-500 text-sm">{errors.position}</p>}
                        </div>
                        <div className="grid gap-2">
                          <Label htmlFor="qualification">Qualification</Label>
                          <Select
                            value={data.qualification}
                            onValueChange={(value) => setData("qualification", value)}
                          >
                            <SelectTrigger id="qualification">
                              <SelectValue placeholder="Select a qualification" />
                            </SelectTrigger>
                            <SelectContent>
                              {qualificationOptions.map((option) => (
                                <SelectItem key={option} value={option}>
                                  {option}
                                </SelectItem>
                              ))}
                            </SelectContent>
                          </Select>
                          {errors.qualification && <p className="text-red-500 text-sm">{errors.qualification}</p>}
                        </div>
                        <div className="grid gap-2">
                          <Label htmlFor="startdate">Start Date</Label>
                          <Input
                            id="startdate"
                            type="date"
                            value={data.startdate}
                            onChange={(e) => setData("startdate", e.target.value)}
                            className="w-full"
                          />
                          {errors.startdate && <p className="text-red-500 text-sm">{errors.startdate}</p>}
                        </div>
                        <div className="grid gap-2">
                          <Label htmlFor="dateofbirth">Date of Birth</Label>
                          <Input
                            id="dateofbirth"
                            type="date"
                            value={data.dateofbirth}
                            onChange={(e) => setData("dateofbirth", e.target.value)}
                            className="w-full"
                          />
                          {errors.dateofbirth && <p className="text-red-500 text-sm">{errors.dateofbirth}</p>}
                        </div>
                        <div className="grid gap-2">
                          <Label htmlFor="ghcard">Ghana Card</Label>
                          <Input
                            id="ghcard"
                            value={data.ghcard}
                            onChange={(e) => setData("ghcard", e.target.value)}
                            className="w-full"
                          />
                          {errors.ghcard && <p className="text-red-500 text-sm">{errors.ghcard}</p>}
                        </div>
                        <div className="grid gap-2">
                          <Label htmlFor="phone1">Phone 1</Label>
                          <Input
                            id="phone1"
                            value={data.phone1}
                            onChange={(e) => setData("phone1", e.target.value)}
                            className="w-full"
                          />
                          {errors.phone1 && <p className="text-red-500 text-sm">{errors.phone1}</p>}
                        </div>
                        <div className="grid gap-2">
                          <Label htmlFor="phone2">Phone 2</Label>
                          <Input
                            id="phone2"
                            value={data.phone2}
                            onChange={(e) => setData("phone2", e.target.value)}
                            className="w-full"
                          />
                          {errors.phone2 && <p className="text-red-500 text-sm">{errors.phone2}</p>}
                        </div>
                        <div className="grid gap-2">
                          <Label htmlFor="gender">Gender</Label>
                          <Input
                            id="gender"
                            value={data.gender}
                            onChange={(e) => setData("gender", e.target.value)}
                            className="w-full"
                          />
                          {errors.gender && <p className="text-red-500 text-sm">{errors.gender}</p>}
                        </div>
                        <div className="grid gap-2">
                          <Label htmlFor="nationality">Nationality</Label>
                          <Input
                            id="nationality"
                            value={data.nationality}
                            onChange={(e) => setData("nationality", e.target.value)}
                            className="w-full"
                          />
                          {errors.nationality && <p className="text-red-500 text-sm">{errors.nationality}</p>}
                        </div>
                        <div className="grid gap-2">
                          <Label htmlFor="email">Email</Label>
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
                          <Label htmlFor="nextofkin">Next of Kin</Label>
                          <Input
                            id="nextofkin"
                            value={data.nextofkin}
                            onChange={(e) => setData("nextofkin", e.target.value)}
                            className="w-full"
                          />
                          {errors.nextofkin && <p className="text-red-500 text-sm">{errors.nextofkin}</p>}
                        </div>
                        <div className="grid gap-2">
                          <Label htmlFor="relation">Relation</Label>
                          <Input
                            id="relation"
                            value={data.relation}
                            onChange={(e) => setData("relation", e.target.value)}
                            className="w-full"
                          />
                          {errors.relation && <p className="text-red-500 text-sm">{errors.relation}</p>}
                        </div>
                        <div className="grid gap-2">
                          <Label htmlFor="contact">Contact</Label>
                          <Input
                            id="contact"
                            value={data.contact}
                            onChange={(e) => setData("contact", e.target.value)}
                            className="w-full"
                          />
                          {errors.contact && <p className="text-red-500 text-sm">{errors.contact}</p>}
                        </div>
                        <div className="grid gap-2">
                          <Label htmlFor="guarantor">Guarantor</Label>
                          <Input
                            id="guarantor"
                            value={data.guarantor}
                            onChange={(e) => setData("guarantor", e.target.value)}
                            className="w-full"
                          />
                          {errors.guarantor && <p className="text-red-500 text-sm">{errors.guarantor}</p>}
                        </div>
                        <div className="grid gap-2">
                          <Label htmlFor="contactgua">Contact (Guarantor)</Label>
                          <Input
                            id="contactgua"
                            value={data.contactgua}
                            onChange={(e) => setData("contactgua", e.target.value)}
                            className="w-full"
                          />
                          {errors.contactgua && <p className="text-red-500 text-sm">{errors.contactgua}</p>}
                        </div>
                        <div className="grid gap-2">
                          <Label htmlFor="gpsaddress">GPS Address</Label>
                          <Input
                            id="gpsaddress"
                            value={data.gpsaddress}
                            onChange={(e) => setData("gpsaddress", e.target.value)}
                            className="w-full"
                          />
                          {errors.gpsaddress && <p className="text-red-500 text-sm">{errors.gpsaddress}</p>}
                        </div>
                      </div>
                      <DialogFooter className="flex flex-col sm:flex-row sm:justify-end gap-2">
                        <Button type="button" onClick={() => console.log("Current form data:", data)}>
                          Log Form Data
                        </Button>
                        <Button type="submit" disabled={processing}>
                          {editingEmployee ? "Update" : "Create"}
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
                    <DialogTitle>Bulk Upload Employees</DialogTitle>
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
                type="button"
                onClick={handleBulkArchive}
                disabled={selectedIds.length === 0}
                className="w-full sm:w-auto bg-amber-600 hover:bg-amber-700 text-white"
              >
                <Archive className="h-4 w-4 mr-2" />
                Archive Selected {selectedIds.length > 0 ? `(${selectedIds.length})` : ""}
              </Button>
              <Button
                variant="destructive"
                onClick={handleBulkDelete}
                disabled={selectedIds.length === 0}
                className="w-full sm:w-auto"
              >
                <Trash className="h-4 w-4 mr-2" />
                Delete Selected
              </Button>
            </div>
          </div>

          {employees.data.length > 0 ? (
            <Card>
              <CardHeader>
                <CardTitle>Your Employees</CardTitle>
                <div className="text-sm text-muted-foreground">
                  {searchCount !== null
                    ? `Showing ${searchCount} of ${totalRecords} records matching your search`
                    : `Total Records: ${totalRecords}`}
                </div>
              </CardHeader>
              <CardContent>
                <div className="block md:hidden space-y-4">
                  {employees.data.map((employee) => (
                    <div key={employee.staffid} className="border rounded-lg p-4 bg-background">
                      <div className="flex flex-col gap-2">
                        <div className="flex items-center gap-2">
                          <Checkbox
                            checked={selectedIds.includes(employee.staffid)}
                            onCheckedChange={(checked) => handleSelect(employee.staffid, checked as boolean)}
                          />
                          <div className="font-semibold">{employee.employeename}</div>
                        </div>
                        <div className="text-sm text-muted-foreground">Staff ID: {employee.staffid}</div>
                        <div className="text-sm text-muted-foreground">Company: {employee.company || 'N/A'}</div>
                        <div className="text-sm text-muted-foreground">Date of Birth: {employee.dateofbirth || 'N/A'}</div>
                        <div className="text-sm text-muted-foreground">Gender: {employee.gender || 'N/A'}</div>
                        <div className="text-sm text-muted-foreground">Phone: {employee.phone1 || 'N/A'}</div>
                        <div className="flex gap-2">
                          <Button variant="outline" size="sm" asChild>
                            <Link href={route("employeeportal.show", employee.staffid)} aria-label="View employee profile">
                              <Eye className="h-4 w-4 text-blue-500" />
                            </Link>
                          </Button>
                          <Button variant="outline" size="sm" onClick={() => handleEdit(employee)} title="Edit Employee">
                            <Pencil className="h-4 w-4" />
                          </Button>
                          <Button variant="outline" size="sm" onClick={() => handleArchive(employee)} className="text-amber-600 hover:text-amber-700 hover:bg-amber-50" title="Archive Employee">
                            <Archive className="h-4 w-4" />
                          </Button>
                          <Button variant="destructive" size="sm" onClick={() => handleDelete(employee.staffid)} title="Delete Employee">
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
                            checked={selectedIds.length === employees.data.length && employees.data.length > 0}
                            onCheckedChange={handleSelectAll}
                          />
                        </TableHead>
                        <TableHead>View</TableHead>
                        <TableHead>
                          <Button variant="ghost" onClick={() => handleSort("staffid")} className="flex items-center gap-1">
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
                          <Button variant="ghost" onClick={() => handleSort("company")} className="flex items-center gap-1">
                            Company
                            <ArrowUpDown className="h-4 w-4" />
                          </Button>
                        </TableHead>
                        <TableHead>
                          <Button variant="ghost" onClick={() => handleSort("dateofbirth")} className="flex items-center gap-1">
                            Date of Birth
                            <ArrowUpDown className="h-4 w-4" />
                          </Button>
                        </TableHead>
                        <TableHead>
                          <Button variant="ghost" onClick={() => handleSort("gender")} className="flex items-center gap-1">
                            Gender
                            <ArrowUpDown className="h-4 w-4" />
                          </Button>
                        </TableHead>
                        <TableHead>
                          <Button variant="ghost" onClick={() => handleSort("phone1")} className="flex items-center gap-1">
                            Phone
                            <ArrowUpDown className="h-4 w-4" />
                          </Button>
                        </TableHead>
                        <TableHead className="text-right">Actions</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {employees.data.map((employee) => (
                        <TableRow key={employee.staffid}>
                          <TableCell>
                            <Checkbox
                              checked={selectedIds.includes(employee.staffid)}
                              onCheckedChange={(checked) => handleSelect(employee.staffid, checked as boolean)}
                            />
                          </TableCell>
                          <TableCell>
                            <Button variant="outline" size="sm" asChild>
                              <Link href={route("employeeportal.show", employee.staffid)} aria-label="View employee profile">
                                <Eye className="h-4 w-4 text-blue-500" />
                              </Link>
                            </Button>
                          </TableCell>
                          <TableCell>{employee.staffid}</TableCell>
                          <TableCell>{employee.employeename}</TableCell>
                          <TableCell>{employee.company || 'N/A'}</TableCell>
                          <TableCell>{employee.dateofbirth || 'N/A'}</TableCell>
                          <TableCell>{employee.gender || 'N/A'}</TableCell>
                          <TableCell>{employee.phone1 || 'N/A'}</TableCell>
                          <TableCell className="text-right">
                            <div className="flex gap-2 justify-end">
                              <Button variant="outline" size="sm" onClick={() => handleEdit(employee)} title="Edit Employee">
                                <Pencil className="h-4 w-4" />
                              </Button>
                              <Button variant="outline" size="sm" onClick={() => handleArchive(employee)} className="text-amber-600 hover:text-amber-700 hover:bg-amber-50" title="Archive Employee">
                                <Archive className="h-4 w-4" />
                              </Button>
                              <Button variant="destructive" size="sm" onClick={() => handleDelete(employee.staffid)} title="Delete Employee">
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
                    {employees.links.map((link, index) => (
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
                <p className="text-center text-gray-500">No employees found. Create a new employee to get started!</p>
              </CardContent>
            </Card>
          )}

          <Dialog open={isArchiveOpen} onOpenChange={setIsArchiveOpen}>
            <DialogContent className="p-4 sm:max-w-md w-full">
              <DialogHeader>
                <DialogTitle>Confirm Archiving</DialogTitle>
              </DialogHeader>
              <p>
                Are you sure you want to archive <strong>{archiveEmployee?.employeename}</strong> ({archiveEmployee?.staffid})? 
                This will move their record to the Archived Staff section.
              </p>
              <DialogFooter className="flex flex-col sm:flex-row sm:justify-end gap-2">
                <Button variant="outline" onClick={() => setIsArchiveOpen(false)}>
                  Cancel
                </Button>
                <Button className="bg-amber-600 hover:bg-amber-700 text-white" onClick={confirmArchive}>
                  Archive Employee
                </Button>
              </DialogFooter>
            </DialogContent>
          </Dialog>

          <Dialog open={isBulkArchiveOpen} onOpenChange={setIsBulkArchiveOpen}>
            <DialogContent className="p-4 sm:max-w-md w-full">
              <DialogHeader>
                <DialogTitle>Confirm Bulk Archiving</DialogTitle>
              </DialogHeader>
              <p>
                Are you sure you want to archive {selectedIds.length} selected employee(s)? 
                Their records will be moved to the Archived Staff section.
              </p>
              <DialogFooter className="flex flex-col sm:flex-row sm:justify-end gap-2">
                <Button variant="outline" onClick={() => setIsBulkArchiveOpen(false)}>
                  Cancel
                </Button>
                <Button className="bg-amber-600 hover:bg-amber-700 text-white" onClick={confirmBulkArchive} disabled={bulkArchiveForm.processing}>
                  Archive Selected
                </Button>
              </DialogFooter>
            </DialogContent>
          </Dialog>

          <Dialog open={isDeleteOpen} onOpenChange={setIsDeleteOpen}>
            <DialogContent className="p-4 sm:max-w-md w-full">
              <DialogHeader>
                <DialogTitle>Confirm Deletion</DialogTitle>
              </DialogHeader>
              <p>Are you sure you want to delete this employee? This action cannot be undone.</p>
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
              <p>Are you sure you want to delete {selectedIds.length} employee(s)? This action cannot be undone.</p>
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

