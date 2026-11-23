import { Head, Link } from "@inertiajs/react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { CheckCircle, XCircle, Download, FileText, CreditCard, User } from "lucide-react";
import { Progress } from "@/components/ui/progress";
import AppLayout from "@/layouts/app-layout";
import { useEffect, useState } from "react";

interface Employee {
  staffid: string;
  employeename: string;
  company: string | null;
  shop: string | null;
  position: string | null;
  qualification: string | null;
  startdate: string | null;
  dateofbirth: string | null;
  ghcard: string | null;
  phone1: string | null;
  phone2: string | null;
  gender: string | null;
  nationality: string | null;
  email: string | null;
  nextofkin: string | null;
  relation: string | null;
  contact: string | null;
  guarantor: string | null;
  contactgua: string | null;
  profilepic: string | null;
  ghanafront: string | null;
  ghanaback: string | null;
  gpsaddress: string | null;
  signature?: string | null;
  signature_date?: string | null;
  profilepic_url?: string | null;
  signature_url?: string | null;
  ghanafront_url?: string | null;
  ghanaback_url?: string | null;
}

interface BankDetail {
  employid: string;
  bank: string | null;
  account: string | null;
  branch: string | null;
}

interface Petra {
  staffid: string;
  lastname: string | null;
  firstname: string | null;
  othername: string | null;
  petraId: string | null;
}

interface Ssnit {
  staffid: string;
  lastname: string | null;
  firstname: string | null;
  othername: string | null;
  ssnit1a: string | null;
}

interface Props {
  employee: Employee;
  bankDetail: BankDetail | null;
  petra: Petra | null;
  ssnit: Ssnit | null;
  flash?: {
    success?: string;
    error?: string;
  };
}

const breadcrumbs = [
  { title: "Employee Portal", href: "/employeeportal" },
  { title: "Employee Profile", href: "#" },
];

export default function Profile({ employee, bankDetail, petra, ssnit, flash }: Props) {
  const [showToast, setShowToast] = useState(false);
  const [toastMessage, setToastMessage] = useState("");
  const [toastType, setToastType] = useState<"success" | "error">("success");
  const [completionPercentage, setCompletionPercentage] = useState(0);
  const [profileImgError, setProfileImgError] = useState(false);

  // Calculate profile completion percentage
  useEffect(() => {
    const fields = [
      employee.staffid,
      employee.employeename,
      employee.company,
      employee.shop,
      employee.position,
      employee.qualification,
      employee.startdate,
      employee.dateofbirth,
      employee.ghcard,
      employee.phone1,
      employee.phone2,
      employee.gender,
      employee.nationality,
      employee.email,
      employee.nextofkin,
      employee.relation,
      employee.contact,
      employee.guarantor,
      employee.contactgua,
      employee.profilepic,
      employee.ghanafront,
      employee.ghanaback,
      employee.signature,
      employee.gpsaddress,
    ];
    const filledFields = fields.filter((field) => field !== null && field !== "" && field !== "0").length;
    const totalFields = fields.length;
    const percentage = Math.round((filledFields / totalFields) * 100);
    setCompletionPercentage(percentage);
  }, [employee]);

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
    if (showToast) {
      const timeout = toastType === "error" ? 60000 : 3000;
      const timer = setTimeout(() => setShowToast(false), timeout);
      return () => clearTimeout(timer);
    }
  }, [showToast, toastType]);

  // Format names for PETRA and SSNIT
  const formatName = (firstname: string | null, lastname: string | null, othername: string | null) => {
    const parts = [firstname, lastname, othername].filter((part) => part !== null && part !== "");
    return parts.length > 0 ? parts.join(" ") : "N/A";
  };

  // Resolved URLs
  const profileUrl = !profileImgError && (employee.profilepic_url || (employee.profilepic && employee.profilepic !== "0" ? `/employeeportal/${employee.staffid}/image/profilepic` : null));
  const signatureUrl = employee.signature_url || (employee.signature && employee.signature !== "0" ? `/employeeportal/${employee.staffid}/image/signature` : null);
  const ghanaFrontUrl = employee.ghanafront_url || (employee.ghanafront && employee.ghanafront !== "0" ? `/employeeportal/${employee.staffid}/image/ghanafront` : null);
  const ghanaBackUrl = employee.ghanaback_url || (employee.ghanaback && employee.ghanaback !== "0" ? `/employeeportal/${employee.staffid}/image/ghanaback` : null);

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title={`Employee Profile - ${employee.employeename}`} />
      <div className="flex h-full flex-1 flex-col gap-4 p-4 md:p-6">
        {showToast && (
          <div
            className={`fixed top-4 right-4 z-50 flex items-center gap-2 rounded-lg p-4 shadow-lg w-full sm:w-auto max-w-sm ${
              toastType === "success" ? "bg-green-500 text-white" : "bg-red-500 text-white"
            } animate-in fade-in slide-in-from-top-5`}
          >
            {toastType === "success" ? <CheckCircle className="h-5 w-5" /> : <XCircle className="h-5 w-5" />}
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

        <div className="max-w-5xl mx-auto w-full">
          {/* Header Section */}
          <Card className="mb-6 border-none shadow-md bg-gradient-to-r from-blue-600 to-indigo-600 text-white">
            <CardContent className="pt-6">
              <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div className="flex items-center gap-4">
                  {/* Circular Avatar */}
                  <div className="w-20 h-20 bg-blue-500 rounded-full flex items-center justify-center shrink-0 ring-4 ring-white/30 shadow-md overflow-hidden">
                    {profileUrl ? (
                      <img
                        src={profileUrl}
                        alt={employee.employeename}
                        onError={() => setProfileImgError(true)}
                        className="w-full h-full object-cover rounded-full"
                      />
                    ) : (
                      <span className="text-white text-2xl font-bold">
                        {employee.employeename.trim().charAt(0).toUpperCase()}
                      </span>
                    )}
                  </div>
                  <div>
                    <h2 className="text-xl font-bold tracking-tight">{employee.employeename}</h2>
                    <p className="text-sm opacity-90">{employee.company ? `${employee.company} Staff` : "Staff"} &bull; ID: {employee.staffid}</p>
                  </div>
                </div>
                <div className="flex items-center gap-2 bg-white/10 px-3 py-1.5 rounded-lg backdrop-blur-sm">
                  <span className="text-xs">Profile Completion:</span>
                  <Progress value={completionPercentage} className="w-24 [data-progress]:bg-green-400 bg-white/20" />
                  <span className="text-xs font-semibold">{completionPercentage}%</span>
                </div>
              </div>
            </CardContent>
          </Card>

          {/* Information Sections */}
          <div className="grid md:grid-cols-2 gap-4">
            {/* Personal Information */}
            <Card className="border-l-4 border-blue-500 shadow-sm hover:shadow-md transition-shadow bg-blue-50/50">
              <CardHeader className="pb-2">
                <CardTitle className="text-base text-blue-700 flex items-center gap-2">
                  <User className="h-4 w-4" /> Personal Information
                </CardTitle>
              </CardHeader>
              <CardContent className="text-sm space-y-2 pt-1">
                <div className="flex justify-between border-b pb-1 text-gray-700">
                  <span className="font-medium text-gray-500">Employee ID:</span>
                  <span>{employee.staffid}</span>
                </div>
                <div className="flex justify-between border-b pb-1 text-gray-700">
                  <span className="font-medium text-gray-500">Date of Birth:</span>
                  <span>{employee.dateofbirth || "N/A"}</span>
                </div>
                <div className="flex justify-between border-b pb-1 text-gray-700">
                  <span className="font-medium text-gray-500">Gender:</span>
                  <span>{employee.gender || "N/A"}</span>
                </div>
                <div className="flex justify-between border-b pb-1 text-gray-700">
                  <span className="font-medium text-gray-500">Nationality:</span>
                  <span>{employee.nationality || "N/A"}</span>
                </div>
                <div className="flex justify-between border-b pb-1 text-gray-700">
                  <span className="font-medium text-gray-500">Ghana Card:</span>
                  <span className="font-mono text-xs">{employee.ghcard || "N/A"}</span>
                </div>
                <div className="flex justify-between text-gray-700">
                  <span className="font-medium text-gray-500">Qualification:</span>
                  <span>{employee.qualification || "N/A"}</span>
                </div>
              </CardContent>
            </Card>

            {/* Employment Details */}
            <Card className="border-l-4 border-green-500 shadow-sm hover:shadow-md transition-shadow bg-green-50/50">
              <CardHeader className="pb-2">
                <CardTitle className="text-base text-green-700 flex items-center gap-2">
                  <FileText className="h-4 w-4" /> Job &amp; Contact Information
                </CardTitle>
              </CardHeader>
              <CardContent className="text-sm space-y-2 pt-1">
                <div className="flex justify-between border-b pb-1 text-gray-700">
                  <span className="font-medium text-gray-500">Company:</span>
                  <span>{employee.company || "N/A"}</span>
                </div>
                <div className="flex justify-between border-b pb-1 text-gray-700">
                  <span className="font-medium text-gray-500">Shop / Dept:</span>
                  <span>{employee.shop || "N/A"}</span>
                </div>
                <div className="flex justify-between border-b pb-1 text-gray-700">
                  <span className="font-medium text-gray-500">Position:</span>
                  <span>{employee.position || "N/A"}</span>
                </div>
                <div className="flex justify-between border-b pb-1 text-gray-700">
                  <span className="font-medium text-gray-500">Start Date:</span>
                  <span>{employee.startdate || "N/A"}</span>
                </div>
                <div className="flex justify-between border-b pb-1 text-gray-700">
                  <span className="font-medium text-gray-500">Email:</span>
                  <span>{employee.email || "N/A"}</span>
                </div>
                <div className="flex justify-between text-gray-700">
                  <span className="font-medium text-gray-500">Phone:</span>
                  <span>{employee.phone1 || employee.phone2 || "N/A"}</span>
                </div>
              </CardContent>
            </Card>

            {/* Bank Details */}
            <Card className="border-l-4 border-teal-500 shadow-sm hover:shadow-md transition-shadow bg-teal-50/50">
              <CardHeader className="pb-2">
                <CardTitle className="text-base text-teal-700 flex items-center gap-2">
                  <CreditCard className="h-4 w-4" /> Bank Information
                </CardTitle>
              </CardHeader>
              <CardContent className="text-sm space-y-2 pt-1">
                {bankDetail ? (
                  <>
                    <div className="flex justify-between border-b pb-1 text-gray-700">
                      <span className="font-medium text-gray-500">Bank Name:</span>
                      <span>{bankDetail.bank || "N/A"}</span>
                    </div>
                    <div className="flex justify-between border-b pb-1 text-gray-700">
                      <span className="font-medium text-gray-500">Account Number:</span>
                      <span className="font-mono">{bankDetail.account || "N/A"}</span>
                    </div>
                    <div className="flex justify-between text-gray-700">
                      <span className="font-medium text-gray-500">Bank Branch:</span>
                      <span>{bankDetail.branch || "N/A"}</span>
                    </div>
                  </>
                ) : (
                  <p className="text-muted-foreground italic text-xs py-2">No bank details available.</p>
                )}
              </CardContent>
            </Card>

            {/* SSNIT Details */}
            <Card className="border-l-4 border-cyan-500 shadow-sm hover:shadow-md transition-shadow bg-cyan-50/50">
              <CardHeader className="pb-2">
                <CardTitle className="text-base text-cyan-700">SSNIT Information</CardTitle>
              </CardHeader>
              <CardContent className="text-sm space-y-2 pt-1">
                <div className="flex justify-between border-b pb-1 text-gray-700">
                  <span className="font-medium text-gray-500">SSNIT Number:</span>
                  <span className="font-mono">{ssnit?.ssnit1a || "N/A"}</span>
                </div>
                <div className="flex justify-between border-b pb-1 text-gray-700">
                  <span className="font-medium text-gray-500">SSNIT Name:</span>
                  <span>{ssnit ? formatName(ssnit.firstname, ssnit.lastname, ssnit.othername) : "N/A"}</span>
                </div>
                <div className="flex justify-between text-gray-700">
                  <span className="font-medium text-gray-500">SSNIT Status:</span>
                  <span>{ssnit?.ssnit1a ? "Registered" : "Pending"}</span>
                </div>
              </CardContent>
            </Card>

            {/* PETRA Details */}
            <Card className="border-l-4 border-pink-500 shadow-sm hover:shadow-md transition-shadow bg-pink-50/50">
              <CardHeader className="pb-2">
                <CardTitle className="text-base text-pink-700">PETRA Information</CardTitle>
              </CardHeader>
              <CardContent className="text-sm space-y-2 pt-1">
                <div className="flex justify-between border-b pb-1 text-gray-700">
                  <span className="font-medium text-gray-500">PETRA ID:</span>
                  <span className="font-mono">{petra?.petraId || "N/A"}</span>
                </div>
                <div className="flex justify-between text-gray-700">
                  <span className="font-medium text-gray-500">PETRA Name:</span>
                  <span>{petra ? formatName(petra.firstname, petra.lastname, petra.othername) : "N/A"}</span>
                </div>
              </CardContent>
            </Card>

            {/* Next of Kin & Guarantor */}
            <Card className="border-l-4 border-orange-500 shadow-sm hover:shadow-md transition-shadow bg-orange-50/50">
              <CardHeader className="pb-2">
                <CardTitle className="text-base text-orange-700">Next of Kin &amp; Guarantor</CardTitle>
              </CardHeader>
              <CardContent className="text-sm space-y-2 pt-1">
                <div className="flex justify-between border-b pb-1 text-gray-700">
                  <span className="font-medium text-gray-500">Next of Kin:</span>
                  <span>{employee.nextofkin || "N/A"}</span>
                </div>
                <div className="flex justify-between border-b pb-1 text-gray-700">
                  <span className="font-medium text-gray-500">Relation:</span>
                  <span>{employee.relation || "N/A"}</span>
                </div>
                <div className="flex justify-between border-b pb-1 text-gray-700">
                  <span className="font-medium text-gray-500">Contact:</span>
                  <span>{employee.contact || "N/A"}</span>
                </div>
                <div className="flex justify-between border-b pb-1 text-gray-700">
                  <span className="font-medium text-gray-500">Guarantor:</span>
                  <span>{employee.guarantor || "N/A"}</span>
                </div>
                <div className="flex justify-between text-gray-700">
                  <span className="font-medium text-gray-500">Guarantor Contact:</span>
                  <span>{employee.contactgua || "N/A"}</span>
                </div>
              </CardContent>
            </Card>

            {/* Signature & Ghana Card Card (Spans full width) */}
            <Card className="col-span-full border-l-4 border-purple-600 shadow-sm hover:shadow-md transition-shadow bg-white mt-1">
              <CardHeader className="flex flex-row items-center gap-2 pb-2">
                <div className="h-8 w-8 rounded-full bg-purple-100 text-purple-700 flex items-center justify-center font-bold">
                  ✍
                </div>
                <CardTitle className="text-base font-semibold text-gray-800">Signature &amp; Ghana Card</CardTitle>
              </CardHeader>
              <CardContent className="pt-2">
                <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                  
                  {/* Signature Column */}
                  <div className="flex flex-col items-center justify-between p-4 bg-gray-50 border rounded-lg text-center min-h-[220px]">
                    <div className="w-full">
                      <p className="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Digital Signature</p>
                      <div className="h-32 flex items-center justify-center bg-white border border-dashed rounded p-2 overflow-hidden shadow-inner">
                        {signatureUrl ? (
                          <img
                            src={signatureUrl}
                            alt="Signature"
                            className="max-h-28 max-w-full object-contain"
                          />
                        ) : (
                          <p className="text-xs text-muted-foreground italic">No signature available</p>
                        )}
                      </div>
                    </div>
                    <div className="mt-3 w-full">
                      <p className="text-xs text-gray-600 font-medium">
                        {employee.signature_date ? `Signed: ${employee.signature_date}` : (signatureUrl ? "Signed (Verified)" : "Unsigned")}
                      </p>
                      {signatureUrl && (
                        <a
                          href={`/employeeportal/${employee.staffid}/download/signature`}
                          className="inline-flex items-center gap-1 text-xs text-purple-600 hover:text-purple-800 font-medium mt-1 transition-colors"
                        >
                          <Download className="h-3 w-3" /> Download Signature
                        </a>
                      )}
                    </div>
                  </div>

                  {/* Ghana Card Front Column */}
                  <div className="flex flex-col items-center justify-between p-4 bg-gray-50 border rounded-lg text-center min-h-[220px]">
                    <div className="w-full">
                      <p className="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Ghana Card Front</p>
                      <div className="h-32 flex items-center justify-center bg-white border border-dashed rounded p-2 overflow-hidden shadow-inner">
                        {ghanaFrontUrl ? (
                          <img
                            src={ghanaFrontUrl}
                            alt="Ghana Card Front"
                            className="max-h-28 max-w-full object-contain rounded"
                          />
                        ) : (
                          <p className="text-xs text-muted-foreground italic">No card front uploaded</p>
                        )}
                      </div>
                    </div>
                    <div className="mt-3 w-full">
                      <p className="text-xs text-gray-600 font-medium mb-1.5">Ghana Card Front</p>
                      {ghanaFrontUrl ? (
                        <a
                          href={`/employeeportal/${employee.staffid}/download/ghanafront`}
                          className="inline-flex items-center gap-1.5 px-3 py-1 bg-purple-50 text-purple-700 hover:bg-purple-100 border border-purple-200 rounded text-xs font-medium transition-colors"
                        >
                          <Download className="h-3.5 w-3.5" /> Download Front
                        </a>
                      ) : (
                        <span className="text-xs text-muted-foreground">Not Available</span>
                      )}
                    </div>
                  </div>

                  {/* Ghana Card Back Column */}
                  <div className="flex flex-col items-center justify-between p-4 bg-gray-50 border rounded-lg text-center min-h-[220px]">
                    <div className="w-full">
                      <p className="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Ghana Card Back</p>
                      <div className="h-32 flex items-center justify-center bg-white border border-dashed rounded p-2 overflow-hidden shadow-inner">
                        {ghanaBackUrl ? (
                          <img
                            src={ghanaBackUrl}
                            alt="Ghana Card Back"
                            className="max-h-28 max-w-full object-contain rounded"
                          />
                        ) : (
                          <p className="text-xs text-muted-foreground italic">No card back uploaded</p>
                        )}
                      </div>
                    </div>
                    <div className="mt-3 w-full">
                      <p className="text-xs text-gray-600 font-medium mb-1.5">Ghana Card Back</p>
                      {ghanaBackUrl ? (
                        <a
                          href={`/employeeportal/${employee.staffid}/download/ghanaback`}
                          className="inline-flex items-center gap-1.5 px-3 py-1 bg-purple-50 text-purple-700 hover:bg-purple-100 border border-purple-200 rounded text-xs font-medium transition-colors"
                        >
                          <Download className="h-3.5 w-3.5" /> Download Back
                        </a>
                      ) : (
                        <span className="text-xs text-muted-foreground">Not Available</span>
                      )}
                    </div>
                  </div>

                </div>
              </CardContent>
            </Card>
          </div>

          {/* Action Buttons */}
          <div className="mt-6 flex justify-end gap-4 pb-8">
            <Button asChild variant="outline" className="border-gray-300">
              <Link href={route("employeeportal.index")}>Back to Employees</Link>
            </Button>
            <Button asChild className="bg-blue-600 hover:bg-blue-700 text-white shadow-sm">
              <a href={route("employeeportal.onboarding-form", employee.staffid)} target="_blank" rel="noopener noreferrer">
                <Download className="mr-2 h-4 w-4" /> Download onBoarding Form
              </a>
            </Button>
          </div>
        </div>
      </div>
    </AppLayout>
  );
}
