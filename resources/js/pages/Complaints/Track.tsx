import React, { useState } from "react";
import { Head, Link, router, usePage } from "@inertiajs/react";
import {
  LifeBuoy,
  Search,
  CheckCircle2,
  Clock,
  AlertCircle,
  FileText,
  User,
  Building2,
  Calendar,
  ArrowLeft,
  RefreshCw,
  PlusCircle,
} from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";

interface ComplaintItem {
  id: number;
  ticket_number: string;
  client_name: string;
  staff_id: string;
  employee_name: string;
  shop_name: string | null;
  month_period: string | null;
  category: string;
  complaint_text: string;
  status: "pending" | "under_review" | "resolved" | "rejected";
  priority: string;
  resolution_notes: string | null;
  resolved_at: string | null;
  created_at: string;
}

interface Props {
  ticketQuery: string;
  complaint: ComplaintItem | null;
}

export default function ComplaintTrack({ ticketQuery, complaint }: Props) {
  const { flash } = usePage().props as any;
  const [query, setQuery] = useState(ticketQuery || "");
  const [searching, setSearching] = useState(false);

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    if (!query.trim()) return;
    setSearching(true);
    router.get(
      "/complaints/track",
      { ticket: query.trim() },
      {
        preserveScroll: true,
        onFinish: () => setSearching(false),
      }
    );
  };

  return (
    <div className="min-h-screen bg-slate-50 text-slate-900 py-8 px-4 sm:px-6 lg:px-8">
      <Head title="Track Complaint Ticket — SBP Africa" />

      <div className="max-w-2xl mx-auto space-y-6">
        {/* Flash banner if redirected from submission */}
        {flash?.success && (
          <div className="p-4 bg-emerald-50 border border-emerald-200 text-emerald-900 rounded-2xl flex items-start space-x-3 shadow-sm">
            <CheckCircle2 className="w-5 h-5 text-emerald-600 flex-shrink-0 mt-0.5" />
            <div className="text-sm">
              <strong className="block font-semibold">Success!</strong>
              {flash.success}
            </div>
          </div>
        )}

        {/* Header */}
        <div className="text-center space-y-2">
          <div className="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-blue-600 text-white shadow-md shadow-blue-500/20 mb-2">
            <LifeBuoy className="w-8 h-8" />
          </div>
          <h1 className="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900">
            Track Complaint Status
          </h1>
          <p className="text-sm text-slate-600 max-w-md mx-auto">
            Enter your Ticket Number (e.g. <code>CMP-202611-0001</code>) or your Staff ID to view live resolution updates.
          </p>
        </div>

        {/* Search Bar */}
        <div className="bg-white rounded-2xl shadow-sm border border-slate-200 p-4 sm:p-6">
          <form onSubmit={handleSearch} className="flex gap-2">
            <div className="relative flex-1">
              <Search className="w-4 h-4 text-slate-400 absolute left-3 top-3.5" />
              <Input
                type="text"
                placeholder="Enter Ticket Number or Staff ID..."
                value={query}
                onChange={(e) => setQuery(e.target.value)}
                className="pl-9 font-mono uppercase text-sm sm:text-base h-11"
                required
              />
            </div>
            <Button
              type="submit"
              disabled={searching}
              className="bg-blue-600 hover:bg-blue-700 text-white font-semibold h-11 px-5 rounded-xl shadow-sm flex items-center gap-2"
            >
              {searching ? <RefreshCw className="w-4 h-4 animate-spin" /> : "Track Ticket"}
            </Button>
          </form>
        </div>

        {/* Results Card */}
        {complaint ? (
          <div className="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden space-y-6 p-6 sm:p-8">
            {/* Ticket Header & Status Pill */}
            <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 border-b border-slate-100 pb-4">
              <div>
                <span className="text-xs font-bold uppercase tracking-wider text-slate-400">
                  Ticket Number
                </span>
                <h2 className="text-xl font-mono font-extrabold text-blue-700">
                  {complaint.ticket_number}
                </h2>
              </div>

              <div>
                {complaint.status === "resolved" ? (
                  <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800">
                    <CheckCircle2 className="w-4 h-4 text-emerald-600" />
                    Resolved
                  </span>
                ) : complaint.status === "under_review" ? (
                  <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-blue-100 text-blue-800">
                    <Clock className="w-4 h-4 text-blue-600" />
                    Under Review by HR
                  </span>
                ) : complaint.status === "rejected" ? (
                  <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-rose-100 text-rose-800">
                    <AlertCircle className="w-4 h-4 text-rose-600" />
                    Rejected / Closed
                  </span>
                ) : (
                  <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800">
                    <Clock className="w-4 h-4 text-amber-600" />
                    Pending Review
                  </span>
                )}
              </div>
            </div>

            {/* Employee & Meta Details */}
            <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 p-4 bg-slate-50 rounded-xl text-xs">
              <div>
                <span className="text-slate-400 block font-medium">Employee</span>
                <span className="font-bold text-slate-900 mt-0.5 block">
                  {complaint.employee_name}
                </span>
              </div>
              <div>
                <span className="text-slate-400 block font-medium">Staff ID</span>
                <span className="font-mono font-bold text-slate-900 mt-0.5 block">
                  {complaint.staff_id}
                </span>
              </div>
              <div>
                <span className="text-slate-400 block font-medium">Company</span>
                <span className="font-bold text-slate-900 mt-0.5 block">
                  {complaint.client_name}
                </span>
              </div>
              <div>
                <span className="text-slate-400 block font-medium">Issue Month</span>
                <span className="font-bold text-slate-900 mt-0.5 block">
                  {complaint.month_period || "—"}
                </span>
              </div>
            </div>

            {/* Complaint Text */}
            <div className="space-y-1.5">
              <span className="text-xs font-bold uppercase tracking-wider text-slate-500">
                Category: <strong className="text-blue-700">{complaint.category}</strong>
              </span>
              <div className="p-4 bg-slate-50/70 border border-slate-100 rounded-xl text-sm text-slate-800 leading-relaxed">
                {complaint.complaint_text}
              </div>
            </div>

            {/* Resolution Note if available */}
            {complaint.resolution_notes && (
              <div className="p-4 bg-emerald-50 border border-emerald-200 rounded-xl space-y-1 text-sm text-emerald-950">
                <span className="text-xs font-bold uppercase tracking-wider text-emerald-800 flex items-center gap-1.5">
                  <CheckCircle2 className="w-4 h-4 text-emerald-600" />
                  HR Resolution Feedback:
                </span>
                <p className="whitespace-pre-line text-emerald-900 mt-1">
                  {complaint.resolution_notes}
                </p>
                {complaint.resolved_at && (
                  <span className="text-[11px] text-emerald-700 block mt-2">
                    Resolved on: {new Date(complaint.resolved_at).toLocaleDateString()}
                  </span>
                )}
              </div>
            )}

            {/* Timestamps */}
            <div className="text-xs text-slate-400 pt-2 flex justify-between items-center border-t border-slate-100">
              <span>Submitted on: {new Date(complaint.created_at).toLocaleDateString()}</span>
              {complaint.shop_name && <span>Location: {complaint.shop_name}</span>}
            </div>
          </div>
        ) : query && !searching ? (
          <div className="bg-white rounded-2xl shadow-sm border border-slate-200 p-8 text-center space-y-3">
            <AlertCircle className="w-10 h-10 text-slate-300 mx-auto" />
            <h3 className="text-base font-bold text-slate-800">No Complaint Found</h3>
            <p className="text-xs text-slate-500 max-w-sm mx-auto">
              We couldn't find any complaint matching <strong>"{query}"</strong>. Please verify your Ticket Number or Staff ID.
            </p>
          </div>
        ) : null}

        {/* Quick actions */}
        <div className="flex justify-between items-center pt-2">
          <Link
            href="/complaints/submit"
            className="inline-flex items-center gap-1.5 text-xs sm:text-sm font-semibold text-blue-600 hover:text-blue-700 hover:underline"
          >
            <PlusCircle className="w-4 h-4" />
            Submit a new complaint
          </Link>
          <Link
            href="/login"
            className="text-xs text-slate-400 hover:text-slate-600 hover:underline"
          >
            HR Portal Login &rarr;
          </Link>
        </div>
      </div>
    </div>
  );
}
