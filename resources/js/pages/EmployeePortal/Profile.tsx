import { Head, Link } from "@inertiajs/react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { CheckCircle, XCircle, Download } from "lucide-react";
import { Progress } from "@/components/ui/progress";
import AppLayout from "@/layouts/app-layout";
import { useEffect, useState } from "react";
import jsPDF from "jspdf";

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

  // Calculate profile completion percentage (for UI only)
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
      employee.gpsaddress,
    ];
    const filledFields = fields.filter((field) => field !== null && field !== "").length;
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

  // Download PDF function
  const downloadPDF = async () => {
    const doc = new jsPDF();
    const pageWidth = doc.internal.pageSize.getWidth();
    const logoUrl = "/images/sbpafricalogo.png"; // Local logo path

    // Add logo
    try {
      const img = new Image();
      img.src = logoUrl;
      await new Promise((resolve) => {
        img.onload = () => resolve(null);
        img.onerror = () => {
          console.warn("Failed to load logo, proceeding without it.");
          resolve(null);
        };
      });
      doc.addImage(img, "PNG", (pageWidth - 60) / 2, 10, 60, 20); // Centered logo
    } catch (error) {
      console.warn("Error loading logo:", error);
    }

    // Add title
    doc.setFontSize(12);
    doc.setFont("helvetica", "bold");
    doc.text("Employee Profile", pageWidth / 2, 35, { align: "center" });

    // Add employee details in two-column layout
    let y = 40;
    const col1X = 20; // Left column
    const col2X = pageWidth / 2; // Right column
    const colWidth = (pageWidth - 40) / 2 - 5; // Width per column, with gap
    const addSection = (title: string, items: Array<{ label: string; value: string }>) => {
      doc.setFontSize(10);
      doc.setFont("helvetica", "bold");
      doc.text(title, 20, y);
      y += 4;
      doc.setLineWidth(0.5);
      doc.setDrawColor("#000000"); // Black underline
      doc.line(20, y, pageWidth - 20, y); // Underline
      y += 6;

      items.forEach((item, index) => {
        const x = index % 2 === 0 ? col1X : col2X; // Alternate columns
        const splitLabel = doc.splitTextToSize(`${item.label}:`, colWidth);
        const splitValue = doc.splitTextToSize(item.value, colWidth - doc.getTextWidth(`${item.label}: `));
        const maxLines = Math.max(splitLabel.length, splitValue.length);
        const boxHeight = maxLines * 4 + 4; // Reduced height for tighter spacing

        // Draw light grey rounded border
        doc.setDrawColor("#E5E7EB"); // Tailwind gray-200
        doc.setLineWidth(0.3);
        doc.roundedRect(x, y - 2, colWidth, boxHeight, 2, 2, "S"); // rx, ry for rounded corners

        // Add label (bold)
        doc.setFontSize(9);
        doc.setFont("helvetica", "bold");
        doc.text(splitLabel, x + 2, y + 2);

        // Add value (normal) with space
        doc.setFont("helvetica", "normal");
        doc.text(splitValue, x + 2 + doc.getTextWidth(`${item.label}: `), y + 2);

        if (index % 2 === 1 || index === items.length - 1) {
          y += boxHeight + 2; // Reduced spacing after row
        }
      });
      y += 2; // Reduced spacing between sections
    };

    addSection("Personal Information", [
      { label: "Name", value: employee.employeename },
      { label: "Staff ID", value: employee.staffid },
      { label: "Date of Birth", value: employee.dateofbirth || "N/A" },
      { label: "Gender", value: employee.gender || "N/A" },
      { label: "Nationality", value: employee.nationality || "N/A" },
      { label: "Ghana Card", value: employee.ghcard || "N/A" },
    ]);

    addSection("Employment Details", [
      { label: "Company", value: employee.company || "N/A" },
      { label: "Shop", value: employee.shop || "N/A" },
      { label: "Position", value: employee.position || "N/A" },
      { label: "Qualification", value: employee.qualification || "N/A" },
      { label: "Start Date", value: employee.startdate || "N/A" },
    ]);

    addSection("Contact Information", [
      { label: "Email", value: employee.email || "N/A" },
      { label: "Phone 1", value: employee.phone1 || "N/A" },
      { label: "Phone 2", value: employee.phone2 || "N/A" },
      { label: "GPS Address", value: employee.gpsaddress || "N/A" },
    ]);

    addSection("Next of Kin & Guarantor", [
      { label: "Next of Kin", value: employee.nextofkin || "N/A" },
      { label: "Relation", value: employee.relation || "N/A" },
      { label: "Contact", value: employee.contact || "N/A" },
      { label: "Guarantor", value: employee.guarantor || "N/A" },
      { label: "Guarantor Contact", value: employee.contactgua || "N/A" },
    ]);

    addSection("Bank Details", [
      { label: "Bank Name", value: bankDetail ? bankDetail.bank || "N/A" : "N/A" },
      { label: "Account Number", value: bankDetail ? bankDetail.account || "N/A" : "N/A" },
      { label: "Bank Branch", value: bankDetail ? bankDetail.branch || "N/A" : "N/A" },
    ]);

    addSection("PETRA Details", [
      { label: "Name", value: petra ? formatName(petra.firstname, petra.lastname, petra.othername) : "N/A" },
      { label: "PETRA ID", value: petra ? petra.petraId || "N/A" : "N/A" },
    ]);

    addSection("SSNIT Details", [
      { label: "Name", value: ssnit ? formatName(ssnit.firstname, ssnit.lastname, ssnit.othername) : "N/A" },
      { label: "SSNIT Number", value: ssnit ? ssnit.ssnit1a || "N/A" : "N/A" },
    ]);

    // Save the PDF
    doc.save(`Employee_Profile_${employee.staffid}.pdf`);
  };

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

        <div className="max-w-5xl mx-auto">
          {/* Header Section */}
          <Card className="mb-6 border-none shadow-md bg-gradient-to-r from-blue-500 to-indigo-500 text-white">
            <CardContent className="pt-6">
              <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div className="flex items-center gap-4">
                  <div className="w-20 h-20 bg-blue-500 rounded-full flex items-center justify-center shrink-0 ring-2 ring-blue-300">
                    {employee.profilepic ? (
                      <img
                        src={employee.profilepic}
                        alt="Profile"
                        className="w-full h-full object-cover rounded-full"
                      />
                    ) : (
                      <span className="text-white text-2xl font-bold">
                        {employee.employeename.charAt(0).toUpperCase()}
                      </span>
                    )}
                  </div>
                  <div>
                    <h2 className="text-xl font-bold">{employee.employeename}</h2>
                    <p className="text-sm opacity-80">{employee.staffid}</p>
                  </div>
                </div>
                <div className="flex items-center gap-2">
                  <span className="text-xs">Profile Completion:</span>
                  <Progress value={completionPercentage} className="w-24 [data-progress]:bg-blue-300" />
                  <span className="text-xs font-medium">{completionPercentage}%</span>
                </div>
              </div>
            </CardContent>
          </Card>

          {/* Information Sections */}
          <div className="grid md:grid-cols-2 gap-4">
            <Card className="border-l-4 border-blue-500 shadow-sm hover:shadow-md transition-shadow bg-blue-50">
              <CardHeader>
                <CardTitle className="text-base text-blue-600">Personal Information</CardTitle>
              </CardHeader>
              <CardContent className="text-sm space-y-1.5">
                <p><span className="font-medium">Date of Birth:</span> {employee.dateofbirth || "N/A"}</p>
                <p><span className="font-medium">Gender:</span> {employee.gender || "N/A"}</p>
                <p><span className="font-medium">Nationality:</span> {employee.nationality || "N/A"}</p>
                <p><span className="font-medium">Ghana Card:</span> {employee.ghcard || "N/A"}</p>
              </CardContent>
            </Card>

            <Card className="border-l-4 border-green-500 shadow-sm hover:shadow-md transition-shadow">
              <CardHeader>
                <CardTitle className="text-base text-green-600">Employment Details</CardTitle>
              </CardHeader>
              <CardContent className="text-sm space-y-1.5">
                <p><span className="font-medium">Company:</span> {employee.company || "N/A"}</p>
                <p><span className="font-medium">Shop:</span> {employee.shop || "N/A"}</p>
                <p><span className="font-medium">Position:</span> {employee.position || "N/A"}</p>
                <p><span className="font-medium">Qualification:</span> {employee.qualification || "N/A"}</p>
                <p><span className="font-medium">Start Date:</span> {employee.startdate || "N/A"}</p>
              </CardContent>
            </Card>

            <Card className="border-l-4 border-purple-500 shadow-sm hover:shadow-md transition-shadow bg-purple-50">
              <CardHeader>
                <CardTitle className="text-base text-purple-600">Contact Information</CardTitle>
              </CardHeader>
              <CardContent className="text-sm space-y-1.5">
                <p><span className="font-medium">Email:</span> {employee.email || "N/A"}</p>
                <p><span className="font-medium">Phone 1:</span> {employee.phone1 || "N/A"}</p>
                <p><span className="font-medium">Phone 2:</span> {employee.phone2 || "N/A"}</p>
                <p><span className="font-medium">GPS Address:</span> {employee.gpsaddress || "N/A"}</p>
              </CardContent>
            </Card>

            <Card className="border-l-4 border-orange-500 shadow-sm hover:shadow-md transition-shadow">
              <CardHeader>
                <CardTitle className="text-base text-orange-600">Next of Kin & Guarantor</CardTitle>
              </CardHeader>
              <CardContent className="text-sm space-y-1.5">
                <p><span className="font-medium">Next of Kin:</span> {employee.nextofkin || "N/A"}</p>
                <p><span className="font-medium">Relation:</span> {employee.relation || "N/A"}</p>
                <p><span className="font-medium">Contact:</span> {employee.contact || "N/A"}</p>
                <p><span className="font-medium">Guarantor:</span> {employee.guarantor || "N/A"}</p>
                <p><span className="font-medium">Guarantor Contact:</span> {employee.contactgua || "N/A"}</p>
              </CardContent>
            </Card>

            <Card className="border-l-4 border-teal-500 shadow-sm hover:shadow-md transition-shadow bg-teal-50">
              <CardHeader>
                <CardTitle className="text-base text-teal-600">Bank Details</CardTitle>
              </CardHeader>
              <CardContent className="text-sm space-y-1.5">
                {bankDetail ? (
                  <>
                    <p><span className="font-medium">Bank Name:</span> {bankDetail.bank || "N/A"}</p>
                    <p><span className="font-medium">Account Number:</span> {bankDetail.account || "N/A"}</p>
                    <p><span className="font-medium">Bank Branch:</span> {bankDetail.branch || "N/A"}</p>
                  </>
                ) : (
                  <p className="text-muted-foreground">No bank details available.</p>
                )}
              </CardContent>
            </Card>

            <Card className="border-l-4 border-pink-500 shadow-sm hover:shadow-md transition-shadow">
              <CardHeader>
                <CardTitle className="text-base text-pink-600">PETRA Details</CardTitle>
              </CardHeader>
              <CardContent className="text-sm space-y-1.5">
                {petra ? (
                  <>
                    <p>
                      <span className="font-medium">Name:</span>{" "}
                      {formatName(petra.firstname, petra.lastname, petra.othername)}
                    </p>
                    <p><span className="font-medium">PETRA ID:</span> {petra.petraId || "N/A"}</p>
                  </>
                ) : (
                  <p className="text-muted-foreground">No PETRA details available.</p>
                )}
              </CardContent>
            </Card>

            <Card className="border-l-4 border-cyan-500 shadow-sm hover:shadow-md transition-shadow bg-cyan-50">
              <CardHeader>
                <CardTitle className="text-base text-cyan-600">SSNIT Details</CardTitle>
              </CardHeader>
              <CardContent className="text-sm space-y-1.5">
                {ssnit ? (
                  <>
                    <p>
                      <span className="font-medium">Name:</span>{" "}
                      {formatName(ssnit.firstname, ssnit.lastname, ssnit.othername)}
                    </p>
                    <p><span className="font-medium">SSNIT Number:</span> {ssnit.ssnit1a || "N/A"}</p>
                  </>
                ) : (
                  <p className="text-muted-foreground">No SSNIT details available.</p>
                )}
              </CardContent>
            </Card>

            {(employee.ghanafront || employee.ghanaback) && (
              <Card className="border-l-4 border-indigo-500 shadow-sm hover:shadow-md transition-shadow">
                <CardHeader>
                  <CardTitle className="text-base text-indigo-600">Identification</CardTitle>
                </CardHeader>
                <CardContent>
                  <div className="grid sm:grid-cols-2 gap-4">
                    {employee.ghanafront && (
                      <div>
                        <p className="font-medium text-sm">Ghana Card (Front):</p>
                        <img
                          src={employee.ghanafront}
                          alt="Ghana Card Front"
                          className="mt-2 max-w-xs rounded shadow"
                        />
                      </div>
                    )}
                    {employee.ghanaback && (
                      <div>
                        <p className="font-medium text-sm">Ghana Card (Back):</p>
                        <img
                          src={employee.ghanaback}
                          alt="Ghana Card Back"
                          className="mt-2 max-w-xs rounded shadow"
                        />
                      </div>
                    )}
                  </div>
                </CardContent>
              </Card>
            )}
          </div>

          <div className="mt-6 flex justify-end gap-4">
            <Button asChild className="bg-blue-600 hover:bg-blue-700">
              <Link href={route("employeeportal.index")}>Back to Employees</Link>
            </Button>
            <Button onClick={downloadPDF} className="bg-green-600 hover:bg-green-700">
              <Download className="mr-2 h-4 w-4" /> Download PDF
            </Button>
          </div>
        </div>
      </div>
    </AppLayout>
  );
}
