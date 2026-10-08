import { Head, Link, useForm } from "@inertiajs/react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { CheckCircle2, XCircle, Pencil, Trash, Plus } from "lucide-react";
import AppLayout from "@/layouts/app-layout";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger, DialogFooter } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Label } from "@/components/ui/label";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { useState, useEffect } from "react";
import { Input as ShadcnInput } from "@/components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import ErrorBoundary from "@/components/ErrorBoundary";

interface LeaveApplication {
  id: number;
  ida: string;
  name: string | null;
  type: string | null;
  date_start: string | null;
  date_end: string | null;
  reason: string | null;
  status: number;
  managername: string | null;
  phone: string | null;
}

interface Props {
  leaveApplications: {
    data: LeaveApplication[];
    links: any[];
  };
  filters: {
    search: string;
    sort: string;
    order: string;
    ida_filter?: string;
  };
  leaveStats: {
    entitlement: number;
    used: number;
    remaining: number;
    pending: number;
  };
  flash?: {
    success?: string;
    error?: string;
  };
}

const breadcrumbs = [
  { title: "Leave Applications", href: "/employee-leaves" },
];

export default function EmployeeLeaveIndex({ leaveApplications, filters, leaveStats, flash }: Props) {
  console.log('Props:', { leaveApplications, filters, leaveStats, flash });

  const [isOpen, setIsOpen] = useState(false);
  const [editingLeave, setEditingLeave] = useState<LeaveApplication | null>(null);
  const [isDeleteOpen, setIsDeleteOpen] = useState(false);
  const [deleteId, setDeleteId] = useState<number | null>(null);
  const [showToast, setShowToast] = useState(false);
  const [toastMessage, setToastMessage] = useState("");
  const [toastType, setToastType] = useState<"success" | "error">("success");
  const [search, setSearch] = useState(filters.search || "");
  const [sort, setSort] = useState(filters.sort || "date_start");
  const [order, setOrder] = useState(filters.order || "desc");
  const [idaFilter, setIdaFilter] = useState(filters.ida_filter || "");

  const { data, setData, post, put, delete: destroy, processing, reset, errors } = useForm({
    ida: "",
    name: "",
    type: "",
    date_start: "",
    date_end: "",
    reason: "",
    managername: "",
    phone: "",
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
    if (Object.keys(errors).length > 0) {
      const errorMessage = Object.values(errors).join(' | ');
      setToastMessage(errorMessage);
      setToastType("error");
      setShowToast(true);
    }
  }, [errors]);

  useEffect(() => {
    if (showToast) {
      const duration = toastType === "error" ? 60000 : 3000;
      const timer = setTimeout(() => setShowToast(false), duration);
      return () => clearTimeout(timer);
    }
  }, [showToast, toastType]);

  const handleSubmit = (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    if (editingLeave) {
      put(route("employee-leaves.update", editingLeave.id), {
        onSuccess: () => {
          setIsOpen(false);
          reset();
          setEditingLeave(null);
        },
      });
    } else {
      post(route("employee-leaves.store"), {
        onSuccess: () => {
          setIsOpen(false);
          reset();
        },
      });
    }
  };

  const handleEdit = (leave: LeaveApplication) => {
    if (leave.status !== 0) return;
    setEditingLeave(leave);
    setData({
      ida: leave.ida || "",
      name: leave.name || "",
      type: leave.type || "",
      date_start: leave.date_start || "",
      date_end: leave.date_end || "",
      reason: leave.reason || "",
      managername: leave.managername || "",
      phone: leave.phone || "",
    });
    setIsOpen(true);
  };

  const handleDelete = (id: number) => {
    const leave = leaveApplications.data.find((app) => app.id === id);
    if (leave && leave.status === 0) {
      setDeleteId(id);
      setIsDeleteOpen(true);
    }
  };

  const confirmDelete = () => {
    if (deleteId) {
      destroy(route("employee-leaves.destroy", deleteId), {
        onSuccess: () => {
          setIsDeleteOpen(false);
          setDeleteId(null);
        },
      });
    }
  };

  const handleSearchSort = () => {
    post(route("employee-leaves.index", { search, sort, order, ida_filter: idaFilter }), {
      preserveState: true,
      preserveScroll: true,
    });
  };

  const getStatusBadge = (status: number) => {
    switch (status) {
      case 0:
        return <span className="badge bg-yellow-500 text-black">Pending</span>;
      case 1:
        return <span className="badge bg-green-500 text-white">Approved</span>;
      case 2:
        return <span className="badge bg-red-500 text-white">Denied</span>;
      case 3:
        return <span className="badge bg-gray-500 text-white">Cancelled</span>;
      default:
        return <span className="badge bg-gray-500 text-white">Unknown</span>;
    }
  };

  return (
    <ErrorBoundary>
      <AppLayout breadcrumbs={breadcrumbs}>
        <Head title="Leave Applications" />
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
              <h1 className="text-2xl font-bold">Leave Applications</h1>
              <div className="flex flex-col sm:flex-row gap-4">
                <ShadcnInput
                  placeholder="Search by leave type, reason, staff ID, or name..."
                  value={search}
                  onChange={(e) => setSearch(e.target.value)}
                  onKeyUp={handleSearchSort}
                  className="w-full sm:w-64"
                />
                <ShadcnInput
                  placeholder="Filter by Staff ID"
                  value={idaFilter}
                  onChange={(e) => setIdaFilter(e.target.value)}
                  onKeyUp={handleSearchSort}
                  className="w-full sm:w-48"
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
                    <SelectItem value="ida:asc">Staff ID (A-Z)</SelectItem>
                    <SelectItem value="ida:desc">Staff ID (Z-A)</SelectItem>
                    <SelectItem value="name:asc">Name (A-Z)</SelectItem>
                    <SelectItem value="name:desc">Name (Z-A)</SelectItem>
                    <SelectItem value="type:asc">Leave Type (A-Z)</SelectItem>
                    <SelectItem value="type:desc">Leave Type (Z-A)</SelectItem>
                    <SelectItem value="date_start:asc">Start Date (Oldest)</SelectItem>
                    <SelectItem value="date_start:desc">Start Date (Newest)</SelectItem>
                    <SelectItem value="status:asc">Status (A-Z)</SelectItem>
                    <SelectItem value="status:desc">Status (Z-A)</SelectItem>
                  </SelectContent>
                </Select>
                <Dialog
                  open={isOpen}
                  onOpenChange={(open) => {
                    setIsOpen(open);
                    if (!open) {
                      reset();
                      setEditingLeave(null);
                    }
                  }}
                >
                  <DialogTrigger asChild>
                    <Button className="w-full sm:w-auto">
                      <Plus className="h-4 w-4 mr-2" />
                      Apply for Leave
                    </Button>
                  </DialogTrigger>
                  <DialogContent className="p-6 sm:max-w-2xl w-full max-h-[80vh] overflow-y-auto">
                    <DialogHeader>
                      <DialogTitle>{editingLeave ? "Edit Leave Application" : "Apply for Leave"}</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={handleSubmit}>
                      <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 py-4">
                        <div className="grid gap-2">
                          <Label htmlFor="ida">Staff ID</Label>
                          <Input
                            id="ida"
                            value={data.ida}
                            onChange={(e) => setData("ida", e.target.value)}
                            required
                            className="w-full"
                          />
                          {errors.ida && <p className="text-red-500 text-sm">{errors.ida}</p>}
                        </div>
                        <div className="grid gap-2">
                          <Label htmlFor="name">Employee Name</Label>
                          <Input
                            id="name"
                            value={data.name}
                            onChange={(e) => setData("name", e.target.value)}
                            required
                            className="w-full"
                          />
                          {errors.name && <p className="text-red-500 text-sm">{errors.name}</p>}
                        </div>
                        <div className="grid gap-2">
                          <Label htmlFor="type">Leave Type</Label>
                          <Select
                            value={data.type}
                            onValueChange={(value) => setData("type", value)}
                          >
                            <SelectTrigger className="w-full">
                              <SelectValue placeholder="Select leave type" />
                            </SelectTrigger>
                            <SelectContent>
                              <SelectItem value="Annual">Annual</SelectItem>
                              <SelectItem value="Sick">Sick</SelectItem>
                              <SelectItem value="Maternity">Maternity</SelectItem>
                              <SelectItem value="Paternity">Paternity</SelectItem>
                              <SelectItem value="Other">Other</SelectItem>
                            </SelectContent>
                          </Select>
                          {errors.type && <p className="text-red-500 text-sm">{errors.type}</p>}
                        </div>
                        <div className="grid gap-2">
                          <Label htmlFor="date_start">Start Date</Label>
                          <Input
                            id="date_start"
                            type="date"
                            value={data.date_start}
                            onChange={(e) => setData("date_start", e.target.value)}
                            required
                            className="w-full"
                          />
                          {errors.date_start && <p className="text-red-500 text-sm">{errors.date_start}</p>}
                        </div>
                        <div className="grid gap-2">
                          <Label htmlFor="date_end">End Date</Label>
                          <Input
                            id="date_end"
                            type="date"
                            value={data.date_end}
                            onChange={(e) => setData("date_end", e.target.value)}
                            required
                            className="w-full"
                          />
                          {errors.date_end && <p className="text-red-500 text-sm">{errors.date_end}</p>}
                        </div>
                        <div className="grid gap-2">
                          <Label htmlFor="managername">Manager Name</Label>
                          <Input
                            id="managername"
                            value={data.managername}
                            onChange={(e) => setData("managername", e.target.value)}
                            required
                            className="w-full"
                          />
                          {errors.managername && <p className="text-red-500 text-sm">{errors.managername}</p>}
                        </div>
                        <div className="grid gap-2">
                          <Label htmlFor="phone">Phone Number</Label>
                          <Input
                            id="phone"
                            value={data.phone}
                            onChange={(e) => setData("phone", e.target.value)}
                            required
                            className="w-full"
                          />
                          {errors.phone && <p className="text-red-500 text-sm">{errors.phone}</p>}
                        </div>
                        <div className="grid gap-2 sm:col-span-2">
                          <Label htmlFor="reason">Reason</Label>
                          <Textarea
                            id="reason"
                            value={data.reason}
                            onChange={(e) => setData("reason", e.target.value)}
                            required
                            className="w-full"
                          />
                          {errors.reason && <p className="text-red-500 text-sm">{errors.reason}</p>}
                        </div>
                      </div>
                      <DialogFooter className="flex flex-col sm:flex-row sm:justify-end gap-2">
                        <Button type="submit" disabled={processing}>
                          {editingLeave ? "Update" : "Submit"}
                        </Button>
                      </DialogFooter>
                    </form>
                  </DialogContent>
                </Dialog>
              </div>
            </div>
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
              <Card className="bg-blue-600 text-white">
                <CardHeader>
                  <CardTitle>Leave Entitlement</CardTitle>
                </CardHeader>
                <CardContent>
                  <p className="text-4xl font-bold">{leaveStats.entitlement} days</p>
                </CardContent>
              </Card>
              <Card className="bg-green-600 text-white">
                <CardHeader>
                  <CardTitle>Used Leave</CardTitle>
                </CardHeader>
                <CardContent>
                  <p className="text-4xl font-bold">{leaveStats.used} days</p>
                </CardContent>
              </Card>
              <Card className={leaveStats.remaining === 0 ? "bg-red-600 text-white" : "bg-gray-600 text-white"}>
                <CardHeader>
                  <CardTitle>Remaining Leave</CardTitle>
                </CardHeader>
                <CardContent>
                  <p className="text-4xl font-bold">{leaveStats.remaining} days</p>
                </CardContent>
              </Card>
              <Card className="bg-yellow-500 text-black sm:col-span-3">
                <CardHeader>
                  <CardTitle>Pending Leave</CardTitle>
                </CardHeader>
                <CardContent>
                  <p className="text-4xl font-bold">{leaveStats.pending} days</p>
                </CardContent>
              </Card>
            </div>
          </div>

          {leaveApplications.data.length > 0 ? (
            <Card>
              <CardHeader>
                <CardTitle>Leave Applications</CardTitle>
                <div className="text-sm text-muted-foreground">
                  {leaveApplications.data.length} records
                </div>
              </CardHeader>
              <CardContent>
                <div className="block md:hidden space-y-4">
                  {leaveApplications.data.map((application) => (
                    <div key={application.id} className="border rounded-lg p-4 bg-background">
                      <div className="flex flex-col gap-2">
                        <div className="font-semibold">{application.ida}</div>
                        <div className="text-sm text-muted-foreground">Name: {application.name || "N/A"}</div>
                        <div className="text-sm text-muted-foreground">Leave Type: {application.type || "N/A"}</div>
                        <div className="text-sm text-muted-foreground">Start Date: {application.date_start || "N/A"}</div>
                        <div className="text-sm text-muted-foreground">End Date: {application.date_end || "N/A"}</div>
                        <div className="text-sm text-muted-foreground">Reason: {application.reason || "N/A"}</div>
                        <div className="text-sm text-muted-foreground">Status: {getStatusBadge(application.status)}</div>
                        <div className="text-sm text-muted-foreground">Manager: {application.managername || "N/A"}</div>
                        <div className="text-sm text-muted-foreground">Phone: {application.phone || "N/A"}</div>
                        {application.status === 0 && (
                          <div className="flex gap-2">
                            <Button variant="outline" size="sm" onClick={() => handleEdit(application)}>
                              <Pencil className="h-4 w-4" />
                            </Button>
                            <Button variant="destructive" size="sm" onClick={() => handleDelete(application.id)}>
                              <Trash className="h-4 w-4" />
                            </Button>
                          </div>
                        )}
                      </div>
                    </div>
                  ))}
                </div>
                <div className="hidden md:block">
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>Staff ID</TableHead>
                        <TableHead>Name</TableHead>
                        <TableHead>Leave Type</TableHead>
                        <TableHead>Start Date</TableHead>
                        <TableHead>End Date</TableHead>
                        <TableHead>Reason</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead>Manager</TableHead>
                        <TableHead>Phone</TableHead>
                        <TableHead className="text-right">Actions</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {leaveApplications.data.map((application) => (
                        <TableRow key={application.id}>
                          <TableCell>{application.ida}</TableCell>
                          <TableCell>{application.name || "N/A"}</TableCell>
                          <TableCell>{application.type || "N/A"}</TableCell>
                          <TableCell>{application.date_start || "N/A"}</TableCell>
                          <TableCell>{application.date_end || "N/A"}</TableCell>
                          <TableCell>{application.reason || "N/A"}</TableCell>
                          <TableCell>{getStatusBadge(application.status)}</TableCell>
                          <TableCell>{application.managername || "N/A"}</TableCell>
                          <TableCell>{application.phone || "N/A"}</TableCell>
                          <TableCell className="text-right">
                            {application.status === 0 && (
                              <div className="flex gap-2 justify-end">
                                <Button variant="outline" size="sm" onClick={() => handleEdit(application)}>
                                  <Pencil className="h-4 w-4" />
                                </Button>
                                <Button variant="destructive" size="sm" onClick={() => handleDelete(application.id)}>
                                  <Trash className="h-4 w-4" />
                                </Button>
                              </div>
                            )}
                          </TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                </div>
                <div className="flex justify-end mt-4">
                  <div className="flex gap-2">
                    {leaveApplications.links.map((link, index) => (
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
                <p className="text-center text-gray-500">No leave applications found. Apply for a leave to get started!</p>
              </CardContent>
            </Card>
          )}

          <Dialog open={isDeleteOpen} onOpenChange={setIsDeleteOpen}>
            <DialogContent className="p-4 sm:max-w-md w-full">
              <DialogHeader>
                <DialogTitle>Confirm Deletion</DialogTitle>
              </DialogHeader>
              <p>Are you sure you want to delete this leave application? This action cannot be undone.</p>
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
        </div>
      </AppLayout>
    </ErrorBoundary>
  );
}
