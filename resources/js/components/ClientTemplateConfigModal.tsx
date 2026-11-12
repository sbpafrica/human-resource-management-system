import React, { useState, useEffect } from "react";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
  DialogFooter,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Checkbox } from "@/components/ui/checkbox";
import { Badge } from "@/components/ui/badge";
import {
  SlidersHorizontal,
  Download,
  Copy,
  RotateCcw,
  Plus,
  ArrowUp,
  ArrowDown,
  Search,
  CheckCircle2,
  AlertCircle,
  FileSpreadsheet,
  Loader2,
  Lock,
  Trash2,
} from "lucide-react";

export type ModuleType = "payroll" | "ssnit" | "petra" | "bank" | "gra" | "contract";

export interface TemplateColumnItem {
  id?: number;
  column_key: string;
  display_label: string;
  header_aliases?: string | null;
  data_type: string;
  is_active: boolean;
  is_required: boolean;
  is_custom: boolean;
  sort_order: number;
  sample_value?: string | null;
}

interface OtherClient {
  id: number;
  client_name: string;
  client_dbname: string;
}

interface Props {
  client: {
    id: number;
    client_name: string;
    client_dbname: string;
    color?: string;
  } | null;
  isOpen: boolean;
  onClose: () => void;
}

const MODULE_TABS: { id: ModuleType; label: string; icon: string; description: string }[] = [
  { id: "payroll", label: "Salary Payment", icon: "💼", description: "Monthly payroll input template with basic pay, allowances, overtime & deductions." },
  { id: "ssnit", label: "SSNIT (Tier 1)", icon: "🏛️", description: "Social Security national pension contribution template." },
  { id: "petra", label: "PETRA (Tier 2)", icon: "🛡️", description: "Occupational Tier 2 master trust pension template." },
  { id: "gra", label: "GRA (PAYE Tax)", icon: "🇬🇭", description: "Ghana Revenue Authority statutory monthly PAYE tax returns." },
  { id: "bank", label: "Bank Details", icon: "🏦", description: "Employee bank and mobile money disbursement accounts template." },
  { id: "contract", label: "Contracts", icon: "📄", description: "Staff contract start/end dates, basic salary, emails & Ghana card." },
];

export default function ClientTemplateConfigModal({ client, isOpen, onClose }: Props) {
  const [activeModule, setActiveModule] = useState<ModuleType>("payroll");
  const [modulesData, setModulesData] = useState<Record<ModuleType, TemplateColumnItem[]>>({
    payroll: [],
    ssnit: [],
    petra: [],
    gra: [],
    bank: [],
    contract: [],
  });
  const [otherClients, setOtherClients] = useState<OtherClient[]>([]);
  const [selectedCloneSource, setSelectedCloneSource] = useState<string>("");
  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [cloning, setCloning] = useState(false);
  const [resetting, setResetting] = useState(false);
  const [searchQuery, setSearchQuery] = useState("");
  const [statusFilter, setStatusFilter] = useState<"all" | "active" | "inactive">("all");
  const [feedback, setFeedback] = useState<{ message: string; type: "success" | "error" } | null>(null);

  // Custom column dialog
  const [isAddCustomOpen, setIsAddCustomOpen] = useState(false);
  const [customLabel, setCustomLabel] = useState("");
  const [customDataType, setCustomDataType] = useState("decimal");
  const [customSample, setCustomSample] = useState("");
  const [addingCustom, setAddingCustom] = useState(false);

  // Fetch configs whenever client or isOpen changes
  useEffect(() => {
    if (isOpen && client) {
      loadClientColumns();
    } else {
      setFeedback(null);
      setSearchQuery("");
    }
  }, [isOpen, client]);

  const loadClientColumns = async () => {
    if (!client) return;
    setLoading(true);
    setFeedback(null);
    try {
      const res = await fetch(`/clients/${client.id}/template-columns`, {
        headers: { Accept: "application/json" },
      });
      if (!res.ok) throw new Error("Failed to load columns");
      const data = await res.json();
      setModulesData(data.modules || { payroll: [], ssnit: [], petra: [], bank: [] });
      setOtherClients(data.other_clients || []);
    } catch (err: any) {
      setFeedback({ message: err.message || "Failed to load column settings.", type: "error" });
    } finally {
      setLoading(false);
    }
  };

  const currentColumns = modulesData[activeModule] || [];

  const updateCurrentColumns = (updater: (prev: TemplateColumnItem[]) => TemplateColumnItem[]) => {
    setModulesData(prev => ({
      ...prev,
      [activeModule]: updater(prev[activeModule] || []),
    }));
  };

  const handleToggleActive = (index: number) => {
    updateCurrentColumns(prev => {
      const next = [...prev];
      if (next[index].is_required) return prev; // Cannot disable required columns
      next[index] = { ...next[index], is_active: !next[index].is_active };
      return next;
    });
  };

  const handleLabelChange = (index: number, newLabel: string) => {
    updateCurrentColumns(prev => {
      const next = [...prev];
      next[index] = { ...next[index], display_label: newLabel };
      return next;
    });
  };

  const handleMove = (index: number, direction: "up" | "down") => {
    updateCurrentColumns(prev => {
      const targetIndex = direction === "up" ? index - 1 : index + 1;
      if (targetIndex < 0 || targetIndex >= prev.length) return prev;
      const next = [...prev];
      const temp = next[index];
      next[index] = next[targetIndex];
      next[targetIndex] = temp;
      // Re-assign sort orders
      return next.map((col, idx) => ({ ...col, sort_order: idx }));
    });
  };

  const handleDeleteColumn = async (col: TemplateColumnItem, index: number) => {
    if (col.is_required || ['staff_id', 'staffid', 'id', 'employid'].includes(col.column_key.toLowerCase())) {
      alert("The primary employee identity column (staff_id) is permanently protected and cannot be deleted.");
      return;
    }

    if (
      !confirm(
        `Are you sure you want to delete column '${col.display_label}' from the ${activeModule.toUpperCase()} template?\n\n• It will NOT appear on downloaded Excel templates.\n• Database tables and historical records remain 100% safe.`
      )
    ) {
      return;
    }

    if (!client) return;

    try {
      // Optimistically remove from state
      updateCurrentColumns(prev => prev.filter((_, idx) => idx !== index));

      // Send delete request to backend
      const res = await fetch(`/clients/${client.id}/template-columns`, {
        method: "DELETE",
        headers: {
          "Content-Type": "application/json",
          "X-CSRF-TOKEN": (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || "",
          Accept: "application/json",
        },
        body: JSON.stringify({
          module: activeModule,
          column_key: col.column_key,
        }),
      });

      const result = await res.json();
      if (!res.ok) {
        throw new Error(result.message || "Failed to delete column.");
      }

      setFeedback({
        message: result.message || `Column '${col.display_label}' deleted successfully.`,
        type: "success",
      });

      if (Array.isArray(result.columns)) {
        setModulesData(prev => ({
          ...prev,
          [activeModule]: result.columns,
        }));
      }
    } catch (err: any) {
      setFeedback({
        message: err.message || "Failed to delete column.",
        type: "error",
      });
      loadClientColumns();
    }
  };

  const handleToggleSelectAll = (activate: boolean) => {
    updateCurrentColumns(prev =>
      prev.map(col => (col.is_required ? col : { ...col, is_active: activate }))
    );
  };

  const handleSaveModule = async () => {
    if (!client) return;
    setSaving(true);
    setFeedback(null);

    try {
      const res = await fetch(`/clients/${client.id}/template-columns`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
          "X-CSRF-TOKEN": (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || "",
        },
        body: JSON.stringify({
          module: activeModule,
          columns: currentColumns.map((col, idx) => ({
            ...col,
            sort_order: idx,
          })),
        }),
      });

      const result = await res.json();
      if (!res.ok) throw new Error(result.message || "Failed to save columns");

      setFeedback({ message: result.message || "Columns saved successfully!", type: "success" });
      if (result.columns) {
        setModulesData(prev => ({ ...prev, [activeModule]: result.columns }));
      }
    } catch (err: any) {
      setFeedback({ message: err.message || "Failed to save configuration.", type: "error" });
    } finally {
      setSaving(false);
    }
  };

  const handleClone = async () => {
    if (!client || !selectedCloneSource) return;
    if (!confirm(`Are you sure you want to clone template columns from '${selectedCloneSource}'? This will override current settings.`)) {
      return;
    }

    setCloning(true);
    setFeedback(null);
    try {
      const res = await fetch(`/clients/${client.id}/template-columns/clone`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
          "X-CSRF-TOKEN": (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || "",
        },
        body: JSON.stringify({ source_client_dbname: selectedCloneSource }),
      });

      const result = await res.json();
      if (!res.ok) throw new Error(result.message || "Failed to clone");

      setFeedback({ message: result.message, type: "success" });
      if (result.modules) {
        setModulesData(result.modules);
      }
    } catch (err: any) {
      setFeedback({ message: err.message || "Failed to clone template columns.", type: "error" });
    } finally {
      setCloning(false);
    }
  };

  const handleResetToStandard = async () => {
    if (!client) return;
    if (!confirm(`Reset ${activeModule.toUpperCase()} template columns to standard default headers?`)) {
      return;
    }

    setResetting(true);
    setFeedback(null);
    try {
      const res = await fetch(`/clients/${client.id}/template-columns/reset`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
          "X-CSRF-TOKEN": (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || "",
        },
        body: JSON.stringify({ module: activeModule }),
      });

      const result = await res.json();
      if (!res.ok) throw new Error(result.message || "Failed to reset");

      setFeedback({ message: result.message, type: "success" });
      if (result.modules) {
        setModulesData(result.modules);
      }
    } catch (err: any) {
      setFeedback({ message: err.message || "Failed to reset columns.", type: "error" });
    } finally {
      setResetting(false);
    }
  };

  const handleAddCustomColumn = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!client || !customLabel.trim()) return;

    setAddingCustom(true);
    try {
      const res = await fetch(`/clients/${client.id}/template-columns/custom`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
          "X-CSRF-TOKEN": (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || "",
        },
        body: JSON.stringify({
          module: activeModule,
          display_label: customLabel.trim(),
          data_type: customDataType,
          sample_value: customSample.trim() || undefined,
        }),
      });

      const result = await res.json();
      if (!res.ok) throw new Error(result.message || "Failed to add custom column");

      if (result.column) {
        updateCurrentColumns(prev => [...prev, result.column]);
      }
      setIsAddCustomOpen(false);
      setCustomLabel("");
      setCustomSample("");
      setFeedback({ message: result.message, type: "success" });
    } catch (err: any) {
      alert(err.message || "Failed to add custom column");
    } finally {
      setAddingCustom(false);
    }
  };

  const handleDownloadSample = () => {
    if (!client) return;
    window.location.href = `/clients/${client.client_dbname}/download-template/${activeModule}`;
  };

  // Filter columns based on search and status
  const filteredColumns = currentColumns.filter(col => {
    const matchesSearch =
      col.display_label.toLowerCase().includes(searchQuery.toLowerCase()) ||
      col.column_key.toLowerCase().includes(searchQuery.toLowerCase());

    if (!matchesSearch) return false;
    if (statusFilter === "active") return col.is_active;
    if (statusFilter === "inactive") return !col.is_active;
    return true;
  });

  const activeCount = currentColumns.filter(c => c.is_active).length;
  const totalCount = currentColumns.length;

  return (
    <Dialog open={isOpen} onOpenChange={open => !open && onClose()}>
      <DialogContent className="max-w-4xl max-h-[90vh] flex flex-col p-0 overflow-hidden">
        {/* Header */}
        <DialogHeader className="p-6 pb-4 border-b bg-gray-50/80">
          <div className="flex items-center justify-between">
            <div className="space-y-1">
              <div className="flex items-center gap-2">
                <div
                  className="w-3.5 h-3.5 rounded-full border shadow-sm"
                  style={{ backgroundColor: client?.color || "#3b82f6" }}
                />
                <DialogTitle className="text-xl font-bold flex items-center gap-2">
                  <span>Excel Template Columns</span>
                  <span className="text-muted-foreground font-normal text-sm">
                    ({client?.client_name})
                  </span>
                </DialogTitle>
              </div>
              <DialogDescription className="text-xs text-muted-foreground">
                Select which columns appear on the Excel file downloaded by staff/clients to input data before uploading.
              </DialogDescription>
            </div>
          </div>

          {/* Module Selector Tabs */}
          <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2 pt-4">
            {MODULE_TABS.map(tab => {
              const isSelected = activeModule === tab.id;
              const colCount = (modulesData[tab.id] || []).filter(c => c.is_active).length;
              return (
                <button
                  key={tab.id}
                  onClick={() => {
                    setActiveModule(tab.id);
                    setFeedback(null);
                  }}
                  className={`flex flex-col items-start p-2.5 rounded-lg border text-left transition-all ${
                    isSelected
                      ? "bg-white border-blue-600 shadow-sm ring-1 ring-blue-600"
                      : "bg-white/60 hover:bg-white border-gray-200 text-gray-700 hover:border-gray-300"
                  }`}
                >
                  <div className="flex items-center justify-between w-full">
                    <span className="text-sm font-semibold flex items-center gap-1.5">
                      <span>{tab.icon}</span>
                      <span>{tab.label}</span>
                    </span>
                    <Badge variant={isSelected ? "default" : "secondary"} className="text-[10px] px-1.5 py-0 h-4">
                      {colCount}
                    </Badge>
                  </div>
                </button>
              );
            })}
          </div>
        </DialogHeader>

        {/* Toolbar & Actions */}
        <div className="px-6 py-3 border-b bg-white flex flex-wrap items-center justify-between gap-3 text-xs">
          <div className="flex items-center gap-2 flex-wrap">
            <span className="font-medium text-gray-700">
              Active: <strong className="text-blue-600">{activeCount}</strong> / {totalCount} columns
            </span>
            <span className="text-gray-300">|</span>
            <Button
              size="sm"
              variant="outline"
              className="h-7 text-xs px-2"
              onClick={() => handleToggleSelectAll(true)}
            >
              Select All
            </Button>
            <Button
              size="sm"
              variant="outline"
              className="h-7 text-xs px-2"
              onClick={() => handleToggleSelectAll(false)}
            >
              Deselect Non-Required
            </Button>
            <Button
              size="sm"
              variant="outline"
              className="h-7 text-xs px-2"
              onClick={() => setIsAddCustomOpen(true)}
            >
              <Plus className="h-3 w-3 mr-1 text-green-600" /> Custom Column
            </Button>
          </div>

          <div className="flex items-center gap-2 flex-wrap">
            {/* Clone Dropdown */}
            {otherClients.length > 0 && (
              <div className="flex items-center gap-1.5">
                <select
                  value={selectedCloneSource}
                  onChange={e => setSelectedCloneSource(e.target.value)}
                  className="h-7 text-xs rounded border border-gray-300 bg-white px-2 py-0 text-gray-700"
                >
                  <option value="">Clone from Client...</option>
                  {otherClients.map(c => (
                    <option key={c.id} value={c.client_dbname}>
                      {c.client_name}
                    </option>
                  ))}
                </select>
                <Button
                  size="sm"
                  variant="outline"
                  className="h-7 text-xs px-2"
                  disabled={!selectedCloneSource || cloning}
                  onClick={handleClone}
                  title="Copy all template column settings from chosen client"
                >
                  {cloning ? <Loader2 className="h-3 w-3 animate-spin" /> : <Copy className="h-3 w-3" />}
                </Button>
              </div>
            )}

            <Button
              size="sm"
              variant="outline"
              className="h-7 text-xs px-2 text-gray-600"
              onClick={handleResetToStandard}
              disabled={resetting}
              title="Reset current tab to standard defaults"
            >
              {resetting ? <Loader2 className="h-3 w-3 animate-spin mr-1" /> : <RotateCcw className="h-3 w-3 mr-1" />}
              Reset
            </Button>

            <Button
              size="sm"
              variant="secondary"
              className="h-7 text-xs px-2 bg-blue-50 text-blue-700 hover:bg-blue-100 border border-blue-200"
              onClick={handleDownloadSample}
              title="Download live Excel file to preview your changes"
            >
              <Download className="h-3 w-3 mr-1" /> Preview Excel
            </Button>
          </div>
        </div>

        {/* Feedback message banner */}
        {feedback && (
          <div
            className={`px-6 py-2 text-xs flex items-center justify-between border-b ${
              feedback.type === "success"
                ? "bg-green-50 text-green-800 border-green-200"
                : "bg-red-50 text-red-800 border-red-200"
            }`}
          >
            <div className="flex items-center gap-2">
              {feedback.type === "success" ? (
                <CheckCircle2 className="h-4 w-4 text-green-600" />
              ) : (
                <AlertCircle className="h-4 w-4 text-red-600" />
              )}
              <span>{feedback.message}</span>
            </div>
            <button
              onClick={() => setFeedback(null)}
              className="text-gray-400 hover:text-gray-600 text-sm font-bold"
            >
              ×
            </button>
          </div>
        )}

        {/* Search & Filter bar inside column list */}
        <div className="px-6 py-2 bg-gray-50/50 flex items-center justify-between gap-3 border-b text-xs">
          <div className="relative flex-1 max-w-sm">
            <Search className="h-3.5 w-3.5 absolute left-2.5 top-2.5 text-gray-400" />
            <Input
              placeholder="Filter columns by name or key..."
              value={searchQuery}
              onChange={e => setSearchQuery(e.target.value)}
              className="h-8 pl-8 text-xs bg-white"
            />
          </div>

          <div className="flex items-center gap-1.5">
            <button
              onClick={() => setStatusFilter("all")}
              className={`px-2 py-1 rounded text-xs transition ${
                statusFilter === "all" ? "bg-white font-semibold shadow-xs border" : "text-gray-600 hover:text-black"
              }`}
            >
              All ({totalCount})
            </button>
            <button
              onClick={() => setStatusFilter("active")}
              className={`px-2 py-1 rounded text-xs transition ${
                statusFilter === "active" ? "bg-white font-semibold shadow-xs border text-blue-600" : "text-gray-600 hover:text-black"
              }`}
            >
              Active ({activeCount})
            </button>
            <button
              onClick={() => setStatusFilter("inactive")}
              className={`px-2 py-1 rounded text-xs transition ${
                statusFilter === "inactive" ? "bg-white font-semibold shadow-xs border text-gray-500" : "text-gray-600 hover:text-black"
              }`}
            >
              Inactive ({totalCount - activeCount})
            </button>
          </div>
        </div>

        {/* Scrollable Column List */}
        <div className="flex-1 overflow-y-auto px-6 py-3 space-y-1.5 min-h-[300px]">
          {loading ? (
            <div className="flex flex-col items-center justify-center h-48 gap-3 text-muted-foreground">
              <Loader2 className="h-6 w-6 animate-spin text-blue-600" />
              <p className="text-sm">Loading template column configuration...</p>
            </div>
          ) : filteredColumns.length === 0 ? (
            <div className="text-center py-12 text-muted-foreground text-sm">
              No columns match the current filter or search.
            </div>
          ) : (
            filteredColumns.map((col, idx) => {
              const originalIndex = currentColumns.findIndex(c => c.column_key === col.column_key);
              return (
                <div
                  key={col.column_key}
                  className={`flex items-center justify-between p-2 rounded-lg border transition-all text-sm ${
                    col.is_active
                      ? "bg-white border-gray-200 shadow-2xs hover:border-blue-300"
                      : "bg-gray-50/70 border-gray-200 opacity-60 hover:opacity-100"
                  }`}
                >
                  {/* Left: Reorder, Active Checkbox, Label */}
                  <div className="flex items-center gap-3 flex-1 min-w-0 pr-4">
                    {/* Sort reorder buttons */}
                    <div className="flex flex-col gap-0.5">
                      <button
                        type="button"
                        onClick={() => handleMove(originalIndex, "up")}
                        disabled={originalIndex === 0}
                        className="text-gray-400 hover:text-gray-700 disabled:opacity-20 p-0.5"
                        title="Move Up"
                      >
                        <ArrowUp className="h-3 w-3" />
                      </button>
                      <button
                        type="button"
                        onClick={() => handleMove(originalIndex, "down")}
                        disabled={originalIndex === currentColumns.length - 1}
                        className="text-gray-400 hover:text-gray-700 disabled:opacity-20 p-0.5"
                        title="Move Down"
                      >
                        <ArrowDown className="h-3 w-3" />
                      </button>
                    </div>

                    {/* Active toggle */}
                    <Checkbox
                      id={`col_${col.column_key}`}
                      checked={col.is_active}
                      disabled={col.is_required}
                      onCheckedChange={() => handleToggleActive(originalIndex)}
                      className="cursor-pointer"
                    />

                    {/* Column Label Input */}
                    <div className="flex-1 min-w-0">
                      <div className="flex items-center gap-2">
                        <Input
                          value={col.display_label}
                          onChange={e => handleLabelChange(originalIndex, e.target.value)}
                          className={`h-7 text-xs font-medium ${
                            col.is_active ? "text-gray-900 bg-white" : "text-gray-500 bg-transparent"
                          }`}
                        />
                        {col.is_required && (
                          <Badge variant="outline" className="text-[10px] text-amber-700 border-amber-300 bg-amber-50 h-5 px-1.5 flex items-center gap-1">
                            <Lock className="h-2.5 w-2.5" /> Required
                          </Badge>
                        )}
                        {col.is_custom && (
                          <Badge variant="outline" className="text-[10px] text-purple-700 border-purple-300 bg-purple-50 h-5 px-1.5">
                            Custom
                          </Badge>
                        )}
                      </div>
                      <div className="flex items-center gap-3 mt-1 text-[11px] text-gray-500 font-mono">
                        <span>Key: {col.column_key}</span>
                        <span>•</span>
                        <span>Type: {col.data_type}</span>
                        {col.sample_value && (
                          <>
                            <span>•</span>
                            <span>Sample: &ldquo;{col.sample_value}&rdquo;</span>
                          </>
                        )}
                      </div>
                    </div>
                  </div>

                  {/* Right: Status indicator & Delete action */}
                  <div className="flex items-center gap-2 text-xs">
                    <span
                      className={`px-2 py-0.5 rounded-full text-[11px] font-medium ${
                        col.is_active ? "bg-green-100 text-green-700" : "bg-gray-100 text-gray-500"
                      }`}
                    >
                      {col.is_active ? "In Excel" : "Excluded"}
                    </span>

                    {!col.is_required && !['staff_id', 'staffid', 'id', 'employid'].includes(col.column_key.toLowerCase()) && (
                      <button
                        type="button"
                        onClick={() => handleDeleteColumn(col, originalIndex)}
                        className="text-gray-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-950/40 p-1 rounded transition-colors"
                        title="Delete column (excludes from Excel template)"
                      >
                        <Trash2 className="h-3.5 w-3.5" />
                      </button>
                    )}
                  </div>
                </div>
              );
            })
          )}
        </div>

        {/* Footer */}
        <DialogFooter className="p-4 border-t bg-gray-50/80 flex items-center justify-between sm:justify-between">
          <div className="text-xs text-muted-foreground flex items-center gap-1.5">
            <FileSpreadsheet className="h-4 w-4 text-green-600" />
            <span>Changes will reflect instantly on template downloads for <strong>{client?.client_name}</strong>.</span>
          </div>
          <div className="flex items-center gap-2">
            <Button variant="outline" onClick={onClose} disabled={saving}>
              Close
            </Button>
            <Button onClick={handleSaveModule} disabled={saving || loading} className="bg-blue-600 hover:bg-blue-700 text-white">
              {saving && <Loader2 className="h-4 w-4 mr-2 animate-spin" />}
              Save {MODULE_TABS.find(t => t.id === activeModule)?.label}
            </Button>
          </div>
        </DialogFooter>
      </DialogContent>

      {/* Sub-Dialog: Add Custom Column */}
      <Dialog open={isAddCustomOpen} onOpenChange={setIsAddCustomOpen}>
        <DialogContent className="max-w-md">
          <DialogHeader>
            <DialogTitle>Add Custom Column</DialogTitle>
            <DialogDescription className="text-xs">
              Add a specialized field to the <strong>{MODULE_TABS.find(t => t.id === activeModule)?.label}</strong> template for {client?.client_name}.
            </DialogDescription>
          </DialogHeader>

          <form onSubmit={handleAddCustomColumn} className="space-y-4 pt-2">
            <div>
              <Label className="text-xs font-semibold">Column Header Label *</Label>
              <Input
                placeholder="e.g. Hazard Allowance, Union Dues"
                value={customLabel}
                onChange={e => setCustomLabel(e.target.value)}
                required
                className="mt-1"
                autoFocus
              />
            </div>

            <div>
              <Label className="text-xs font-semibold">Data Type</Label>
              <select
                value={customDataType}
                onChange={e => setCustomDataType(e.target.value)}
                className="mt-1 w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm shadow-xs"
              >
                <option value="decimal">Decimal / Currency Amount (e.g. 500.00)</option>
                <option value="string">Text / Code (e.g. Grade A)</option>
                <option value="integer">Integer / Quantity (e.g. 10)</option>
                <option value="date">Date (YYYY-MM-DD)</option>
              </select>
            </div>

            <div>
              <Label className="text-xs font-semibold">Example Sample Value (Optional)</Label>
              <Input
                placeholder={customDataType === "decimal" ? "0.00" : "Sample value"}
                value={customSample}
                onChange={e => setCustomSample(e.target.value)}
                className="mt-1 text-sm"
              />
            </div>

            <DialogFooter className="pt-2">
              <Button type="button" variant="outline" onClick={() => setIsAddCustomOpen(false)}>
                Cancel
              </Button>
              <Button type="submit" disabled={addingCustom || !customLabel.trim()}>
                {addingCustom && <Loader2 className="h-4 w-4 mr-2 animate-spin" />}
                Add Column
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </Dialog>
  );
}
