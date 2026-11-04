import AppLayout from "@/layouts/app-layout";
import { Head, router } from "@inertiajs/react";
import React, { useState, useCallback, useTransition } from "react";
import {
  Search,
  CreditCard,
  Users,
  CheckCircle2,
  XCircle,
  FileSpreadsheet,
  ChevronLeft,
  ChevronRight,
  RefreshCw,
  Building2,
  Edit2,
  Save,
  Check,
  AlertCircle,
  PlusCircle,
} from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
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
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
  DialogFooter,
} from "@/components/ui/dialog";

interface GhanaCardRecord {
  staff_id: string;
  name: string;
  company_shop: string;
  company?: string;
  ghana_card: string;
  ssnit_number: string;
  has_card?: number;
}

interface PaginatedRecords {
  data: GhanaCardRecord[];
  current_page: number;
  last_page: number;
  from: number;
  to: number;
  total: number;
  per_page: number;
  links: Array<{ url: string | null; label: string; active: boolean }>;
}

interface Props {
  records: PaginatedRecords;
  filters: { search: string; client: string; filter: string };
  clients: Array<{ value: string; label: string }>;
  totalRecords: number;
  summary: { total: number; with_card: number; without_card: number };
  flash?: { success?: string; error?: string };
}

export default function GhanaCardSearch({ records, filters, clients, totalRecords, summary, flash }: Props) {
  const [search, setSearch]                 = useState(filters.search || "");
  const [client, setClient]                 = useState(filters.client || "all");
  const [filterType, setFilterType]         = useState(filters.filter || "all");
  const [isLoading, setIsLoading]           = useState(false);
  const [tableData, setTableData]           = useState<GhanaCardRecord[]>(records.data);

  // Edit Modal State
  const [editingStaff, setEditingStaff]     = useState<GhanaCardRecord | null>(null);
  const [editCardNumber, setEditCardNumber] = useState("");
  const [editSsnitNumber, setEditSsnitNumber] = useState("");
  const [isSaving, setIsSaving]             = useState(false);
  const [saveSuccess, setSaveSuccess]       = useState<string | null>(null);
  const [saveError, setSaveError]           = useState<string | null>(null);

  // Sync tableData whenever records change
  React.useEffect(() => {
    setTableData(records.data);
  }, [records.data]);

  const applyFilters = useCallback(
    (overrides: Partial<typeof filters> = {}) => {
      setIsLoading(true);
      router.get(
        "/ghana-card",
        {
          search:   overrides.search   !== undefined ? overrides.search   : search,
          client:   overrides.client   !== undefined ? overrides.client   : client,
          filter:   overrides.filter   !== undefined ? overrides.filter   : filterType,
          per_page: records.per_page,
        },
        {
          preserveState: true,
          preserveScroll: true,
          onFinish: () => setIsLoading(false),
        }
      );
    },
    [search, client, filterType, records.per_page]
  );

  const handleSearchKey = (e: React.KeyboardEvent<HTMLInputElement>) => {
    if (e.key === "Enter") applyFilters({ search });
  };

  const handleClientChange = (val: string) => {
    setClient(val);
    applyFilters({ client: val });
  };

  const handleFilterChange = (val: string) => {
    setFilterType(val);
    applyFilters({ filter: val });
  };

  const handleReset = () => {
    setSearch("");
    setClient("all");
    setFilterType("all");
    router.get("/ghana-card", {}, { preserveState: false });
  };

  const handleExport = () => {
    const params = new URLSearchParams({ search, client, filter: filterType });
    window.location.href = `/ghana-card/export?${params.toString()}`;
  };

  // Open Edit Modal
  const openEditModal = (staff: GhanaCardRecord) => {
    setEditingStaff(staff);
    setEditCardNumber(staff.ghana_card || "");
    setEditSsnitNumber(staff.ssnit_number || "");
    setSaveSuccess(null);
    setSaveError(null);
  };

  // Handle Save
  const handleSaveGhanaCard = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!editingStaff) return;

    setIsSaving(true);
    setSaveSuccess(null);
    setSaveError(null);

    const formattedCard = editCardNumber.trim().toUpperCase();
    const formattedSsnit = editSsnitNumber.trim().toUpperCase();

    try {
      const csrfToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || "";
      
      const response = await fetch("/ghana-card/update", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-CSRF-TOKEN": csrfToken,
          "Accept": "application/json",
        },
        body: JSON.stringify({
          staff_id: editingStaff.staff_id,
          ghana_card: formattedCard,
          ssnit_number: formattedSsnit,
        }),
      });

      const result = await response.json();

      if (response.ok && result.success) {
        setSaveSuccess(result.message || "Updated successfully");

        // Update row in local state immediately
        setTableData((prev) =>
          prev.map((item) =>
            item.staff_id === editingStaff.staff_id
              ? {
                  ...item,
                  ghana_card: formattedCard,
                  ssnit_number: formattedSsnit,
                  has_card: formattedCard ? 1 : 0,
                }
              : item
          )
        );

        setTimeout(() => {
          setEditingStaff(null);
          setSaveSuccess(null);
        }, 1200);
      } else {
        setSaveError(result.message || "Failed to update Ghana Card. Please try again.");
      }
    } catch (err: any) {
      setSaveError(err.message || "An unexpected error occurred while saving.");
    } finally {
      setIsSaving(false);
    }
  };

  const hasCard = (card: string) => card && card !== "" && card.toLowerCase() !== "null";

  return (
    <AppLayout>
      <Head title="Ghana Card Search & Update" />

      <div className="p-6 space-y-6">
        {/* Flash Message */}
        {flash?.success && (
          <div className="p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg flex items-center gap-2 text-sm">
            <Check className="w-4 h-4 text-green-600" />
            {flash.success}
          </div>
        )}

        {/* Header */}
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div>
            <h1 className="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
              <CreditCard className="w-7 h-7 text-blue-600" />
              Ghana Card Search & Update
            </h1>
            <p className="text-sm text-gray-500 mt-1">
              Look up, verify, and update staff Ghana Card numbers and statutory SSNIT records
            </p>
          </div>
          <div className="flex items-center gap-2">
            <Button
              variant="outline"
              size="sm"
              onClick={handleExport}
              className="flex items-center gap-2"
            >
              <FileSpreadsheet className="w-4 h-4" />
              Export to Excel
            </Button>
          </div>
        </div>

        {/* Summary Metric Cards */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          <Card
            className={`cursor-pointer border-2 transition-all ${
              filterType === "all" ? "border-blue-500 shadow-sm" : "border-transparent"
            }`}
            onClick={() => handleFilterChange("all")}
          >
            <CardHeader className="pb-2">
              <CardTitle className="text-sm font-medium text-gray-500 flex items-center gap-2">
                <Users className="w-4 h-4 text-gray-600" /> Total Staff
              </CardTitle>
            </CardHeader>
            <CardContent>
              <p className="text-3xl font-bold text-gray-900 dark:text-white">
                {summary.total.toLocaleString()}
              </p>
              <p className="text-xs text-gray-400 mt-1">Click to view all staff</p>
            </CardContent>
          </Card>

          <Card
            className={`cursor-pointer border-2 transition-all ${
              filterType === "with_card" ? "border-green-500 shadow-sm" : "border-transparent"
            }`}
            onClick={() => handleFilterChange("with_card")}
          >
            <CardHeader className="pb-2">
              <CardTitle className="text-sm font-medium text-gray-500 flex items-center gap-2">
                <CheckCircle2 className="w-4 h-4 text-green-600" /> With Ghana Card
              </CardTitle>
            </CardHeader>
            <CardContent>
              <p className="text-3xl font-bold text-green-600">
                {summary.with_card.toLocaleString()}
              </p>
              <p className="text-xs text-gray-400 mt-1">
                {summary.total > 0
                  ? Math.round((summary.with_card / summary.total) * 100)
                  : 0}
                % coverage — click to filter
              </p>
            </CardContent>
          </Card>

          <Card
            className={`cursor-pointer border-2 transition-all ${
              filterType === "without_card" ? "border-red-500 shadow-sm" : "border-transparent"
            }`}
            onClick={() => handleFilterChange("without_card")}
          >
            <CardHeader className="pb-2">
              <CardTitle className="text-sm font-medium text-gray-500 flex items-center gap-2">
                <XCircle className="w-4 h-4 text-red-500" /> Without Ghana Card
              </CardTitle>
            </CardHeader>
            <CardContent>
              <p className="text-3xl font-bold text-red-500">
                {summary.without_card.toLocaleString()}
              </p>
              <p className="text-xs text-gray-400 mt-1">Missing numbers — click to filter & update</p>
            </CardContent>
          </Card>
        </div>

        {/* Filter Toolbar */}
        <Card>
          <CardContent className="pt-4">
            <div className="flex flex-wrap gap-3 items-end">
              {/* Search */}
              <div className="flex-1 min-w-[240px]">
                <Label className="text-xs mb-1 block">Quick Search</Label>
                <div className="relative">
                  <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                  <Input
                    className="pl-9"
                    placeholder="Search by Name, Staff ID, or GHA-..."
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    onKeyDown={handleSearchKey}
                  />
                </div>
              </div>

              {/* Client filter */}
              <div className="min-w-[200px]">
                <Label className="text-xs mb-1 block">Client</Label>
                <Select value={client} onValueChange={handleClientChange}>
                  <SelectTrigger>
                    <Building2 className="w-4 h-4 mr-2 text-gray-400" />
                    <SelectValue placeholder="All Clients" />
                  </SelectTrigger>
                  <SelectContent>
                    {clients.map((c) => (
                      <SelectItem key={c.value} value={c.value}>{c.label}</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>

              {/* Card status filter */}
              <div className="min-w-[180px]">
                <Label className="text-xs mb-1 block">Status</Label>
                <Select value={filterType} onValueChange={handleFilterChange}>
                  <SelectTrigger>
                    <SelectValue placeholder="All Status" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="all">All Staff</SelectItem>
                    <SelectItem value="with_card">With Ghana Card</SelectItem>
                    <SelectItem value="without_card">Without Ghana Card</SelectItem>
                  </SelectContent>
                </Select>
              </div>

              <Button onClick={() => applyFilters()} disabled={isLoading} className="flex items-center gap-2">
                {isLoading ? <RefreshCw className="w-4 h-4 animate-spin" /> : <Search className="w-4 h-4" />}
                Search
              </Button>

              <Button variant="outline" onClick={handleReset} className="flex items-center gap-2">
                <RefreshCw className="w-4 h-4" /> Reset
              </Button>
            </div>
          </CardContent>
        </Card>

        {/* Results Table */}
        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium flex items-center justify-between">
              <span>
                Records Found:{" "}
                <span className="font-bold text-blue-600">{totalRecords.toLocaleString()}</span>
                {records.from && records.to ? (
                  <span className="text-gray-400 font-normal ml-2">
                    (showing {records.from}–{records.to})
                  </span>
                ) : null}
              </span>
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="overflow-x-auto">
              <Table>
                <TableHeader>
                  <TableRow className="bg-gray-50 dark:bg-gray-800">
                    <TableHead className="w-12 text-center">#</TableHead>
                    <TableHead>Staff ID</TableHead>
                    <TableHead>Employee Name</TableHead>
                    <TableHead>Company / Shop</TableHead>
                    <TableHead>Ghana Card Number</TableHead>
                    <TableHead>SSNIT Number</TableHead>
                    <TableHead className="text-center">Status</TableHead>
                    <TableHead className="text-right">Action</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {tableData.length === 0 ? (
                    <TableRow>
                      <TableCell colSpan={8} className="text-center py-12 text-gray-400">
                        <CreditCard className="w-10 h-10 mx-auto mb-2 opacity-30" />
                        No records match your search criteria.
                      </TableCell>
                    </TableRow>
                  ) : (
                    tableData.map((row, idx) => (
                      <TableRow key={row.staff_id} className="hover:bg-gray-50 dark:hover:bg-gray-800">
                        <TableCell className="text-gray-400 text-xs text-center">
                          {(records.from ?? 0) + idx}
                        </TableCell>
                        <TableCell className="font-mono text-xs font-semibold text-gray-800 dark:text-gray-200">
                          {row.staff_id}
                        </TableCell>
                        <TableCell className="font-medium text-gray-900 dark:text-white">
                          {row.name}
                        </TableCell>
                        <TableCell className="text-sm text-gray-500">
                          {row.company_shop}
                        </TableCell>
                        <TableCell className="font-mono text-sm">
                          {hasCard(row.ghana_card) ? (
                            <span className="inline-block px-2 py-0.5 rounded bg-blue-50 text-blue-700 font-medium border border-blue-100">
                              {row.ghana_card}
                            </span>
                          ) : (
                            <span className="text-red-400 italic text-xs">Not Captured</span>
                          )}
                        </TableCell>
                        <TableCell className="font-mono text-sm text-gray-500">
                          {row.ssnit_number || <span className="text-gray-300">—</span>}
                        </TableCell>
                        <TableCell className="text-center">
                          {hasCard(row.ghana_card) ? (
                            <Badge className="bg-green-100 text-green-700 border-green-200 text-xs">
                              <CheckCircle2 className="w-3 h-3 mr-1" /> Captured
                            </Badge>
                          ) : (
                            <Badge className="bg-red-50 text-red-600 border-red-200 text-xs">
                              <XCircle className="w-3 h-3 mr-1" /> Missing
                            </Badge>
                          )}
                        </TableCell>
                        <TableCell className="text-right">
                          <Button
                            variant={hasCard(row.ghana_card) ? "outline" : "default"}
                            size="sm"
                            className={`h-8 text-xs ${
                              !hasCard(row.ghana_card) ? "bg-blue-600 hover:bg-blue-700 text-white" : ""
                            }`}
                            onClick={() => openEditModal(row)}
                          >
                            {hasCard(row.ghana_card) ? (
                              <>
                                <Edit2 className="w-3 h-3 mr-1" /> Edit
                              </>
                            ) : (
                              <>
                                <PlusCircle className="w-3 h-3 mr-1" /> Add Card
                              </>
                            )}
                          </Button>
                        </TableCell>
                      </TableRow>
                    ))
                  )}
                </TableBody>
              </Table>
            </div>

            {/* Pagination Controls */}
            {records.last_page > 1 && (
              <div className="flex items-center justify-between mt-4">
                <p className="text-sm text-gray-500">
                  Page {records.current_page} of {records.last_page}
                </p>
                <div className="flex gap-2">
                  <Button
                    variant="outline"
                    size="sm"
                    disabled={records.current_page <= 1}
                    onClick={() =>
                      router.get("/ghana-card", {
                        ...filters,
                        page: records.current_page - 1,
                        per_page: records.per_page,
                      }, { preserveState: true })
                    }
                  >
                    <ChevronLeft className="w-4 h-4" />
                  </Button>
                  <Button
                    variant="outline"
                    size="sm"
                    disabled={records.current_page >= records.last_page}
                    onClick={() =>
                      router.get("/ghana-card", {
                        ...filters,
                        page: records.current_page + 1,
                        per_page: records.per_page,
                      }, { preserveState: true })
                    }
                  >
                    <ChevronRight className="w-4 h-4" />
                  </Button>
                </div>
              </div>
            )}
          </CardContent>
        </Card>
      </div>

      {/* Edit Ghana Card Modal */}
      <Dialog open={!!editingStaff} onOpenChange={(open) => !open && setEditingStaff(null)}>
        <DialogContent className="sm:max-w-[480px]">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              <CreditCard className="w-5 h-5 text-blue-600" />
              Update Ghana Card
            </DialogTitle>
            <DialogDescription>
              Update identification details for staff member. Changes will update both national SSNIT records and employee portal data.
            </DialogDescription>
          </DialogHeader>

          {editingStaff && (
            <form onSubmit={handleSaveGhanaCard} className="space-y-4 py-2">
              {/* Staff Info Preview */}
              <div className="bg-gray-50 dark:bg-gray-800 p-3 rounded-lg space-y-1 text-sm border border-gray-100 dark:border-gray-700">
                <div className="flex justify-between">
                  <span className="text-gray-500">Employee:</span>
                  <span className="font-semibold text-gray-900 dark:text-white">{editingStaff.name}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-gray-500">Staff ID:</span>
                  <span className="font-mono font-medium text-gray-800 dark:text-gray-200">{editingStaff.staff_id}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-gray-500">Company / Shop:</span>
                  <span className="text-gray-700 dark:text-gray-300">{editingStaff.company_shop}</span>
                </div>
              </div>

              {/* Status Alert if Saved */}
              {saveSuccess && (
                <div className="p-3 bg-green-50 border border-green-200 text-green-800 rounded-md flex items-center gap-2 text-sm">
                  <Check className="w-4 h-4 text-green-600 flex-shrink-0" />
                  {saveSuccess}
                </div>
              )}

              {saveError && (
                <div className="p-3 bg-red-50 border border-red-200 text-red-800 rounded-md flex items-center gap-2 text-sm">
                  <AlertCircle className="w-4 h-4 text-red-600 flex-shrink-0" />
                  {saveError}
                </div>
              )}

              {/* Ghana Card Number Field */}
              <div className="space-y-1">
                <Label htmlFor="ghana_card" className="text-xs font-medium">
                  Ghana Card Number <span className="text-gray-400 font-normal">(e.g. GHA-712345678-1)</span>
                </Label>
                <div className="relative">
                  <CreditCard className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                  <Input
                    id="ghana_card"
                    className="pl-9 font-mono uppercase tracking-wide"
                    placeholder="GHA-XXXXXXXXX-X"
                    value={editCardNumber}
                    onChange={(e) => setEditCardNumber(e.target.value.toUpperCase())}
                    autoFocus
                  />
                </div>
                <p className="text-[11px] text-gray-400">
                  Enter official Republic of Ghana National ID Pin.
                </p>
              </div>

              {/* SSNIT Number Field */}
              <div className="space-y-1">
                <Label htmlFor="ssnit_number" className="text-xs font-medium">
                  SSNIT Number <span className="text-gray-400 font-normal">(Optional)</span>
                </Label>
                <Input
                  id="ssnit_number"
                  className="font-mono uppercase tracking-wide"
                  placeholder="e.g. C018301110076"
                  value={editSsnitNumber}
                  onChange={(e) => setEditSsnitNumber(e.target.value.toUpperCase())}
                />
              </div>

              <DialogFooter className="pt-2">
                <Button
                  type="button"
                  variant="outline"
                  onClick={() => setEditingStaff(null)}
                  disabled={isSaving}
                >
                  Cancel
                </Button>
                <Button
                  type="submit"
                  disabled={isSaving}
                  className="bg-blue-600 hover:bg-blue-700 text-white flex items-center gap-2"
                >
                  {isSaving ? <RefreshCw className="w-4 h-4 animate-spin" /> : <Save className="w-4 h-4" />}
                  Save Ghana Card
                </Button>
              </DialogFooter>
            </form>
          )}
        </DialogContent>
      </Dialog>
    </AppLayout>
  );
}
