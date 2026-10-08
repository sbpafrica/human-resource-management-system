import { Head } from "@inertiajs/react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Check, X } from "lucide-react";
import { useForm } from "@inertiajs/react";
import { useEffect, useState } from "react";
import { debounce } from "lodash";
import ErrorBoundary from "@/components/ErrorBoundary";

interface LeaveApplication {
  id: number;
  ida: string;
  name: string | null;
  type: string | null;
  date_start: string | null;
  date_end: string | null;
  reason: string | null;
  status: number;
  managername: string | null;
  phone: string | null;
}

interface Props {
  leaveApplication: LeaveApplication | null;
  token: string | null;
  error?: string | null;
  success?: string | null;
  leaveDays?: number;
}

export default function ManagerLeaveReview({ leaveApplication, token, error, success, leaveDays }: Props) {
  console.log('Props:', { leaveApplication, token, error, success, leaveDays });

  const [showToast, setShowToast] = useState(false);
  const [toastMessage, setToastMessage] = useState("");
  const [toastType, setToastType] = useState<"success" | "error">("success");

  const { data, setData, post, processing, errors } = useForm({
    action: "" as "approve" | "deny" | "",
  });

  useEffect(() => {
    if (success) {
      setToastMessage(success);
      setToastType("success");
      setShowToast(true);
    } else if (error) {
      setToastMessage(error);
      setToastType("error");
      setShowToast(true);
    }
  }, [success, error]);

  useEffect(() => {
    if (Object.keys(errors).length > 0) {
      const errorMessage = Object.values(errors).join(' | ') || 'An error occurred while processing the request.';
      setToastMessage(errorMessage);
      setToastType("error");
      setShowToast(true);
    }
  }, [errors]);

  useEffect(() => {
    if (showToast) {
      const duration = toastType === "error" ? 60000 : 3000;
      const timer = setTimeout(() => setShowToast(false), duration);
      return () => clearTimeout(timer);
    }
  }, [showToast, toastType]);

  // Debounced function to handle form submission
  const submitAction = debounce(() => {
    if (!data.action) {
      console.log('No action set, aborting submission');
      setToastMessage('Please select an action (Approve or Deny).');
      setToastType("error");
      setShowToast(true);
      return;
    }
    console.log('Submitting form with data:', data);
    post(route("manager-leave.process", { token }), {
      onSuccess: () => {
        console.log('Post request successful');
        setShowToast(true);
      },
      onError: (errors) => {
        console.log('Post request failed with errors:', errors);
      },
    });
  }, 300);

  // Handle button clicks
  const handleAction = (action: "approve" | "deny") => {
    console.log('handleAction called with action:', action);
    setData("action", action);
  };

  // Trigger submission when data.action changes
  useEffect(() => {
    if (data.action) {
      submitAction();
    }
  }, [data.action]);

  // Format date to "7th August, 2025"
  const formatDate = (date: string | null) => {
    if (!date) return "N/A";
    return new Date(date).toLocaleDateString("en-GB", {
      day: "numeric",
      month: "long",
      year: "numeric",
    }).replace(/(\d+)(st|nd|rd|th)/, "$1$2");
  };

  return (
    <ErrorBoundary>
      <Head title="Leave Application Review" />
      <div className="flex min-h-screen flex-col items-center justify-center bg-white p-4">
        {showToast && (
          <div
            className={`fixed top-4 right-4 z-50 flex items-center gap-2 rounded-lg p-4 shadow-lg w-full sm:w-auto max-w-sm ${
              toastType === "success" ? "bg-green-500 text-white" : "bg-red-500 text-white"
            } animate-in fade-in slide-in-from-top-5`}
          >
            {toastType === "success" ? <Check className="h-5 w-5" /> : <X className="h-5 w-5" />}
            <span>{toastMessage}</span>
            {toastType === "error" && (
              <Button
                variant="ghost"
                size="sm"
                className="ml-2 p-1 text-white hover:text-white hover:bg-red-600"
                onClick={() => setShowToast(false)}
              >
                <X className="h-4 w-4" />
              </Button>
            )}
          </div>
        )}

        <Card className="w-full max-w-2xl bg-white text-black">
          <CardHeader>
            <CardTitle>Leave Application Review</CardTitle>
          </CardHeader>
          <CardContent>
            {leaveApplication ? (
              <div className="grid gap-4">
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <strong>Staff ID:</strong> {leaveApplication.ida}
                  </div>
                  <div>
                    <strong>Employee Name:</strong> {leaveApplication.name || "N/A"}
                  </div>
                  <div>
                    <strong>Leave Type:</strong> {leaveApplication.type || "N/A"}
                  </div>
                  <div>
                    <strong>Start Date:</strong> {formatDate(leaveApplication.date_start)}
                  </div>
                  <div>
                    <strong>End Date:</strong> {formatDate(leaveApplication.date_end)}
                  </div>
                  <div>
                    <strong>Manager Name:</strong> {leaveApplication.managername || "N/A"}
                  </div>
                  <div>
                    <strong>Phone:</strong> {leaveApplication.phone || "N/A"}
                  </div>
                  <div>
                    <strong>Total Leave Days:</strong> {leaveDays ?? "N/A"}
                  </div>
                </div>
                <div>
                  <strong>Reason:</strong>
                  <p className="mt-1">{leaveApplication.reason || "N/A"}</p>
                </div>
                <div className="flex flex-col sm:flex-row gap-4 mt-4">
                  <Button
                    variant="default"
                    className="bg-green-600 hover:bg-green-700"
                    onClick={() => handleAction("approve")}
                    disabled={processing}
                  >
                    <Check className="h-4 w-4 mr-2" />
                    Approve
                  </Button>
                  <Button
                    variant="destructive"
                    className="bg-red-600 hover:bg-red-700"
                    onClick={() => handleAction("deny")}
                    disabled={processing}
                  >
                    <X className="h-4 w-4 mr-2" />
                    Deny
                  </Button>
                </div>
              </div>
            ) : (
              <p className="text-center text-gray-500">
                {success || error || "No leave application available for review."}
              </p>
            )}
          </CardContent>
        </Card>
      </div>
    </ErrorBoundary>
  );
}
