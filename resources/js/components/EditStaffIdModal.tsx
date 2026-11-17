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
import { Badge } from "@/components/ui/badge";
import {
  KeyRound,
  Wand2,
  CheckCircle2,
  AlertCircle,
  Loader2,
  RefreshCw,
  Layers,
} from "lucide-react";

interface Props {
  isOpen: boolean;
  onClose: () => void;
  staffId?: string;
  employeeName?: string;
  companyName?: string;
  onRenamed?: (oldId: string, newId: string) => void;
}

export default function EditStaffIdModal({
  isOpen,
  onClose,
  staffId = "",
  employeeName = "",
  companyName = "",
  onRenamed,
}: Props) {
  const [currentId, setCurrentId] = useState(staffId);
  const [newId, setNewId] = useState("");
  const [loadingSuggestion, setLoadingSuggestion] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [feedback, setFeedback] = useState<{ message: string; type: "success" | "error" } | null>(null);

  useEffect(() => {
    if (isOpen) {
      setCurrentId(staffId);
      setNewId("");
      setFeedback(null);
    }
  }, [isOpen, staffId]);

  const handleSuggestNextId = async () => {
    setLoadingSuggestion(true);
    try {
      const res = await fetch(`/staff-management/next-id?client=${encodeURIComponent(companyName || "")}`);
      const data = await res.json();
      if (data.success && data.next_id) {
        setNewId(data.next_id);
      }
    } catch (e) {
      // Silent catch
    } finally {
      setLoadingSuggestion(false);
    }
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!currentId.trim() || !newId.trim()) return;

    if (currentId.trim().toUpperCase() === newId.trim().toUpperCase()) {
      alert("New Staff ID must be different from the current Staff ID.");
      return;
    }

    setSubmitting(true);
    setFeedback(null);

    try {
      const res = await fetch("/staff-management/rename-id", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
          "X-CSRF-TOKEN": (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || "",
        },
        body: JSON.stringify({
          old_staff_id: currentId.trim(),
          new_staff_id: newId.trim(),
        }),
      });

      const data = await res.json();
      if (!res.ok) throw new Error(data.message || "Failed to rename Staff ID.");

      setFeedback({
        message: data.message || `Staff ID successfully updated to ${newId}!`,
        type: "success",
      });

      if (onRenamed) {
        onRenamed(currentId.trim(), newId.trim());
      }
    } catch (err: any) {
      setFeedback({ message: err.message || "Rename failed.", type: "error" });
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <Dialog open={isOpen} onOpenChange={open => !open && onClose()}>
      <DialogContent className="max-w-md p-0 overflow-hidden shadow-xl rounded-xl border border-gray-200">
        <DialogHeader className="p-5 pb-3 border-b bg-gray-50/90">
          <div className="flex items-center gap-2 text-blue-600">
            <KeyRound className="h-5 w-5" />
            <DialogTitle className="text-lg font-bold text-gray-900">
              Rename Staff ID
            </DialogTitle>
          </div>
          <DialogDescription className="text-xs text-muted-foreground">
            Safely updates this ID across all tables: Employee Directory, Contracts, Bank Accounts, Phone, PIN, and Leaves.
          </DialogDescription>
        </DialogHeader>

        <form onSubmit={handleSubmit} className="p-5 space-y-4 text-xs">
          {/* Employee summary card */}
          <div className="p-3 rounded-lg border bg-blue-50/50 border-blue-200 space-y-1">
            <div className="flex items-center justify-between">
              <span className="font-semibold text-gray-800 text-sm">{employeeName || "Employee"}</span>
              <Badge variant="outline" className="text-[10px] bg-white border-blue-300 text-blue-700">
                {companyName || "Client"}
              </Badge>
            </div>
            <div className="text-[11px] text-gray-500 font-mono">
              Current ID: <strong>{currentId}</strong>
            </div>
          </div>

          <div>
            <Label className="text-xs font-semibold text-gray-700">Current Staff ID</Label>
            <Input
              value={currentId}
              onChange={e => setCurrentId(e.target.value)}
              required
              className="mt-1 font-mono font-bold bg-gray-50"
              placeholder="e.g. SBPENM52"
            />
          </div>

          <div>
            <div className="flex items-center justify-between">
              <Label className="text-xs font-semibold text-gray-700">New Staff ID *</Label>
              <button
                type="button"
                onClick={handleSuggestNextId}
                disabled={loadingSuggestion}
                className="text-[11px] text-blue-600 hover:text-blue-800 flex items-center gap-1 font-medium"
                title="Automatically detect next sequential ID for this company"
              >
                {loadingSuggestion ? <Loader2 className="h-3 w-3 animate-spin" /> : <Wand2 className="h-3 w-3" />}
                Suggest Next ID
              </button>
            </div>
            <Input
              value={newId}
              onChange={e => setNewId(e.target.value.toUpperCase())}
              required
              className="mt-1 font-mono font-bold text-blue-700 focus:ring-blue-500"
              placeholder="e.g. SBPENM107"
              autoFocus
            />
          </div>

          <div className="p-2.5 rounded-md bg-amber-50 border border-amber-200 text-amber-800 text-[11px] flex items-start gap-2">
            <Layers className="h-4 w-4 shrink-0 text-amber-600 mt-0.5" />
            <span>
              All historical records, active contracts, and payslip data will automatically link to the new ID.
            </span>
          </div>

          {/* Feedback banner */}
          {feedback && (
            <div
              className={`p-2.5 rounded-md text-xs flex items-center justify-between border ${
                feedback.type === "success"
                  ? "bg-green-50 text-green-800 border-green-200"
                  : "bg-red-50 text-red-800 border-red-200"
              }`}
            >
              <div className="flex items-center gap-2">
                {feedback.type === "success" ? (
                  <CheckCircle2 className="h-4 w-4 text-green-600 shrink-0" />
                ) : (
                  <AlertCircle className="h-4 w-4 text-red-600 shrink-0" />
                )}
                <span>{feedback.message}</span>
              </div>
            </div>
          )}

          <DialogFooter className="pt-2">
            <Button type="button" variant="outline" size="sm" onClick={onClose} disabled={submitting}>
              Cancel
            </Button>
            <Button
              type="submit"
              size="sm"
              disabled={submitting || !newId.trim() || currentId === newId}
              className="bg-blue-600 hover:bg-blue-700 text-white font-semibold"
            >
              {submitting ? <Loader2 className="h-3.5 w-3.5 animate-spin mr-1" /> : <RefreshCw className="h-3.5 w-3.5 mr-1" />}
              Update Staff ID
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
