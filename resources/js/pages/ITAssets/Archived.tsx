import { Head, Link, router } from "@inertiajs/react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
  Archive,
  ArrowLeft,
  RotateCcw,
  Search,
  CheckCircle2,
  AlertCircle,
  Laptop,
} from "lucide-react";
import AppLayout from "@/layouts/app-layout";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { Input } from "@/components/ui/input";
import { useState, useEffect } from "react";
import ErrorBoundary from "@/components/ErrorBoundary";

interface ArchivedAsset {
  archive_id: number;
  asset_id: number | null;
  asset_tag: string;
  category_name: string;
  name: string;
  brand: string | null;
  model: string | null;
  location: string | null;
  allocation_status: string | null;
  condition: string | null;
  archived_by: string | null;
  archived_date: string | null;
}

interface Props {
  archives: {
    data: ArchivedAsset[];
    links: any[];
    total: number;
  };
  flash?: {
    success?: string;
    error?: string;
  };
}

const breadcrumbs = [
  { title: "IT Assets", href: "/it-assets" },
  { title: "Archived Assets", href: "/it-assets/archived" },
];

export default function ITAssetsArchived({ archives, flash }: Props) {
  const [showToast, setShowToast] = useState(false);
  const [toastMessage, setToastMessage] = useState("");
  const [toastType, setToastType] = useState<"success" | "error">("success");
  const [search, setSearch] = useState("");

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
      const timer = setTimeout(() => setShowToast(false), 4000);
      return () => clearTimeout(timer);
    }
  }, [showToast]);

  const handleSearch = () => {
    router.get(
      route("itassets.archived"),
      { search },
      { preserveState: true, replace: true }
    );
  };

  const handleRestore = (archiveId: number) => {
    router.post(
      route("itassets.restore", archiveId),
      {},
      {
        preserveScroll: true,
      }
    );
  };

  return (
    <ErrorBoundary>
      <AppLayout breadcrumbs={breadcrumbs}>
        <Head title="Archived IT Assets" />
        <div className="flex h-full flex-1 flex-col gap-6 p-6 bg-gray-50 dark:bg-gray-900 rounded-xl">
          {/* Toast Notification */}
          {showToast && (
            <div
              className={`fixed top-4 right-4 z-50 flex items-center gap-2 rounded-lg p-4 shadow-lg w-full sm:w-auto max-w-sm ${
                toastType === "success" ? "bg-green-600 text-white" : "bg-red-600 text-white"
              } animate-in fade-in slide-in-from-top-5`}
            >
              {toastType === "success" ? (
                <CheckCircle2 className="h-5 w-5" />
              ) : (
                <AlertCircle className="h-5 w-5" />
              )}
              <span>{toastMessage}</span>
            </div>
          )}

          {/* Top Actions & Header */}
          <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
              <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                <Archive className="h-7 w-7 text-amber-600 dark:text-amber-400" />
                Archived IT Assets
              </h1>
              <p className="text-sm text-gray-500 dark:text-gray-400">
                Assets moved out of active inventory — {archives.total || 0} total records
              </p>
            </div>
            <Link href={route("itassets.index")}>
              <Button variant="outline" className="border-gray-300 dark:border-gray-700">
                <ArrowLeft className="h-4 w-4 mr-2" />
                Back to Active Assets
              </Button>
            </Link>
          </div>

          {/* Search Card */}
          <Card className="shadow-none border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-800">
            <CardHeader className="pb-3">
              <CardTitle className="text-base font-semibold text-gray-800 dark:text-gray-100">
                Search Archived Assets
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div className="max-w-md relative">
                <Search className="absolute left-3 top-2.5 h-4 w-4 text-gray-400" />
                <Input
                  placeholder="Search by tag, name, staff, location..."
                  value={search}
                  onChange={(e) => setSearch(e.target.value)}
                  onKeyDown={(e) => e.key === "Enter" && handleSearch()}
                  className="pl-9"
                />
              </div>
            </CardContent>
          </Card>

          {/* Archived Table */}
          <Card className="shadow-none border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-800">
            <CardHeader className="pb-3">
              <CardTitle className="text-base font-semibold text-gray-800 dark:text-gray-100">
                Archived Assets ({archives.total || 0} records)
              </CardTitle>
            </CardHeader>
            <CardContent>
              {archives.data.length > 0 ? (
                <div className="overflow-x-auto">
                  <Table>
                    <TableHeader>
                      <TableRow className="bg-gray-50 dark:bg-gray-700/50">
                        <TableHead className="font-semibold">Asset Tag</TableHead>
                        <TableHead className="font-semibold">Category</TableHead>
                        <TableHead className="font-semibold">Name</TableHead>
                        <TableHead className="font-semibold">Location</TableHead>
                        <TableHead className="font-semibold">Condition</TableHead>
                        <TableHead className="font-semibold">Archived By</TableHead>
                        <TableHead className="font-semibold">Archived Date</TableHead>
                        <TableHead className="font-semibold text-right">Actions</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {archives.data.map((arch) => (
                        <TableRow key={arch.archive_id} className="hover:bg-gray-50/80 dark:hover:bg-gray-700/50">
                          <TableCell className="font-mono text-xs font-semibold text-amber-600 dark:text-amber-400">
                            {arch.asset_tag}
                          </TableCell>
                          <TableCell className="text-sm font-medium">{arch.category_name}</TableCell>
                          <TableCell className="text-sm">{arch.name}</TableCell>
                          <TableCell className="text-sm">{arch.location || "-"}</TableCell>
                          <TableCell className="text-sm">{arch.condition || "-"}</TableCell>
                          <TableCell className="text-sm">{arch.archived_by || "System"}</TableCell>
                          <TableCell className="text-xs text-gray-500">{arch.archived_date || "-"}</TableCell>
                          <TableCell className="text-right">
                            <Button
                              variant="outline"
                              size="sm"
                              className="text-green-600 border-green-200 hover:bg-green-50 dark:hover:bg-green-950/20"
                              onClick={() => handleRestore(arch.archive_id)}
                            >
                              <RotateCcw className="h-4 w-4 mr-1" />
                              Restore
                            </Button>
                          </TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                </div>
              ) : (
                <div className="text-center py-10 text-gray-500 dark:text-gray-400">
                  No archived assets found.
                </div>
              )}

              {/* Pagination */}
              {archives.links && archives.links.length > 3 && (
                <div className="flex justify-end gap-2 mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                  {archives.links.map((link, i) => (
                    <Button
                      key={i}
                      variant={link.active ? "default" : "outline"}
                      size="sm"
                      disabled={!link.url}
                      onClick={() => link.url && router.get(link.url, {}, { preserveState: true })}
                      dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                  ))}
                </div>
              )}
            </CardContent>
          </Card>
        </div>
      </AppLayout>
    </ErrorBoundary>
  );
}
