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
  ChevronsUp,
  ChevronsDown,
  Search,
  CheckCircle2,
  AlertCircle,
  FileSpreadsheet,
  Loader2,
  Lock,
  Trash2,
  Save,
  CheckCheck,
  GripVertical,
  Move,
  Maximize2,
  Minimize2,
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
  const [dirtyModules, setDirtyModules] = useState<Set<ModuleType>>(new Set());

  // Drag and drop state
  const [draggedKey, setDraggedKey] = useState<string | null>(null);
  const [dragOverKey, setDragOverKey] = useState<string | null>(null);
  const [dropPosition, setDropPosition] = useState<"before" | "after">("before");

  // Modal sizing & drag state
  const [isMaximized, setIsMaximized] = useState(false);
  const [dialogOffsetY, setDialogOffsetY] = useState(0);
  const [isDraggingDialog, setIsDraggingDialog] = useState(false);
  const dragStartYRef = React.useRef(0);
  const startOffsetYRef = React.useRef(0);

  // Auto-scrolling while dragging columns
  const scrollContainerRef = React.useRef<HTMLDivElement>(null);
  const scrollIntervalRef = React.useRef<number | null>(null);

  const stopAutoScroll = () => {
    if (scrollIntervalRef.current) {
      clearInterval(scrollIntervalRef.current);
      scrollIntervalRef.current = null;
    }
  };

  const handleContainerDragOver = (e: React.DragEvent) => {
    e.preventDefault();
    if (!scrollContainerRef.current) return;

    const container = scrollContainerRef.current;
    const rect = container.getBoundingClientRect();
    const offsetY = e.clientY - rect.top;
    const scrollZone = 50;

    if (offsetY < scrollZone) {
      const speed = Math.max(6, Math.floor((scrollZone - offsetY) / 2));
      if (!scrollIntervalRef.current) {
        scrollIntervalRef.current = window.setInterval(() => {
          if (container) container.scrollTop -= speed;
        }, 16);
      }
    } else if (offsetY > rect.height - scrollZone) {
      const distFromBottom = rect.height - offsetY;
      const speed = Math.max(6, Math.floor((scrollZone - distFromBottom) / 2));
      if (!scrollIntervalRef.current) {
        scrollIntervalRef.current = window.setInterval(() => {
          if (container) container.scrollTop += speed;
        }, 16);
      }
    } else {
      stopAutoScroll();
    }
  };

  // Drag dialog up/down handler
  const handleDialogHeaderMouseDown = (e: React.MouseEvent) => {
    if ((e.target as HTMLElement).closest("button, input, select, a, textarea, [role='tab']")) return;
    setIsDraggingDialog(true);
    dragStartYRef.current = e.clientY;
    startOffsetYRef.current = dialogOffsetY;
  };

  useEffect(() => {
    const handleMouseMove = (e: MouseEvent) => {
      if (!isDraggingDialog) return;
      const deltaY = e.clientY - dragStartYRef.current;
      setDialogOffsetY(startOffsetYRef.current + deltaY);
    };

    const handleMouseUp = () => {
      setIsDraggingDialog(false);
    };

    if (isDraggingDialog) {
      window.addEventListener("mousemove", handleMouseMove);
      window.addEventListener("mouseup", handleMouseUp);
    }
    return () => {
      window.removeEventListener("mousemove", handleMouseMove);
      window.removeEventListener("mouseup", handleMouseUp);
    };
  }, [isDraggingDialog]);

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
      setDirtyModules(new Set());
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
    setDirtyModules(prev => new Set(prev).add(activeModule));
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

  const handleMove = (index: number, direction: "up" | "down" | "top" | "bottom") => {
    updateCurrentColumns(prev => {
      let targetIndex = index;
      if (direction === "up") targetIndex = index - 1;
      else if (direction === "down") targetIndex = index + 1;
      else if (direction === "top") targetIndex = 0;
      else if (direction === "bottom") targetIndex = prev.length - 1;

      if (targetIndex < 0 || targetIndex >= prev.length || targetIndex === index) return prev;
      const next = [...prev];
      const [movedItem] = next.splice(index, 1);
      next.splice(targetIndex, 0, movedItem);
      return next.map((col, idx) => ({ ...col, sort_order: idx }));
    });
  };

  const handleMoveToPosition = (fromIndex: number, newPosition1Based: number) => {
    const toIndex = Math.max(0, Math.min(currentColumns.length - 1, newPosition1Based - 1));
    if (fromIndex === toIndex) return;
    updateCurrentColumns(prev => {
      const next = [...prev];
      const [movedItem] = next.splice(fromIndex, 1);
      next.splice(toIndex, 0, movedItem);
      return next.map((col, idx) => ({ ...col, sort_order: idx }));
    });
  };

  const handleReorder = (fromIndex: number, toIndex: number) => {
    if (fromIndex === toIndex || fromIndex < 0 || toIndex < 0) return;
    updateCurrentColumns(prev => {
      const next = [...prev];
      const [movedItem] = next.splice(fromIndex, 1);
      next.splice(toIndex, 0, movedItem);
      return next.map((col, idx) => ({ ...col, sort_order: idx }));
    });
  };

  const handleDragStart = (e: React.DragEvent, colKey: string) => {
    e.dataTransfer.effectAllowed = "move";
    e.dataTransfer.setData("text/plain", colKey);
    // Defer state update so browser initializes drag image from untouched DOM node
    setTimeout(() => {
      setDraggedKey(colKey);
    }, 0);
  };

  const handleRowDragOver = (e: React.DragEvent, colKey: string) => {
    e.preventDefault();
    e.stopPropagation();
    e.dataTransfer.dropEffect = "move";

    const targetRect = (e.currentTarget as HTMLElement).getBoundingClientRect();
    const midY = targetRect.top + targetRect.height / 2;
    const pos = e.clientY < midY ? "before" : "after";

    if (dragOverKey !== colKey || dropPosition !== pos) {
      setDragOverKey(colKey);
      setDropPosition(pos);
    }
  };

  const handleDragLeave = (e: React.DragEvent) => {
    e.preventDefault();
  };

  const handleDragEnd = () => {
    stopAutoScroll();
    setDraggedKey(null);
    setDragOverKey(null);
  };

  const handleDrop = (e: React.DragEvent, targetColKey: string) => {
    e.preventDefault();
    e.stopPropagation();
    stopAutoScroll();

    if (!draggedKey || draggedKey === targetColKey) {
      setDraggedKey(null);
      setDragOverKey(null);
      return;
    }

    const fromIndex = currentColumns.findIndex(c => c.column_key === draggedKey);
    const toIndex = currentColumns.findIndex(c => c.column_key === targetColKey);

    if (fromIndex === -1 || toIndex === -1) {
      setDraggedKey(null);
      setDragOverKey(null);
      return;
    }

    let finalIndex = toIndex;
    if (dropPosition === "after") {
      finalIndex = fromIndex < toIndex ? toIndex : toIndex + 1;
    } else {
      finalIndex = fromIndex < toIndex ? toIndex - 1 : toIndex;
    }

    if (finalIndex < 0) finalIndex = 0;
    if (finalIndex >= currentColumns.length) finalIndex = currentColumns.length - 1;

    if (fromIndex !== finalIndex) {
      const movedColName = currentColumns[fromIndex]?.display_label || "Column";
      handleReorder(fromIndex, finalIndex);
      setFeedback({
        message: `Repositioned '${movedColName}' to position #${finalIndex + 1}. Click 'Save' to apply changes.`,
        type: "success",
      });
    }

    setDraggedKey(null);
    setDragOverKey(null);
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

  const handleSaveModule = async (moduleToSave?: ModuleType, silent: boolean = false): Promise<boolean> => {
    if (!client) return false;
    const targetModule = moduleToSave || activeModule;
    const cols = modulesData[targetModule] || [];

    setSaving(true);
    if (!silent) setFeedback(null);

    try {
      const res = await fetch(`/clients/${client.id}/template-columns`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
          "X-CSRF-TOKEN": (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || "",
        },
        body: JSON.stringify({
          module: targetModule,
          columns: cols.map((col, idx) => ({
            ...col,
            sort_order: idx,
          })),
        }),
      });

      const result = await res.json();
      if (!res.ok) throw new Error(result.message || "Failed to save columns");

      setDirtyModules(prev => {
        const next = new Set(prev);
        next.delete(targetModule);
        return next;
      });

      const tabLabel = MODULE_TABS.find(t => t.id === targetModule)?.label || targetModule;
      setFeedback({
        message: result.message || `${tabLabel} template configuration saved successfully!`,
        type: "success",
      });

      if (result.columns) {
        setModulesData(prev => ({ ...prev, [targetModule]: result.columns }));
      }
      return true;
    } catch (err: any) {
      setFeedback({ message: err.message || "Failed to save configuration.", type: "error" });
      return false;
    } finally {
      setSaving(false);
    }
  };

  const handleSaveAll = async (): Promise<boolean> => {
    if (!client) return false;
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
          modules: Object.fromEntries(
            Object.entries(modulesData).map(([mod, cols]) => [
              mod,
              cols.map((col, idx) => ({ ...col, sort_order: idx })),
            ])
          ),
        }),
      });

      const result = await res.json();
      if (!res.ok) throw new Error(result.message || "Failed to save all columns");

      setDirtyModules(new Set());
      setFeedback({
        message: result.message || "All template column configurations saved successfully!",
        type: "success",
      });

      if (result.modules) {
        setModulesData(prev => ({ ...prev, ...result.modules }));
      }
      return true;
    } catch (err: any) {
      setFeedback({ message: err.message || "Failed to save configurations.", type: "error" });
      return false;
    } finally {
      setSaving(false);
    }
  };

  const handleTabChange = async (nextModule: ModuleType) => {
    if (nextModule === activeModule) return;

    // Save previous configuration automatically if it was modified
    if (dirtyModules.has(activeModule)) {
      await handleSaveModule(activeModule, true);
    } else {
      setFeedback(null);
    }

    setActiveModule(nextModule);
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
      <DialogContent
        className={`flex flex-col p-0 overflow-hidden shadow-2xl border transition-all duration-150 ${
          isMaximized
            ? "!fixed !inset-0 !w-screen !h-screen !max-w-none !max-h-none !rounded-none !border-0 z-50 !translate-x-0 !translate-y-0 !top-0 !left-0"
            : "w-[96vw] max-w-[1650px] 2xl:max-w-[1800px] h-[94vh] max-h-[94vh] rounded-2xl border-gray-200"
        }`}
        style={
          isMaximized
            ? { transform: "none", top: 0, left: 0 }
            : {
                transform: `translate(-50%, calc(-50% + ${dialogOffsetY}px))`,
                transition: isDraggingDialog ? "none" : "transform 0.1s ease-out",
              }
        }
      >
        {/* Header - Draggable on screen & Maximize Control */}
        <DialogHeader
          onMouseDown={handleDialogHeaderMouseDown}
          className="p-4 sm:p-5 pb-3 border-b bg-gray-50/95 shrink-0 cursor-grab active:cursor-grabbing select-none"
          title="Click and drag header to move modal up/down on your screen"
        >
          <div className="flex items-center justify-between pr-8">
            <div className="space-y-1">
              <div className="flex items-center gap-2">
                <div
                  className="w-4 h-4 rounded-full border shadow-sm"
                  style={{ backgroundColor: client?.color || "#3b82f6" }}
                />
                <DialogTitle className="text-xl sm:text-2xl font-bold flex items-center gap-2">
                  <span>Excel Template Columns</span>
                  <span className="text-muted-foreground font-normal text-base">
                    ({client?.client_name})
                  </span>
                  {dialogOffsetY !== 0 && !isMaximized && (
                    <button
                      type="button"
                      onClick={(e) => {
                        e.stopPropagation();
                        setDialogOffsetY(0);
                      }}
                      className="ml-2 text-xs font-normal text-blue-600 hover:underline bg-blue-50 px-2 py-0.5 rounded border border-blue-200"
                      title="Reset modal position to center"
                    >
                      Reset position
                    </button>
                  )}
                </DialogTitle>
              </div>
              <DialogDescription className="text-xs sm:text-sm text-muted-foreground">
                Customize, drag & drop, and order the exact columns that appear when clients/staff download Excel templates to upload monthly payroll.
              </DialogDescription>
            </div>

            {/* Maximize / Restore Toggle Button */}
            <div className="flex items-center gap-2" onMouseDown={(e) => e.stopPropagation()}>
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() => {
                  setIsMaximized(!isMaximized);
                  setDialogOffsetY(0);
                }}
                className="h-8 px-2.5 text-xs font-medium bg-white text-gray-700 hover:text-blue-600 hover:border-blue-300 shadow-2xs"
                title={isMaximized ? "Restore window size" : "Expand to Fullscreen"}
              >
                {isMaximized ? (
                  <>
                    <Minimize2 className="h-3.5 w-3.5 mr-1.5" />
                    <span>Restore Size</span>
                  </>
                ) : (
                  <>
                    <Maximize2 className="h-3.5 w-3.5 mr-1.5 text-blue-600" />
                    <span>Fullscreen</span>
                  </>
                )}
              </Button>
            </div>
          </div>

          {/* Module Selector Tabs */}
          <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2 pt-3">
            {MODULE_TABS.map(tab => {
              const isSelected = activeModule === tab.id;
              const colCount = (modulesData[tab.id] || []).filter(c => c.is_active).length;
              const isDirty = dirtyModules.has(tab.id);
              return (
                <button
                  key={tab.id}
                  type="button"
                  onClick={() => handleTabChange(tab.id)}
                  className={`flex flex-col items-start p-2.5 rounded-lg border text-left transition-all relative ${
                    isSelected
                      ? "bg-white border-blue-600 shadow-sm ring-2 ring-blue-600/30 font-semibold"
                      : "bg-white/70 hover:bg-white border-gray-200 text-gray-700 hover:border-gray-300"
                  }`}
                >
                  <div className="flex items-center justify-between w-full">
                    <span className="text-xs sm:text-sm flex items-center gap-1.5 truncate">
                      <span>{tab.icon}</span>
                      <span className="truncate">{tab.label}</span>
                    </span>
                    <div className="flex items-center gap-1">
                      {isDirty && (
                        <span
                          className="w-2 h-2 rounded-full bg-amber-500 animate-pulse shrink-0"
                          title="Has unsaved changes (will auto-save on tab change or click Save)"
                        />
                      )}
                      <Badge variant={isSelected ? "default" : "secondary"} className="text-[10px] px-1.5 py-0 h-4">
                        {colCount}
                      </Badge>
                    </div>
                  </div>
                </button>
              );
            })}
          </div>
        </DialogHeader>

        {/* Toolbar & Actions */}
        <div className="px-4 sm:px-6 py-2.5 border-b bg-white shrink-0 flex flex-wrap items-center justify-between gap-2.5 text-xs">
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

            {/* Direct Save Action in the Top Toolbar */}
            <Button
              size="sm"
              onClick={() => handleSaveModule()}
              disabled={saving || loading}
              className={`h-7 text-xs px-3 font-semibold text-white shadow-sm transition-all ${
                dirtyModules.has(activeModule)
                  ? "bg-emerald-600 hover:bg-emerald-700 ring-2 ring-emerald-500/30"
                  : "bg-blue-600 hover:bg-blue-700"
              }`}
              title={`Save ${MODULE_TABS.find(t => t.id === activeModule)?.label} template columns`}
            >
              {saving ? (
                <Loader2 className="h-3.5 w-3.5 animate-spin mr-1" />
              ) : (
                <Save className="h-3.5 w-3.5 mr-1" />
              )}
              <span>Save {MODULE_TABS.find(t => t.id === activeModule)?.label}</span>
              {dirtyModules.has(activeModule) && (
                <span className="ml-1.5 px-1 py-0.2 bg-white/25 rounded text-[9px] uppercase tracking-wider">
                  Unsaved
                </span>
              )}
            </Button>

            {dirtyModules.size > 1 && (
              <Button
                size="sm"
                variant="outline"
                onClick={handleSaveAll}
                disabled={saving || loading}
                className="h-7 text-xs px-2.5 font-medium border-emerald-600 text-emerald-700 bg-emerald-50 hover:bg-emerald-100"
                title="Save changes across all modified tabs at once"
              >
                <CheckCheck className="h-3.5 w-3.5 mr-1 text-emerald-600" />
                <span>Save All ({dirtyModules.size})</span>
              </Button>
            )}
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
        <div className="px-4 sm:px-6 py-2 bg-gray-50/60 shrink-0 flex items-center justify-between gap-3 border-b text-xs">
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

        {/* Drag & Drop Instructions Banner */}
        <div className="px-4 sm:px-6 py-2 bg-gradient-to-r from-blue-50/90 via-indigo-50/70 to-blue-50/90 dark:from-blue-950/30 dark:to-blue-950/30 border-b border-blue-200/80 text-[11px] text-blue-900 dark:text-blue-200 flex items-center justify-between">
          <div className="flex items-center gap-2">
            <GripVertical className="h-4 w-4 text-blue-600 shrink-0 animate-pulse" />
            <span>
              <strong>Drag Columns Up & Down:</strong> Click and drag the grip handle <span className="font-mono bg-white text-blue-800 border border-blue-300 px-1.5 py-0.5 rounded font-bold shadow-2xs">⋮⋮</span> or use the <strong>▲ / ▼</strong> arrow buttons to arrange columns to your exact desired position.
            </span>
          </div>
          <div className="flex items-center gap-3 shrink-0 text-[10px]">
            <span className="bg-white/80 dark:bg-gray-800 px-2 py-0.5 rounded border border-blue-200 text-blue-700 dark:text-blue-300 font-semibold hidden md:inline">
              Excel Order: Top = Column A, B, C...
            </span>
            <span className="text-gray-400 hidden lg:inline">
              (Header is also draggable up/down)
            </span>
          </div>
        </div>

        {/* Scrollable Column List with Auto-Scroll on Drag */}
        <div
          ref={scrollContainerRef}
          onDragOver={handleContainerDragOver}
          onDrop={stopAutoScroll}
          className="flex-1 min-h-0 overflow-y-auto px-4 sm:px-6 py-3 space-y-2 relative"
        >
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
              const isBeingDragged = draggedKey === col.column_key;
              const isDragOver = dragOverKey === col.column_key && !isBeingDragged;

              return (
                <div key={col.column_key} className="relative">
                  {/* Drop Indicator Line (Before) */}
                  {isDragOver && dropPosition === "before" && (
                    <div className="absolute -top-1.5 left-0 right-0 z-20 flex items-center">
                      <div className="w-2.5 h-2.5 rounded-full bg-blue-600 shadow-sm -ml-1"></div>
                      <div className="h-1 bg-blue-600 flex-1 rounded-full shadow-sm"></div>
                      <div className="w-2.5 h-2.5 rounded-full bg-blue-600 shadow-sm -mr-1"></div>
                    </div>
                  )}

                  <div
                    draggable={true}
                    onDragStart={(e) => handleDragStart(e, col.column_key)}
                    onDragOver={(e) => handleRowDragOver(e, col.column_key)}
                    onDragLeave={handleDragLeave}
                    onDrop={(e) => handleDrop(e, col.column_key)}
                    onDragEnd={handleDragEnd}
                    className={`group flex items-center justify-between p-2.5 rounded-lg border transition-all text-sm select-none ${
                      isBeingDragged
                        ? "opacity-35 border-dashed border-blue-500 bg-blue-50/70 scale-[0.99] shadow-inner"
                        : isDragOver
                        ? "border-blue-500 ring-2 ring-blue-500/40 bg-blue-50/40 shadow-sm"
                        : col.is_active
                        ? "bg-white border-gray-200 shadow-2xs hover:border-blue-400 hover:shadow-xs"
                        : "bg-gray-50/70 border-gray-200 opacity-60 hover:opacity-100"
                    }`}
                  >
                    {/* Left: Drag Handle, Position Badge, Reorder Buttons, Active Checkbox, Label */}
                    <div className="flex items-center gap-2.5 flex-1 min-w-0 pr-3">
                      {/* Big Drag Grip Handle */}
                      <div
                        className="cursor-grab active:cursor-grabbing p-1.5 -ml-1 text-gray-400 group-hover:text-blue-600 rounded-md hover:bg-blue-50 transition-colors shrink-0 flex items-center justify-center border border-transparent group-hover:border-blue-200"
                        title="Click and drag up or down to reposition column"
                      >
                        <GripVertical className="h-4 w-4" />
                      </div>

                      {/* Position Badge in Excel with Quick Jump Selector */}
                      <div className="flex items-center gap-1 shrink-0" onMouseDown={(e) => e.stopPropagation()}>
                        <span
                          className={`text-xs font-mono font-bold px-2 py-0.5 rounded border ${
                            col.is_active
                              ? "bg-blue-50 text-blue-800 border-blue-200 shadow-2xs"
                              : "bg-gray-100 text-gray-400 border-gray-200 line-through"
                          }`}
                          title={`Excel column position #${originalIndex + 1}`}
                        >
                          #{originalIndex + 1}
                        </span>
                      </div>

                      {/* Instant Move Controls: Up / Down / Top / Bottom */}
                      <div className="flex items-center gap-0.5 shrink-0 bg-gray-100/90 rounded border border-gray-200 p-0.5" onMouseDown={(e) => e.stopPropagation()}>
                        <button
                          type="button"
                          onClick={() => handleMove(originalIndex, "top")}
                          disabled={originalIndex === 0}
                          className="text-gray-500 hover:text-blue-600 hover:bg-white disabled:opacity-20 p-1 rounded transition-colors"
                          title="Move to Top (Position #1)"
                        >
                          <ChevronsUp className="h-3 w-3" />
                        </button>
                        <button
                          type="button"
                          onClick={() => handleMove(originalIndex, "up")}
                          disabled={originalIndex === 0}
                          className="text-gray-600 hover:text-blue-600 hover:bg-white disabled:opacity-20 p-1 rounded transition-colors"
                          title="Move Up 1 position"
                        >
                          <ArrowUp className="h-3 w-3" />
                        </button>
                        <button
                          type="button"
                          onClick={() => handleMove(originalIndex, "down")}
                          disabled={originalIndex === currentColumns.length - 1}
                          className="text-gray-600 hover:text-blue-600 hover:bg-white disabled:opacity-20 p-1 rounded transition-colors"
                          title="Move Down 1 position"
                        >
                          <ArrowDown className="h-3 w-3" />
                        </button>
                        <button
                          type="button"
                          onClick={() => handleMove(originalIndex, "bottom")}
                          disabled={originalIndex === currentColumns.length - 1}
                          className="text-gray-500 hover:text-blue-600 hover:bg-white disabled:opacity-20 p-1 rounded transition-colors"
                          title="Move to Bottom"
                        >
                          <ChevronsDown className="h-3 w-3" />
                        </button>
                      </div>

                      {/* Active toggle */}
                      <div onMouseDown={(e) => e.stopPropagation()} className="shrink-0">
                        <Checkbox
                          id={`col_${col.column_key}`}
                          checked={col.is_active}
                          disabled={col.is_required}
                          onCheckedChange={() => handleToggleActive(originalIndex)}
                          className="cursor-pointer"
                        />
                      </div>

                      {/* Column Label Input */}
                      <div className="flex-1 min-w-0" onMouseDown={(e) => e.stopPropagation()}>
                        <div className="flex items-center gap-2">
                          <Input
                            value={col.display_label}
                            onChange={e => handleLabelChange(originalIndex, e.target.value)}
                            className={`h-7 text-xs font-medium ${
                              col.is_active ? "text-gray-900 bg-white" : "text-gray-500 bg-transparent"
                            }`}
                          />
                          {col.is_required && (
                            <Badge variant="outline" className="text-[10px] text-amber-700 border-amber-300 bg-amber-50 h-5 px-1.5 flex items-center gap-1 shrink-0">
                              <Lock className="h-2.5 w-2.5" /> Required
                            </Badge>
                          )}
                          {col.is_custom && (
                            <Badge variant="outline" className="text-[10px] text-purple-700 border-purple-300 bg-purple-50 h-5 px-1.5 shrink-0">
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
                    <div className="flex items-center gap-2 text-xs shrink-0" onMouseDown={(e) => e.stopPropagation()}>
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

                  {/* Drop Indicator Line (After) */}
                  {isDragOver && dropPosition === "after" && (
                    <div className="absolute -bottom-1.5 left-0 right-0 z-20 flex items-center">
                      <div className="w-2.5 h-2.5 rounded-full bg-blue-600 shadow-sm -ml-1"></div>
                      <div className="h-1 bg-blue-600 flex-1 rounded-full shadow-sm"></div>
                      <div className="w-2.5 h-2.5 rounded-full bg-blue-600 shadow-sm -mr-1"></div>
                    </div>
                  )}
                </div>
              );
            })
          )}
        </div>

        {/* Footer */}
        <DialogFooter className="p-3 sm:p-4 border-t bg-gray-50/95 shrink-0 z-10 flex items-center justify-between sm:justify-between">
          <div className="text-xs text-muted-foreground flex items-center gap-1.5 min-w-0 pr-2">
            <FileSpreadsheet className="h-4 w-4 text-green-600 shrink-0" />
            <span className="truncate">
              {dirtyModules.size > 0 ? (
                <span className="text-amber-700 font-medium">
                  ● Unsaved changes in: {Array.from(dirtyModules).map(m => MODULE_TABS.find(t => t.id === m)?.label).join(", ")}. Switching tabs or clicking Save will persist them.
                </span>
              ) : (
                <span>Changes will reflect instantly on template downloads for <strong>{client?.client_name}</strong>.</span>
              )}
            </span>
          </div>
          <div className="flex items-center gap-2 shrink-0">
            <Button variant="outline" size="sm" onClick={onClose} disabled={saving}>
              Close
            </Button>
            {dirtyModules.size > 1 && (
              <Button
                variant="outline"
                size="sm"
                onClick={handleSaveAll}
                disabled={saving || loading}
                className="border-emerald-600 text-emerald-700 bg-emerald-50 hover:bg-emerald-100 font-medium"
              >
                <CheckCheck className="h-3.5 w-3.5 mr-1 text-emerald-600" />
                Save All Tabs ({dirtyModules.size})
              </Button>
            )}
            <Button
              size="sm"
              onClick={() => handleSaveModule()}
              disabled={saving || loading}
              className={`text-white font-medium shadow-sm transition-all ${
                dirtyModules.has(activeModule)
                  ? "bg-emerald-600 hover:bg-emerald-700"
                  : "bg-blue-600 hover:bg-blue-700"
              }`}
            >
              {saving ? <Loader2 className="h-4 w-4 mr-1.5 animate-spin" /> : <Save className="h-4 w-4 mr-1.5" />}
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
