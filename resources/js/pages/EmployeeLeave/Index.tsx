import { Head, Link, useForm } from "@inertiajs/react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { CheckCircle2, XCircle, Pencil, Trash, Plus, Building2, Search, Loader2 } from "lucide-react";
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
  company?: string | null;
  type: string | null;
  date_start: string | null;
  date_end: string | null;
  reason: string | null;
  status: number | string;
  managername: string | null;
  phone: string | null;
}

interface ClientOption {
  id: number;
  client_name: string;
  short_name: string;
  client_dbname?: string;
}

interface Props {
  leaveApplications: {
    data: LeaveApplication[];
    links: any[];
  };
  clients?: ClientOption[];
  filters: {
    search: string;
    sort: string;
    order: string;
    ida_filter?: string;
    client?: string;
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

export default function EmployeeLeaveIndex({ leaveApplications, clients = [], filters, leaveStats, flash }: Props) {
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
  const [selectedClient, setSelectedClient] = useState(filters.client || "all");
  const [isLookingUp, setIsLookingUp] = useState(false);
  const [lookupFeedback, setLookupFeedback] = useState<string | null>(null);

  const { data, setData, post, put, delete: destroy, processing, reset, errors } = useForm({
    ida: "",
    name: "",
    company: "",
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

  const isPending = (status: number | string) => {
    const s = String(status).toLowerCase();
    return s === "0" || s === "pending";
  };

  const handleLookupStaff = async (staffIdToLookup?: string) => {
    const id = (staffIdToLookup || data.ida || "").trim();
    if (!id) return;

    setIsLookingUp(true);
    setLookupFeedback(null);
    try {
      const res = await fetch(route("employee-leaves.lookup", encodeURIComponent(id)));
      if (res.ok) {
        const result = await res.json();
        if (result.found) {
          setData((prev) => ({
            ...prev,
            name: result.name || prev.name,
            company: result.company || prev.company,
            phone: result.phone || prev.phone,
          }));
          setLookupFeedback(`Found: ${result.name} (${result.company || 'No Company'})`);
        } else {
          setLookupFeedback("Staff ID not found in database. You can manually enter details.");
        }
      }
    } catch (e) {
      console.error("Lookup error:", e);
    } finally {
      setIsLookingUp(false);
    }
  };

  const handleSubmit = (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    if (editingLeave) {
      put(route("employee-leaves.update", editingLeave.id), {
        onSuccess: () => {
          setIsOpen(false);
          reset();
          setEditingLeave(null);
          setLookupFeedback(null);
        },
      });
    } else {
      post(route("employee-leaves.store"), {
        onSuccess: () => {
          setIsOpen(false);
          reset();
          setLookupFeedback(null);
        },
      });
    }
  };

  const handleEdit = (leave: LeaveApplication) => {
    if (!isPending(leave.status)) return;
    setEditingLeave(leave);
    setData({
      ida: leave.ida || "",
      name: leave.name || "",
      company: leave.company || "",
      type: leave.type || "",
      date_start: leave.date_start || "",
      date_end: leave.date_end || "",
      reason: leave.reason || "",
      managername: leave.managername || "",
      phone: leave.phone || "",
    });
    setLookupFeedback(null);
    setIsOpen(true);
  };

  const handleDelete = (id: number) => {
    const leave = leaveApplications.data.find((app) => app.id === id);
    if (leave && isPending(leave.status)) {
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

  const handleSearchSort = (customParams: Record<string, any> = {}) => {
    post(route("employee-leaves.index", {
      search,
      sort,
      order,
      ida_filter: idaFilter,
      client: selectedClient,
      ...customParams,
    }), {
      preserveState: true,
      preserveScroll: true,
    });
  };

  const getStatusBadge = (status: number | string) => {
    const s = String(status).toLowerCase();
    switch (s) {
      case "0":
      case "pending":
        return <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">Pending</span>;
      case "1":
      case "approved":
        return <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">Approved</span>;
      case "2":
      case "denied":
        return <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300">Denied</span>;
      case "3":
      case "cancelled":
        return <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300">Cancelled</span>;
      default:
        return <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-gray-100 text-gray-800">Unknown</span>;
    }
  };

  const formatDate = (date: string | null) => {
    if (!date) return "N/A";
    return new Date(date).toLocaleDateString("en-GB", {
      day: "numeric",
      month: "short",
      year: "numeric",
    });
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
            <div className="flex flex-col xl:flex-row xl:items-center xl:justify-between gap-4">
              <div>
                <h1 className="text-2xl font-bold tracking-tight">Leave Applications</h1>
                <p className="text-sm text-muted-foreground">Submit and track leave applications for staff across managed clients & companies.</p>
              </div>

              <div className="flex flex-wrap items-center gap-2">
                <ShadcnInput
                  placeholder="Search type, reason, staff ID..."
                  value={search}
                  onChange={(e) => setSearch(e.target.value)}
                  onKeyUp={(e) => e.key === "Enter" && handleSearchSort()}
                  className="w-full sm:w-56"
                />

                <ShadcnInput
                  placeholder="Filter by Staff ID"
                  value={idaFilter}
                  onChange={(e) => setIdaFilter(e.target.value)}
                  onKeyUp={(e) => e.key === "Enter" && handleSearchSort()}
                  className="w-full sm:w-40"
                />

                {/* Client Filter */}
                <Select
                  value={selectedClient}
                  onValueChange={(val) => {
                    setSelectedClient(val);
                    handleSearchSort({ client: val });
                  }}
                >
                  <SelectTrigger className="w-full sm:w-48">
                    <Building2 className="h-4 w-4 mr-1.5 opacity-60" />
                    <SelectValue placeholder="All Clients" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="all">All Clients / Companies</SelectItem>
                    {clients.map((c) => (
                      <SelectItem key={c.id} value={c.client_name}>
                        {c.short_name || c.client_name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>

                {/* Sort */}
                <Select
                  value={`${sort}:${order}`}
                  onValueChange={(value) => {
                    const [s, o] = value.split(":");
                    setSort(s);
                    setOrder(o);
                    handleSearchSort({ sort: s, order: o });
                  }}
                >
                  <SelectTrigger className="w-full sm:w-44">
                    <SelectValue placeholder="Sort by" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="date_start:desc">Start Date (Newest)</SelectItem>
                    <SelectItem value="date_start:asc">Start Date (Oldest)</SelectItem>
                    <SelectItem value="ida:asc">Staff ID (A-Z)</SelectItem>
                    <SelectItem value="name:asc">Name (A-Z)</SelectItem>
                    <SelectItem value="company:asc">Company (A-Z)</SelectItem>
                    <SelectItem value="type:asc">Leave Type (A-Z)</SelectItem>
                    <SelectItem value="status:asc">Status</SelectItem>
                  </SelectContent>
                </Select>

                <Dialog
                  open={isOpen}
                  onOpenChange={(open) => {
                    setIsOpen(open);
                    if (!open) {
                      reset();
                      setEditingLeave(null);
                      setLookupFeedback(null);
                    }
                  }}
                >
                  <DialogTrigger asChild>
                    <Button className="w-full sm:w-auto bg-primary text-primary-foreground">
                      <Plus className="h-4 w-4 mr-1.5" />
                      Apply for Leave
                    </Button>
                  </DialogTrigger>
                  <DialogContent className="p-6 sm:max-w-2xl w-full max-h-[85vh] overflow-y-auto">
                    <DialogHeader>
                      <DialogTitle>{editingLeave ? "Edit Leave Application" : "Apply for Leave"}</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={handleSubmit}>
                      <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 py-4">
                        {/* Staff ID with Lookup */}
                        <div className="grid gap-2">
                          <Label htmlFor="ida">Staff ID</Label>
                          <div className="flex gap-2">
                            <Input
                              id="ida"
                              value={data.ida}
                              onChange={(e) => setData("ida", e.target.value)}
                              onBlur={() => {
                                if (data.ida && !data.name) {
                                  handleLookupStaff(data.ida);
                                }
                              }}
                              placeholder="e.g. SBP9102"
                              required
                              className="w-full"
                            />
                            <Button
                              type="button"
                              variant="outline"
                              size="sm"
                              onClick={() => handleLookupStaff()}
                              disabled={isLookingUp || !data.ida}
                              title="Lookup Staff details"
                            >
                              {isLookingUp ? <Loader2 className="h-4 w-4 animate-spin" /> : <Search className="h-4 w-4" />}
                            </Button>
                          </div>
                          {lookupFeedback && (
                            <p className="text-xs text-muted-foreground">{lookupFeedback}</p>
                          )}
                          {errors.ida && <p className="text-red-500 text-sm">{errors.ida}</p>}
                        </div>

                        {/* Employee Name */}
                        <div className="grid gap-2">
                          <Label htmlFor="name">Employee Name</Label>
                          <Input
                            id="name"
                            value={data.name}
                            onChange={(e) => setData("name", e.target.value)}
                            required
                            placeholder="Full Name"
                            className="w-full"
                          />
                          {errors.name && <p className="text-red-500 text-sm">{errors.name}</p>}
                        </div>

                        {/* Company / Client Selection */}
                        <div className="grid gap-2">
                          <Label htmlFor="company">Company / Client</Label>
                          {clients.length > 0 ? (
                            <Select
                              value={data.company}
                              onValueChange={(value) => setData("company", value)}
                            >
                              <SelectTrigger className="w-full">
                                <SelectValue placeholder="Select Company / Client" />
                              </SelectTrigger>
                              <SelectContent>
                                {clients.map((c) => (
                                  <SelectItem key={c.id} value={c.client_name}>
                                    {c.short_name || c.client_name}
                                  </SelectItem>
                                ))}
                              </SelectContent>
                            </Select>
                          ) : (
                            <Input
                              id="company"
                              value={data.company}
                              onChange={(e) => setData("company", e.target.value)}
                              placeholder="e.g. SBP AFRICA LTD."
                              className="w-full"
                            />
                          )}
                          {errors.company && <p className="text-red-500 text-sm">{errors.company}</p>}
                        </div>

                        {/* Leave Type */}
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
                              <SelectItem value="Casual">Casual</SelectItem>
                              <SelectItem value="Study">Study</SelectItem>
                              <SelectItem value="Other">Other</SelectItem>
                            </SelectContent>
                          </Select>
                          {errors.type && <p className="text-red-500 text-sm">{errors.type}</p>}
                        </div>

                        {/* Dates */}
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

                        {/* Manager info */}
                        <div className="grid gap-2">
                          <Label htmlFor="managername">Manager Name</Label>
                          <Input
                            id="managername"
                            value={data.managername}
                            onChange={(e) => setData("managername", e.target.value)}
                            required
                            placeholder="Manager's Full Name"
                            className="w-full"
                          />
                          {errors.managername && <p className="text-red-500 text-sm">{errors.managername}</p>}
                        </div>
                        <div className="grid gap-2">
                          <Label htmlFor="phone">Manager Phone Number (for SMS Review)</Label>
                          <Input
                            id="phone"
                            value={data.phone}
                            onChange={(e) => setData("phone", e.target.value)}
                            required
                            placeholder="0XXXXXXXXX (10 digits)"
                            className="w-full"
                          />
                          {errors.phone && <p className="text-red-500 text-sm">{errors.phone}</p>}
                        </div>

                        {/* Reason */}
                        <div className="grid gap-2 sm:col-span-2">
                          <Label htmlFor="reason">Reason for Leave</Label>
                          <Textarea
                            id="reason"
                            value={data.reason}
                            onChange={(e) => setData("reason", e.target.value)}
                            required
                            placeholder="Provide reason for leave application..."
                            className="w-full"
                          />
                          {errors.reason && <p className="text-red-500 text-sm">{errors.reason}</p>}
                        </div>
                      </div>
                      <DialogFooter className="flex flex-col sm:flex-row sm:justify-end gap-2">
                        <Button type="button" variant="outline" onClick={() => setIsOpen(false)}>
                          Cancel
                        </Button>
                        <Button type="submit" disabled={processing}>
                          {editingLeave ? "Update Application" : "Submit Leave Application"}
                        </Button>
                      </DialogFooter>
                    </form>
                  </DialogContent>
                </Dialog>
              </div>
            </div>

            {/* Leave Entitlement Statistics */}
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
              <Card className="bg-blue-600 text-white">
                <CardHeader className="pb-2">
                  <CardTitle className="text-sm font-medium text-blue-100">Annual Entitlement</CardTitle>
                </CardHeader>
                <CardContent>
                  <p className="text-3xl font-bold">{leaveStats.entitlement} days</p>
                </CardContent>
              </Card>
              <Card className="bg-emerald-600 text-white">
                <CardHeader className="pb-2">
                  <CardTitle className="text-sm font-medium text-emerald-100">Used Leave</CardTitle>
                </CardHeader>
                <CardContent>
                  <p className="text-3xl font-bold">{leaveStats.used} days</p>
                </CardContent>
              </Card>
              <Card className={leaveStats.remaining === 0 ? "bg-rose-600 text-white" : "bg-slate-700 text-white"}>
                <CardHeader className="pb-2">
                  <CardTitle className="text-sm font-medium text-slate-200">Remaining Leave</CardTitle>
                </CardHeader>
                <CardContent>
                  <p className="text-3xl font-bold">{leaveStats.remaining} days</p>
                </CardContent>
              </Card>
              <Card className="bg-amber-500 text-white">
                <CardHeader className="pb-2">
                  <CardTitle className="text-sm font-medium text-amber-100">Pending Leave Days</CardTitle>
                </CardHeader>
                <CardContent>
                  <p className="text-3xl font-bold">{leaveStats.pending} days</p>
                </CardContent>
              </Card>
            </div>
          </div>

          {leaveApplications.data.length > 0 ? (
            <Card>
              <CardHeader className="py-3 flex flex-row items-center justify-between">
                <CardTitle className="text-base font-semibold">Leave Applications</CardTitle>
                <div className="text-xs text-muted-foreground">{leaveApplications.data.length} records on this page</div>
              </CardHeader>
              <CardContent className="p-0 sm:p-4">
                {/* Mobile View */}
                <div className="block md:hidden space-y-3 p-3">
                  {leaveApplications.data.map((application) => (
                    <div key={application.id} className="border rounded-lg p-3 bg-card space-y-2">
                      <div className="flex items-center justify-between">
                        <span className="font-semibold">{application.ida}</span>
                        <div>{getStatusBadge(application.status)}</div>
                      </div>
                      <div className="text-sm"><span className="text-muted-foreground">Name:</span> <span className="font-medium">{application.name || "N/A"}</span></div>
                      <div className="text-sm"><span className="text-muted-foreground">Company:</span> <span className="font-semibold text-primary">{application.company || "N/A"}</span></div>
                      <div className="text-sm"><span className="text-muted-foreground">Type:</span> {application.type || "N/A"}</div>
                      <div className="text-xs text-muted-foreground">Period: {formatDate(application.date_start)} — {formatDate(application.date_end)}</div>
                      <div className="text-xs text-muted-foreground">Reason: {application.reason || "N/A"}</div>
                      <div className="text-xs text-muted-foreground">Manager: {application.managername || "N/A"} ({application.phone || "N/A"})</div>
                      {isPending(application.status) && (
                        <div className="flex gap-2 pt-2 border-t">
                          <Button variant="outline" size="sm" className="flex-1" onClick={() => handleEdit(application)}>
                            <Pencil className="h-4 w-4 mr-1" /> Edit
                          </Button>
                          <Button variant="destructive" size="sm" className="flex-1" onClick={() => handleDelete(application.id)}>
                            <Trash className="h-4 w-4 mr-1" /> Delete
                          </Button>
                        </div>
                      )}
                    </div>
                  ))}
                </div>

                {/* Desktop View */}
                <div className="hidden md:block">
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>Staff ID</TableHead>
                        <TableHead>Name</TableHead>
                        <TableHead>Company / Client</TableHead>
                        <TableHead>Leave Type</TableHead>
                        <TableHead>Start Date</TableHead>
                        <TableHead>End Date</TableHead>
                        <TableHead>Reason</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead>Manager</TableHead>
                        <TableHead className="text-right">Actions</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {leaveApplications.data.map((application) => (
                        <TableRow key={application.id}>
                          <TableCell className="font-medium">{application.ida}</TableCell>
                          <TableCell>{application.name || "N/A"}</TableCell>
                          <TableCell>
                            <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-muted text-foreground border">
                              {application.company || "N/A"}
                            </span>
                          </TableCell>
                          <TableCell>{application.type || "N/A"}</TableCell>
                          <TableCell>{formatDate(application.date_start)}</TableCell>
                          <TableCell>{formatDate(application.date_end)}</TableCell>
                          <TableCell className="max-w-[200px] truncate" title={application.reason || ""}>
                            {application.reason || "N/A"}
                          </TableCell>
                          <TableCell>{getStatusBadge(application.status)}</TableCell>
                          <TableCell>
                            <div className="text-xs">
                              <div>{application.managername || "N/A"}</div>
                              <div className="text-muted-foreground">{application.phone || ""}</div>
                            </div>
                          </TableCell>
                          <TableCell className="text-right">
                            {isPending(application.status) && (
                              <div className="flex gap-1.5 justify-end">
                                <Button
                                  variant="outline"
                                  size="sm"
                                  className="h-8 px-2"
                                  onClick={() => handleEdit(application)}
                                  title="Edit Application"
                                >
                                  <Pencil className="h-4 w-4" />
                                </Button>
                                <Button
                                  variant="destructive"
                                  size="sm"
                                  className="h-8 px-2"
                                  onClick={() => handleDelete(application.id)}
                                  title="Delete Application"
                                >
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

                {/* Pagination */}
                <div className="flex justify-end p-4 border-t">
                  <div className="flex gap-1.5">
                    {leaveApplications.links.map((link, index) => (
                      <Button
                        key={index}
                        variant={link.active ? "default" : "outline"}
                        size="sm"
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
              <CardContent className="py-12 text-center text-muted-foreground">
                <p>No leave applications found.</p>
              </CardContent>
            </Card>
          )}

          {/* Delete Confirmation Dialog */}
          <Dialog open={isDeleteOpen} onOpenChange={setIsDeleteOpen}>
            <DialogContent>
              <DialogHeader>
                <DialogTitle>Confirm Delete</DialogTitle>
              </DialogHeader>
              <p>Are you sure you want to delete this pending leave application? This action cannot be undone.</p>
              <DialogFooter>
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
