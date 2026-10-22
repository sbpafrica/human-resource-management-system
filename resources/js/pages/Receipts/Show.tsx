import { Head, Link } from "@inertiajs/react";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import {
  ArrowLeft,
  Printer,
  Copy,
  CheckCircle,
  Building,
  Briefcase,
  CreditCard,
  User,
  FileText,
} from "lucide-react";
import AppLayout from "@/layouts/app-layout";
import { useState, useEffect } from "react";
import ErrorBoundary from "@/components/ErrorBoundary";

interface ReceiptItem {
  id: number;
  receipt_number: string;
  applicant_name: string;
  address: string;
  contact: string;
  position: string;
  client: string;
  descriptions: string;
  amount_paid: number | string;
  receipt_date: string;
  mode_of_payment: "Momo" | "Cash";
  receiving_officer: string;
  staffid: string;
  status: "Pending" | "Paid" | "Archived";
}

interface Props {
  receipt: ReceiptItem;
}

const breadcrumbs = [
  { title: "Dashboard", href: "/dashboard" },
  { title: "Receipts", href: "/receipts" },
  { title: "View Receipt", href: "#" },
];

export default function ReceiptShow({ receipt }: Props) {
  const [copied, setCopied] = useState(false);

  useEffect(() => {
    if (typeof window !== "undefined") {
      const urlParams = new URLSearchParams(window.location.search);
      if (urlParams.get("print") === "1" || urlParams.get("auto_print") === "1") {
        const timer = setTimeout(() => {
          window.print();
        }, 500);
        return () => clearTimeout(timer);
      }
    }
  }, []);

  const copyReceiptNumber = () => {
    navigator.clipboard.writeText(receipt.receipt_number).then(() => {
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    });
  };

  const handlePrint = () => {
    window.print();
  };

  // Determine badge styling based on status
  const statusLower = (receipt.status || "").toLowerCase();
  let statusBadgeClass = "bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-200";
  if (statusLower.includes("paid") || statusLower.includes("complete")) {
    statusBadgeClass = "bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 border-emerald-300";
  } else if (statusLower.includes("pending") || statusLower.includes("partial")) {
    statusBadgeClass = "bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300 border-amber-300";
  } else if (statusLower.includes("fail") || statusLower.includes("cancel") || statusLower.includes("archived")) {
    statusBadgeClass = "bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300 border-rose-300";
  }

  // Format descriptions string
  const formattedDescriptions = receipt.descriptions
    ? receipt.descriptions.replace(/,/g, ", ")
    : "N/A";

  return (
    <ErrorBoundary>
      <AppLayout breadcrumbs={breadcrumbs}>
        <Head title={`Receipt #${receipt.receipt_number} - SBP AFRICA LTD`} />

        <div className="flex flex-col gap-4 p-3 sm:p-6 max-w-4xl mx-auto w-full">
          {/* Back Navigation Bar (No Print) */}
          <div className="flex items-center justify-between no-print">
            <Link
              href={route("receipts.index")}
              className="inline-flex items-center gap-1.5 text-xs sm:text-sm text-muted-foreground hover:text-foreground transition-colors"
            >
              <ArrowLeft className="h-4 w-4" />
              <span>Back to Receipts</span>
            </Link>
            <div className="flex items-center gap-2">
              <Button
                variant="outline"
                size="sm"
                className="text-xs h-8"
                onClick={handlePrint}
              >
                <Printer className="mr-1.5 h-3.5 w-3.5" />
                Print Receipt
              </Button>
            </div>
          </div>

          {/* Copy Toast Alert */}
          {copied && (
            <div className="fixed bottom-4 left-1/2 -translate-x-1/2 z-50 bg-slate-900 text-white text-xs px-4 py-2 rounded-md shadow-lg flex items-center gap-2">
              <CheckCircle className="h-4 w-4 text-emerald-400" />
              <span>Receipt number copied to clipboard!</span>
            </div>
          )}

          {/* Official Receipt Card */}
          <Card className="border shadow-md overflow-hidden bg-card text-card-foreground">
            <CardContent className="p-4 sm:p-8 space-y-6">
              {/* Header Section */}
              <div className="border-b pb-4 flex flex-col sm:flex-row justify-between gap-4">
                {/* Issuer Info */}
                <div className="space-y-1">
                  <div className="text-xl sm:text-2xl font-extrabold tracking-tight text-sky-700 dark:text-sky-400">
                    SBP AFRICA LTD
                  </div>
                  <div className="text-xs text-muted-foreground leading-relaxed">
                    Kencity Avenue, North Legon, Ghana<br />
                    Email: enquiry@sbpafrica.group<br />
                    Phone: +233 302 546 664<br />
                    <span className="font-medium text-foreground">
                      Receipt Date: {receipt.receipt_date}
                    </span>
                  </div>
                </div>

                {/* Bill To Info */}
                <div className="text-left sm:text-right space-y-1">
                  <div className="text-[11px] font-bold tracking-wider uppercase text-muted-foreground">
                    BILL TO
                  </div>
                  <div className="font-semibold text-sm sm:text-base">
                    {receipt.applicant_name}
                  </div>
                  <div className="text-xs text-muted-foreground leading-relaxed">
                    {receipt.address}<br />
                    Contact: {receipt.contact}
                  </div>
                </div>
              </div>

              {/* Title & Status Row */}
              <div className="flex flex-wrap items-center justify-between gap-3 pt-2">
                <div>
                  <h1 className="text-xl sm:text-2xl font-extrabold uppercase tracking-wide">
                    Official Receipt
                  </h1>
                  <div className="flex items-center gap-1.5 text-xs text-muted-foreground mt-0.5">
                    <span>Receipt No:</span>
                    <strong className="font-mono font-semibold text-foreground">
                      {receipt.receipt_number}
                    </strong>
                    <button
                      onClick={copyReceiptNumber}
                      title="Copy receipt number"
                      className="p-1 hover:text-sky-600 transition-colors no-print"
                    >
                      <Copy className="h-3.5 w-3.5" />
                    </button>
                  </div>
                </div>

                <span
                  className={`inline-flex items-center px-3.5 py-1 rounded-full text-xs font-semibold border ${statusBadgeClass}`}
                >
                  {receipt.status}
                </span>
              </div>

              {/* Amount Highlight Box */}
              <div className="bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-900 rounded-xl p-4 sm:p-5 flex items-center justify-between">
                <span className="text-xs font-bold uppercase tracking-wider text-emerald-800 dark:text-emerald-300">
                  Amount Paid
                </span>
                <span className="text-xl sm:text-3xl font-extrabold text-emerald-700 dark:text-emerald-400">
                  GHS {Number(receipt.amount_paid).toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                </span>
              </div>

              {/* Details Table */}
              <div className="border rounded-lg overflow-hidden divide-y text-xs sm:text-sm">
                <div className="flex flex-col sm:flex-row p-3 bg-muted/40 sm:items-center">
                  <div className="w-full sm:w-1/3 font-semibold text-muted-foreground flex items-center gap-2">
                    <FileText className="h-4 w-4 text-muted-foreground" />
                    <span>Description</span>
                  </div>
                  <div className="w-full sm:w-2/3 font-medium mt-1 sm:mt-0">
                    Cost of new candidate onboarding ({formattedDescriptions})
                  </div>
                </div>

                <div className="flex flex-col sm:flex-row p-3 sm:items-center">
                  <div className="w-full sm:w-1/3 font-semibold text-muted-foreground flex items-center gap-2">
                    <Briefcase className="h-4 w-4 text-muted-foreground" />
                    <span>Position Considered for</span>
                  </div>
                  <div className="w-full sm:w-2/3 font-medium mt-1 sm:mt-0">
                    {receipt.position}
                  </div>
                </div>

                <div className="flex flex-col sm:flex-row p-3 bg-muted/40 sm:items-center">
                  <div className="w-full sm:w-1/3 font-semibold text-muted-foreground flex items-center gap-2">
                    <Building className="h-4 w-4 text-muted-foreground" />
                    <span>Client Company</span>
                  </div>
                  <div className="w-full sm:w-2/3 font-medium mt-1 sm:mt-0">
                    {receipt.client}
                  </div>
                </div>

                <div className="flex flex-col sm:flex-row p-3 sm:items-center">
                  <div className="w-full sm:w-1/3 font-semibold text-muted-foreground flex items-center gap-2">
                    <CreditCard className="h-4 w-4 text-muted-foreground" />
                    <span>Mode of Payment</span>
                  </div>
                  <div className="w-full sm:w-2/3 font-medium mt-1 sm:mt-0">
                    {receipt.mode_of_payment}
                  </div>
                </div>

                <div className="flex flex-col sm:flex-row p-3 bg-muted/40 sm:items-center">
                  <div className="w-full sm:w-1/3 font-semibold text-muted-foreground flex items-center gap-2">
                    <User className="h-4 w-4 text-muted-foreground" />
                    <span>Receiving Officer</span>
                  </div>
                  <div className="w-full sm:w-2/3 font-medium mt-1 sm:mt-0">
                    {receipt.receiving_officer}
                  </div>
                </div>
              </div>

              {/* Footer Notice */}
              <div className="text-center text-[11px] text-muted-foreground pt-4 border-t">
                This is an official receipt issued by SBP AFRICA LTD. For inquiries, contact info@sbpafrica.group.
              </div>

              {/* Action Buttons (No Print) */}
              <div className="flex flex-wrap items-center justify-end gap-2 pt-2 no-print">
                <Button
                  variant="outline"
                  size="sm"
                  className="text-xs h-9"
                  onClick={handlePrint}
                >
                  <Printer className="mr-1.5 h-4 w-4" />
                  Print Receipt
                </Button>
                <Button
                  asChild
                  variant="default"
                  size="sm"
                  className="text-xs h-9 bg-sky-600 hover:bg-sky-700 text-white"
                >
                  <Link href={route("receipts.index")}>
                    <ArrowLeft className="mr-1.5 h-4 w-4" />
                    Back to Receipts
                  </Link>
                </Button>
              </div>
            </CardContent>
          </Card>
        </div>
      </AppLayout>
    </ErrorBoundary>
  );
}
