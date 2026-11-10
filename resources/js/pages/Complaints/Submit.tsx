import React, { useState, useEffect, useCallback } from "react";
import { Head, Link, router } from "@inertiajs/react";
import {
  LifeBuoy,
  CheckCircle2,
  AlertCircle,
  Search,
  Send,
  Building2,
  Calendar,
  FileText,
  Phone,
  Mail,
  Paperclip,
  ArrowRight,
  ShieldCheck,
  RefreshCw,
} from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";

interface ClientOption {
  id: number;
  client_name: string;
  short_name: string;
}

interface Props {
  clients: ClientOption[];
  months: string[];
  categories: string[];
  prefillClient?: string;
}

export default function ComplaintSubmit({ clients, months, categories, prefillClient = "" }: Props) {
  const [staffId, setStaffId] = useState("");
  const [employeeName, setEmployeeName] = useState("");
  const [clientName, setClientName] = useState(prefillClient || "");
  const [shopName, setShopName] = useState("");
  const [phone, setPhone] = useState("");
  const [email, setEmail] = useState("");
  const [monthPeriod, setMonthPeriod] = useState(months[0] || "");
  const [category, setCategory] = useState(categories[0] || "");
  const [complaintText, setComplaintText] = useState("");
  const [attachment, setAttachment] = useState<File | null>(null);

  const [verifying, setVerifying] = useState(false);
  const [verified, setVerified] = useState<boolean | null>(null);
  const [verifyMsg, setVerifyMsg] = useState("");
  const [submitting, setSubmitting] = useState(false);
  const [errors, setErrors] = useState<Record<string, string>>({});

  const handleVerifyStaff = useCallback(async (idToVerify?: string) => {
    const targetId = (idToVerify !== undefined ? idToVerify : staffId).trim();
    if (!targetId || targetId.length < 2) {
      setVerified(null);
      setVerifyMsg("");
      return;
    }
    setVerifying(true);
    setVerifyMsg("");
    try {
      const res = await fetch(`/complaints/verify-staff?staff_id=${encodeURIComponent(targetId)}`, {
        headers: { "Accept": "application/json" }
      });
      const data = await res.json();
      if (data && data.found) {
        setVerified(true);
        if (data.employee_name) setEmployeeName(data.employee_name);
        if (data.client_name) {
          // If a client match exists in clients prop, match its exact name
          const matched = clients.find(
            (c) =>
              c.client_name.toLowerCase() === data.client_name.toLowerCase() ||
              (c.short_name && c.short_name.toLowerCase() === data.client_name.toLowerCase())
          );
          setClientName(matched ? matched.client_name : data.client_name);
        }
        if (data.shop_name) setShopName(data.shop_name);
        if (data.phone) setPhone(data.phone);
        if (data.email) setEmail(data.email);
        setVerifyMsg(`✓ Verified: ${data.employee_name} (${data.client_name || "Employee"})`);
      } else {
        setVerified(false);
        setVerifyMsg("Staff ID not found in master records. You can still proceed by entering your details manually.");
      }
    } catch (err) {
      console.warn(err);
      setVerified(false);
      setVerifyMsg("Could not verify Staff ID automatically. Please fill your details manually.");
    } finally {
      setVerifying(false);
    }
  }, [staffId, clients]);

  // Debounced auto-verify when typing Staff ID
  useEffect(() => {
    const clean = staffId.trim();
    if (clean.length < 3) {
      setVerified(null);
      setVerifyMsg("");
      return;
    }
    const timer = setTimeout(() => {
      handleVerifyStaff(clean);
    }, 500);
    return () => clearTimeout(timer);
  }, [staffId, handleVerifyStaff]);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setErrors({});

    const newErrors: Record<string, string> = {};
    if (!staffId.trim()) newErrors.staff_id = "Staff ID is required.";
    if (!employeeName.trim()) newErrors.employee_name = "Employee name is required.";
    if (!clientName.trim()) newErrors.client_name = "Client / Company is required.";
    if (!phone.trim()) newErrors.phone = "Contact phone number is required.";
    if (!complaintText.trim() || complaintText.trim().length < 10) {
      newErrors.complaint_text = "Please write a detailed explanation (at least 10 characters).";
    }

    if (Object.keys(newErrors).length > 0) {
      setErrors(newErrors);
      return;
    }

    const formData = new FormData();
    formData.append("staff_id", staffId.trim());
    formData.append("employee_name", employeeName.trim());
    formData.append("client_name", clientName);
    formData.append("shop_name", shopName.trim());
    formData.append("phone", phone.trim());
    formData.append("email", email.trim());
    formData.append("month_period", monthPeriod);
    formData.append("category", category);
    formData.append("complaint_text", complaintText.trim());
    if (attachment) {
      formData.append("attachment", attachment);
    }

    setSubmitting(true);
    router.post("/complaints/submit", formData, {
      onError: (errs) => {
        setErrors(errs);
        setSubmitting(false);
      },
      onFinish: () => setSubmitting(false),
    });
  };

  return (
    <div className="min-h-screen bg-slate-50 text-slate-900 py-8 px-4 sm:px-6 lg:px-8">
      <Head title="Employee Complaint & Query Portal — SBP Africa" />

      <div className="max-w-2xl mx-auto space-y-6">
        {/* Header Branding */}
        <div className="text-center space-y-2">
          <div className="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-blue-600 text-white shadow-md shadow-blue-500/20 mb-2">
            <LifeBuoy className="w-8 h-8" />
          </div>
          <h1 className="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900">
            SBP Africa Employee Support
          </h1>
          <p className="text-sm sm:text-base text-slate-600 max-w-lg mx-auto">
            Submit a query or complaint regarding your salary, attendance, deductions, or benefits. Our HR team will review and resolve it promptly.
          </p>
          <div className="pt-1">
            <Link
              href="/complaints/track"
              className="inline-flex items-center gap-1.5 text-xs sm:text-sm font-semibold text-blue-600 hover:text-blue-700 hover:underline"
            >
              Already submitted? Track your complaint status here
              <ArrowRight className="w-3.5 h-3.5" />
            </Link>
          </div>
        </div>

        {/* Main Form Card */}
        <div className="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 sm:p-8 space-y-6">
          <form onSubmit={handleSubmit} className="space-y-5">
            {/* Staff ID & Auto Verification */}
            <div className="space-y-1.5">
              <Label className="text-xs font-bold uppercase tracking-wider text-slate-700">
                Staff ID <span className="text-rose-500">*</span>
              </Label>
              <div className="flex gap-2">
                <Input
                  type="text"
                  placeholder="e.g. SBP1106, SBP4143"
                  value={staffId}
                  onChange={(e) => {
                    setStaffId(e.target.value);
                    setVerified(null);
                    setVerifyMsg("");
                  }}
                  onBlur={() => {
                    if (staffId.trim() && verified === null) {
                      handleVerifyStaff();
                    }
                  }}
                  className="font-mono uppercase text-base"
                  required
                />
                <Button
                  type="button"
                  variant="outline"
                  onClick={handleVerifyStaff}
                  disabled={verifying || !staffId.trim()}
                  className="flex items-center gap-1.5 text-xs font-semibold px-4"
                >
                  {verifying ? (
                    <RefreshCw className="w-3.5 h-3.5 animate-spin" />
                  ) : (
                    <Search className="w-3.5 h-3.5" />
                  )}
                  Verify ID
                </Button>
              </div>

              {verifyMsg && (
                <div
                  className={`text-xs p-2 rounded-lg flex items-center gap-2 mt-1.5 ${
                    verified
                      ? "bg-emerald-50 text-emerald-800 border border-emerald-200"
                      : "bg-amber-50 text-amber-800 border border-amber-200"
                  }`}
                >
                  {verified ? (
                    <ShieldCheck className="w-4 h-4 text-emerald-600 flex-shrink-0" />
                  ) : (
                    <AlertCircle className="w-4 h-4 text-amber-600 flex-shrink-0" />
                  )}
                  <span>{verifyMsg}</span>
                </div>
              )}
              {errors.staff_id && (
                <p className="text-xs text-rose-500">{errors.staff_id}</p>
              )}
            </div>

            {/* Employee Full Name */}
            <div className="space-y-1.5">
              <Label className="text-xs font-bold uppercase tracking-wider text-slate-700">
                Employee Full Name <span className="text-rose-500">*</span>
              </Label>
              <Input
                type="text"
                placeholder="Enter your full name"
                value={employeeName}
                onChange={(e) => setEmployeeName(e.target.value)}
                required
              />
              {errors.employee_name && (
                <p className="text-xs text-rose-500">{errors.employee_name}</p>
              )}
            </div>

            {/* Client / Company & Branch / Shop */}
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div className="space-y-1.5">
                <Label className="text-xs font-bold uppercase tracking-wider text-slate-700">
                  Client / Company <span className="text-rose-500">*</span>
                </Label>
                <select
                  value={clientName}
                  onChange={(e) => setClientName(e.target.value)}
                  className="w-full h-10 px-3 py-2 text-sm bg-white border border-slate-200 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                  required
                >
                  <option value="">Select your company</option>
                  {clients.map((c) => (
                    <option key={c.id} value={c.client_name}>
                      {c.client_name}
                    </option>
                  ))}
                  {clientName &&
                    !clients.some(
                      (c) =>
                        c.client_name.toLowerCase() === clientName.toLowerCase() ||
                        (c.short_name && c.short_name.toLowerCase() === clientName.toLowerCase())
                    ) &&
                    clientName.toLowerCase() !== "melcom" && (
                      <option value={clientName}>{clientName}</option>
                    )}
                  <option value="Melcom">Melcom</option>
                </select>
                {errors.client_name && (
                  <p className="text-xs text-rose-500">{errors.client_name}</p>
                )}
              </div>

              <div className="space-y-1.5">
                <Label className="text-xs font-bold uppercase tracking-wider text-slate-700">
                  Shop / Branch / Location
                </Label>
                <Input
                  type="text"
                  placeholder="e.g. WA Shop, Kumasi 2, Tema Warehouse"
                  value={shopName}
                  onChange={(e) => setShopName(e.target.value)}
                />
              </div>
            </div>

            {/* Phone & Email */}
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div className="space-y-1.5">
                <Label className="text-xs font-bold uppercase tracking-wider text-slate-700">
                  Phone Number <span className="text-rose-500">*</span>
                </Label>
                <div className="relative">
                  <Phone className="w-4 h-4 text-slate-400 absolute left-3 top-3" />
                  <Input
                    type="tel"
                    placeholder="024XXXXXXX"
                    value={phone}
                    onChange={(e) => setPhone(e.target.value)}
                    className="pl-9"
                    required
                  />
                </div>
                {errors.phone && (
                  <p className="text-xs text-rose-500">{errors.phone}</p>
                )}
              </div>

              <div className="space-y-1.5">
                <Label className="text-xs font-bold uppercase tracking-wider text-slate-700">
                  Email Address (Optional)
                </Label>
                <div className="relative">
                  <Mail className="w-4 h-4 text-slate-400 absolute left-3 top-3" />
                  <Input
                    type="email"
                    placeholder="your.email@example.com"
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                    className="pl-9"
                  />
                </div>
              </div>
            </div>

            {/* Month Period & Category */}
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div className="space-y-1.5">
                <Label className="text-xs font-bold uppercase tracking-wider text-slate-700">
                  Month of Issue <span className="text-rose-500">*</span>
                </Label>
                <select
                  value={monthPeriod}
                  onChange={(e) => setMonthPeriod(e.target.value)}
                  className="w-full h-10 px-3 py-2 text-sm bg-white border border-slate-200 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                  required
                >
                  {months.map((m) => (
                    <option key={m} value={m}>
                      {m}
                    </option>
                  ))}
                </select>
              </div>

              <div className="space-y-1.5">
                <Label className="text-xs font-bold uppercase tracking-wider text-slate-700">
                  Category <span className="text-rose-500">*</span>
                </Label>
                <select
                  value={category}
                  onChange={(e) => setCategory(e.target.value)}
                  className="w-full h-10 px-3 py-2 text-sm bg-white border border-slate-200 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                  required
                >
                  {categories.map((c) => (
                    <option key={c} value={c}>
                      {c}
                    </option>
                  ))}
                </select>
              </div>
            </div>

            {/* Detailed Complaint Text */}
            <div className="space-y-1.5">
              <Label className="text-xs font-bold uppercase tracking-wider text-slate-700">
                Detailed Complaint / Explanation <span className="text-rose-500">*</span>
              </Label>
              <textarea
                rows={4}
                value={complaintText}
                onChange={(e) => setComplaintText(e.target.value)}
                placeholder="Describe your issue clearly: e.g. dates you were marked absent, expected salary vs received amount, missing overtime hours, etc."
                className="w-full p-3 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-slate-800"
                required
              />
              {errors.complaint_text && (
                <p className="text-xs text-rose-500">{errors.complaint_text}</p>
              )}
            </div>

            {/* Attachment File */}
            <div className="space-y-1.5">
              <Label className="text-xs font-bold uppercase tracking-wider text-slate-700">
                Supporting Document / Screenshot (Optional)
              </Label>
              <div className="border border-dashed border-slate-300 rounded-xl p-4 text-center hover:border-blue-500 transition-colors bg-slate-50/50">
                <input
                  type="file"
                  id="complaint-attachment"
                  className="hidden"
                  accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                  onChange={(e) => {
                    if (e.target.files && e.target.files[0]) {
                      setAttachment(e.target.files[0]);
                    }
                  }}
                />
                <label
                  htmlFor="complaint-attachment"
                  className="cursor-pointer flex flex-col items-center justify-center space-y-1"
                >
                  <Paperclip className="w-5 h-5 text-slate-400" />
                  <span className="text-xs font-medium text-blue-600 hover:underline">
                    {attachment ? attachment.name : "Attach photo, screenshot, or document (max 10MB)"}
                  </span>
                  <span className="text-[11px] text-slate-400">PDF, JPG, PNG or DOCX</span>
                </label>
              </div>
            </div>

            {/* Submit Button */}
            <div className="pt-2">
              <Button
                type="submit"
                disabled={submitting}
                className="w-full h-12 bg-blue-600 hover:bg-blue-700 text-white font-bold text-sm rounded-xl shadow-md shadow-blue-500/20 transition-all flex items-center justify-center gap-2"
              >
                {submitting ? (
                  <>
                    <RefreshCw className="w-4 h-4 animate-spin" />
                    Submitting Complaint...
                  </>
                ) : (
                  <>
                    <Send className="w-4 h-4" />
                    Submit Complaint Ticket
                  </>
                )}
              </Button>
            </div>
          </form>
        </div>

        {/* Footer info */}
        <div className="text-center text-xs text-slate-400 space-y-1">
          <p>&copy; {new Date().getFullYear()} SBP Africa Human Resource Management System.</p>
          <p>For urgent workplace issues, please contact your designated HRBP or HR Officer.</p>
        </div>
      </div>
    </div>
  );
}
