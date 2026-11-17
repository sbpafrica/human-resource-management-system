import React, { useState } from "react";
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
import { Badge } from "@/components/ui/badge";
import { Checkbox } from "@/components/ui/checkbox";
import {
  Trash2,
  Search,
  AlertTriangle,
  CheckCircle2,
  AlertCircle,
  Loader2,
  FileSpreadsheet,
  Users,
  ShieldAlert,
} from "lucide-react";

interface MatchingRecord {
  staff_id: string;
  name: string;
  company: string;
  position: string;
  in_directory: boolean;
  in_contracts: boolean;
  contract_tables: string[];
  exists_in_db: boolean;
}

interface Props {
  isOpen: boolean;
  onClose: () => void;
  defaultClient?: string;
  clientsList?: string[];
  onDeleted?: () => void;
}

export default function StaffRangeDeleteModal({
  isOpen,
  onClose,
  defaultClient = "",
  clientsList = [],
  onDeleted,
}: Props) {
  const [mode, setMode] = useState<"range" | "list">("range");
  const [selectedClient, setSelectedClient] = useState(defaultClient);

  // Range inputs
  const [prefix, setPrefix] = useState("SBPENM");
  const [startNum, setStartNum] = useState<number | string>(52);
  const [endNum, setEndNum] = useState<number | string>(106);
  const [padLength, setPadLength] = useState<number>(0);

  // List input
  const [idListText, setIdListText] = useState("");

  // Scope: 'contracts' | 'directory' | 'complete'
  const [scope, setScope] = useState<"contracts" | "directory" | "complete">("complete");

  // State for preview
  const [loadingPreview, setLoadingPreview] = useState(false);
  const [previewRecords, setPreviewRecords] = useState<MatchingRecord[]>([]);
  const [selectedIds, setSelectedIds] = useState<Set<string>>(new Set());
  const [previewStats, setPreviewStats] = useState<{
    total: number;
    found: number;
    inDir: number;
    inContracts: number;
  } | null>(null);

  // Execution state
  const [deleting, setDeleting] = useState(false);
  const [feedback, setFeedback] = useState<{ message: string; type: "success" | "error" } | null>(null);
  const [confirmPrompt, setConfirmPrompt] = useState(false);
  const [confirmInput, setConfirmInput] = useState("");

  const handleFetchPreview = async () => {
    setLoadingPreview(true);
    setFeedback(null);
    setConfirmPrompt(false);

    try {
      const payload: any = {
        mode,
        client: selectedClient || undefined,
      };

      if (mode === "range") {
        payload.prefix = prefix.trim();
        payload.start_num = Number(startNum);
        payload.end_num = Number(endNum);
        payload.pad_length = padLength;
      } else {
        payload.ids = idListText;
      }

      const res = await fetch("/staff-management/preview-range", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
          "X-CSRF-TOKEN": (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || "",
        },
        body: JSON.stringify(payload),
      });

      const data = await res.json();
      if (!res.ok) throw new Error(data.message || "Failed to preview staff range.");

      setPreviewRecords(data.records || []);
      setPreviewStats({
        total: data.total_target_ids,
        found: data.found_in_system,
        inDir: data.found_in_directory,
        inContracts: data.found_in_contracts,
      });

      // Default select all matching IDs that actually exist in the database
      const existing = (data.records || [])
        .filter((r: MatchingRecord) => r.exists_in_db)
        .map((r: MatchingRecord) => r.staff_id);
      setSelectedIds(new Set(existing.length > 0 ? existing : (data.records || []).map((r: MatchingRecord) => r.staff_id)));

    } catch (err: any) {
      setFeedback({ message: err.message || "Preview failed.", type: "error" });
    } finally {
      setLoadingPreview(false);
    }
  };

  const handleToggleSelectAll = (check: boolean) => {
    if (check) {
      setSelectedIds(new Set(previewRecords.map(r => r.staff_id)));
    } else {
      setSelectedIds(new Set());
    }
  };

  const handleToggleId = (id: string) => {
    setSelectedIds(prev => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  };

  const handleExecuteDelete = async () => {
    if (selectedIds.size === 0) {
      alert("Please select at least one staff member to delete.");
      return;
    }

    if (confirmInput.trim().toUpperCase() !== "DELETE") {
      alert("Please type 'DELETE' in all caps to confirm.");
      return;
    }

    setDeleting(true);
    setFeedback(null);

    try {
      const res = await fetch("/staff-management/bulk-delete", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
          "X-CSRF-TOKEN": (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || "",
        },
        body: JSON.stringify({
          staff_ids: Array.from(selectedIds),
          scope,
          client: selectedClient || undefined,
        }),
      });

      const data = await res.json();
      if (!res.ok) throw new Error(data.message || "Deletion failed.");

      setFeedback({
        message: data.message || `Successfully deleted ${selectedIds.size} staff records!`,
        type: "success",
      });

      setConfirmPrompt(false);
      setConfirmInput("");
      setPreviewRecords([]);
      setPreviewStats(null);
      setSelectedIds(new Set());

      if (onDeleted) {
        onDeleted();
      }
    } catch (err: any) {
      setFeedback({ message: err.message || "Failed to execute delete.", type: "error" });
    } finally {
      setDeleting(false);
    }
  };

  return (
    <Dialog open={isOpen} onOpenChange={open => !open && onClose()}>
      <DialogContent className="max-w-4xl h-[90vh] max-h-[90vh] flex flex-col p-0 overflow-hidden shadow-2xl rounded-xl border border-gray-200">
        {/* Header */}
        <DialogHeader className="p-5 pb-3 border-b bg-gray-50/90 shrink-0">
          <div className="flex items-center gap-2 text-red-600">
            <Trash2 className="h-5 w-5" />
            <DialogTitle className="text-xl font-bold text-gray-900">
              Bulk & Range Staff Deletion
            </DialogTitle>
          </div>
          <DialogDescription className="text-xs text-muted-foreground">
            Target a numerical range (e.g. SBPENM52 to SBPENM106) or paste a custom list of Staff IDs to delete from Olam, Transsion, or any client.
          </DialogDescription>

          {/* Mode Switcher */}
          <div className="flex items-center gap-2 pt-3">
            <button
              type="button"
              onClick={() => { setMode("range"); setPreviewRecords([]); }}
              className={`px-3 py-1.5 rounded-md text-xs font-medium transition ${
                mode === "range"
                  ? "bg-white text-blue-700 shadow-xs border border-blue-200 font-semibold ring-1 ring-blue-500/20"
                  : "bg-gray-100 text-gray-600 hover:bg-gray-200"
              }`}
            >
              Numerical Range (Prefix + Start to End)
            </button>
            <button
              type="button"
              onClick={() => { setMode("list"); setPreviewRecords([]); }}
              className={`px-3 py-1.5 rounded-md text-xs font-medium transition ${
                mode === "list"
                  ? "bg-white text-blue-700 shadow-xs border border-blue-200 font-semibold ring-1 ring-blue-500/20"
                  : "bg-gray-100 text-gray-600 hover:bg-gray-200"
              }`}
            >
              Custom List / Paste IDs
            </button>
          </div>
        </DialogHeader>

        {/* Input Parameters Box */}
        <div className="px-5 py-3 border-b bg-white shrink-0 space-y-3 text-xs">
          {mode === "range" ? (
            <div className="grid grid-cols-1 sm:grid-cols-4 gap-3 items-end">
              <div>
                <Label className="text-xs font-semibold text-gray-700">Staff ID Prefix</Label>
                <div className="flex items-center gap-1 mt-1">
                  <Input
                    placeholder="e.g. SBPENM, TRN-"
                    value={prefix}
                    onChange={e => setPrefix(e.target.value.toUpperCase())}
                    className="h-8 text-xs font-mono font-bold"
                  />
                </div>
                <div className="flex gap-1 mt-1 text-[10px] text-gray-400">
                  <span>Quick:</span>
                  <button type="button" onClick={() => setPrefix("SBPENM")} className="hover:text-blue-600 underline">SBPENM</button>
                  <span>•</span>
                  <button type="button" onClick={() => setPrefix("TRN-")} className="hover:text-blue-600 underline">TRN-</button>
                  <span>•</span>
                  <button type="button" onClick={() => setPrefix("BUNGCI")} className="hover:text-blue-600 underline">BUNGCI</button>
                </div>
              </div>

              <div>
                <Label className="text-xs font-semibold text-gray-700">Start Number</Label>
                <Input
                  type="number"
                  min="0"
                  value={startNum}
                  onChange={e => setStartNum(e.target.value)}
                  className="h-8 text-xs mt-1"
                />
              </div>

              <div>
                <Label className="text-xs font-semibold text-gray-700">End Number</Label>
                <Input
                  type="number"
                  min="0"
                  value={endNum}
                  onChange={e => setEndNum(e.target.value)}
                  className="h-8 text-xs mt-1"
                />
              </div>

              <div>
                <Button
                  size="sm"
                  onClick={handleFetchPreview}
                  disabled={loadingPreview || !prefix.trim()}
                  className="h-8 text-xs w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold"
                >
                  {loadingPreview ? <Loader2 className="h-3.5 w-3.5 animate-spin mr-1" /> : <Search className="h-3.5 w-3.5 mr-1" />}
                  Preview Matching Staff
                </Button>
              </div>
            </div>
          ) : (
            <div className="space-y-2">
              <Label className="text-xs font-semibold text-gray-700">
                Paste Staff IDs (separated by commas, spaces, or newlines)
              </Label>
              <textarea
                rows={3}
                placeholder="SBPENM52&#10;SBPENM53&#10;SBPENM54..."
                value={idListText}
                onChange={e => setIdListText(e.target.value)}
                className="w-full text-xs font-mono p-2 border rounded-md border-gray-300 focus:outline-blue-500"
              />
              <div className="flex justify-end">
                <Button
                  size="sm"
                  onClick={handleFetchPreview}
                  disabled={loadingPreview || !idListText.trim()}
                  className="h-8 text-xs bg-blue-600 hover:bg-blue-700 text-white font-semibold"
                >
                  {loadingPreview ? <Loader2 className="h-3.5 w-3.5 animate-spin mr-1" /> : <Search className="h-3.5 w-3.5 mr-1" />}
                  Preview Matching Staff
                </Button>
              </div>
            </div>
          )}

          {/* Scope Selector */}
          <div className="pt-2 border-t flex flex-wrap items-center justify-between gap-3 text-xs">
            <span className="font-semibold text-gray-700 flex items-center gap-1.5">
              <span>Deletion Scope:</span>
            </span>
            <div className="flex items-center gap-3">
              <label className="flex items-center gap-1.5 cursor-pointer">
                <input
                  type="radio"
                  name="scope"
                  value="complete"
                  checked={scope === "complete"}
                  onChange={() => setScope("complete")}
                  className="text-red-600 focus:ring-red-500"
                />
                <span className="font-medium text-red-700">Complete Purge (Contracts + Directory + Bank + Pin)</span>
              </label>

              <label className="flex items-center gap-1.5 cursor-pointer">
                <input
                  type="radio"
                  name="scope"
                  value="contracts"
                  checked={scope === "contracts"}
                  onChange={() => setScope("contracts")}
                  className="text-blue-600 focus:ring-blue-500"
                />
                <span className="text-gray-700">Client Contracts Only</span>
              </label>

              <label className="flex items-center gap-1.5 cursor-pointer">
                <input
                  type="radio"
                  name="scope"
                  value="directory"
                  checked={scope === "directory"}
                  onChange={() => setScope("directory")}
                  className="text-gray-600 focus:ring-gray-500"
                />
                <span className="text-gray-700">Directory Only (newemployeedata)</span>
              </label>
            </div>
          </div>
        </div>

        {/* Feedback message banner */}
        {feedback && (
          <div
            className={`px-5 py-2 text-xs flex items-center justify-between border-b ${
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
            <button onClick={() => setFeedback(null)} className="text-gray-400 hover:text-gray-600 font-bold">×</button>
          </div>
        )}

        {/* Preview Stats Bar */}
        {previewStats && (
          <div className="px-5 py-2 bg-gray-50 shrink-0 border-b flex items-center justify-between text-xs">
            <div className="flex items-center gap-3">
              <span className="font-medium">
                Target IDs: <strong>{previewStats.total}</strong>
              </span>
              <span>•</span>
              <span className="text-green-700 font-medium">
                Found in System: <strong>{previewStats.found}</strong>
              </span>
              <span>•</span>
              <span className="text-blue-700">
                In Directory: <strong>{previewStats.inDir}</strong>
              </span>
              <span>•</span>
              <span className="text-purple-700">
                In Contracts: <strong>{previewStats.inContracts}</strong>
              </span>
            </div>

            <div className="flex items-center gap-2">
              <Button
                variant="outline"
                size="sm"
                className="h-6 text-[11px] px-2"
                onClick={() => handleToggleSelectAll(true)}
              >
                Select All ({previewRecords.length})
              </Button>
              <Button
                variant="outline"
                size="sm"
                className="h-6 text-[11px] px-2"
                onClick={() => handleToggleSelectAll(false)}
              >
                Clear Selection
              </Button>
            </div>
          </div>
        )}

        {/* Scrollable Results Table */}
        <div className="flex-1 min-h-0 overflow-y-auto px-5 py-2">
          {loadingPreview ? (
            <div className="flex flex-col items-center justify-center h-48 gap-3 text-muted-foreground">
              <Loader2 className="h-6 w-6 animate-spin text-blue-600" />
              <p className="text-xs">Scanning employee directory and contract tables...</p>
            </div>
          ) : previewRecords.length === 0 ? (
            <div className="flex flex-col items-center justify-center h-48 gap-2 text-muted-foreground text-center">
              <Users className="h-8 w-8 text-gray-300" />
              <p className="text-xs">No records previewed yet.</p>
              <p className="text-[11px] text-gray-400">
                Enter your ID range above (e.g. SBPENM 52 to 106) and click &ldquo;Preview Matching Staff&rdquo;.
              </p>
            </div>
          ) : (
            <div className="border rounded-md overflow-hidden bg-white">
              <table className="w-full text-left text-xs border-collapse">
                <thead className="bg-gray-100 text-gray-700 font-semibold border-b">
                  <tr>
                    <th className="p-2 w-8 text-center">
                      <Checkbox
                        checked={selectedIds.size > 0 && selectedIds.size === previewRecords.length}
                        onCheckedChange={checked => handleToggleSelectAll(!!checked)}
                      />
                    </th>
                    <th className="p-2">Staff ID</th>
                    <th className="p-2">Name</th>
                    <th className="p-2">Company / Client</th>
                    <th className="p-2">Position</th>
                    <th className="p-2 text-right">Locations</th>
                  </tr>
                </thead>
                <tbody className="divide-y">
                  {previewRecords.map(rec => {
                    const isSelected = selectedIds.has(rec.staff_id);
                    return (
                      <tr
                        key={rec.staff_id}
                        className={`transition ${isSelected ? "bg-red-50/50" : "hover:bg-gray-50"} ${
                          !rec.exists_in_db ? "opacity-50" : ""
                        }`}
                      >
                        <td className="p-2 text-center">
                          <Checkbox
                            checked={isSelected}
                            onCheckedChange={() => handleToggleId(rec.staff_id)}
                          />
                        </td>
                        <td className="p-2 font-mono font-bold text-gray-900">{rec.staff_id}</td>
                        <td className="p-2 font-medium">{rec.name}</td>
                        <td className="p-2 text-gray-600">{rec.company}</td>
                        <td className="p-2 text-gray-500">{rec.position}</td>
                        <td className="p-2 text-right space-x-1">
                          {rec.in_directory && (
                            <Badge variant="outline" className="text-[10px] bg-blue-50 text-blue-700 border-blue-200">
                              Directory
                            </Badge>
                          )}
                          {rec.in_contracts && (
                            <Badge variant="outline" className="text-[10px] bg-purple-50 text-purple-700 border-purple-200">
                              Contracts
                            </Badge>
                          )}
                          {!rec.exists_in_db && (
                            <Badge variant="outline" className="text-[10px] text-gray-400 border-gray-200">
                              Not in DB
                            </Badge>
                          )}
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          )}
        </div>

        {/* Confirmation Step if clicked Delete */}
        {confirmPrompt && (
          <div className="p-4 bg-red-100 border-t border-red-300 text-xs text-red-950 flex flex-col sm:flex-row items-center justify-between gap-3 shrink-0">
            <div className="flex items-center gap-2">
              <ShieldAlert className="h-5 w-5 text-red-600 shrink-0" />
              <div>
                <p className="font-bold">Are you absolutely sure you want to delete {selectedIds.size} staff?</p>
                <p className="text-[11px] text-red-800">
                  Scope: <strong>{scope.toUpperCase()}</strong>. This operation cannot be undone. Type <strong>DELETE</strong> below to confirm.
                </p>
              </div>
            </div>
            <div className="flex items-center gap-2 w-full sm:w-auto">
              <Input
                placeholder="Type DELETE"
                value={confirmInput}
                onChange={e => setConfirmInput(e.target.value)}
                className="h-8 w-28 text-xs font-mono uppercase bg-white border-red-400"
                autoFocus
              />
              <Button
                size="sm"
                variant="destructive"
                disabled={deleting || confirmInput.trim().toUpperCase() !== "DELETE"}
                onClick={handleExecuteDelete}
                className="h-8 text-xs px-3 font-semibold"
              >
                {deleting && <Loader2 className="h-3 w-3 animate-spin mr-1" />}
                Confirm Delete
              </Button>
              <Button
                size="sm"
                variant="outline"
                onClick={() => setConfirmPrompt(false)}
                className="h-8 text-xs"
              >
                Cancel
              </Button>
            </div>
          </div>
        )}

        {/* Footer */}
        <DialogFooter className="p-3 sm:p-4 border-t bg-gray-50/95 shrink-0 z-10 flex items-center justify-between sm:justify-between">
          <div className="text-xs text-muted-foreground flex items-center gap-1.5">
            <Users className="h-4 w-4 text-gray-500 shrink-0" />
            <span>
              Selected for deletion: <strong className="text-red-600 font-bold">{selectedIds.size}</strong> staff
            </span>
          </div>

          <div className="flex items-center gap-2 shrink-0">
            <Button variant="outline" size="sm" onClick={onClose} disabled={deleting}>
              Close
            </Button>
            {!confirmPrompt && (
              <Button
                size="sm"
                variant="destructive"
                disabled={deleting || selectedIds.size === 0}
                onClick={() => setConfirmPrompt(true)}
                className="h-8 text-xs px-3 font-semibold shadow-sm"
              >
                <Trash2 className="h-3.5 w-3.5 mr-1" />
                Delete Selected ({selectedIds.size})
              </Button>
            )}
          </div>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
