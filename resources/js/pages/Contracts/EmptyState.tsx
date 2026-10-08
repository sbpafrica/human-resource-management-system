// resources/js/pages/Contracts/EmptyState.tsx

import { Head } from "@inertiajs/react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Input } from "@/components/ui/input";
import { FileQuestion, FolderSearch } from "lucide-react";
import AppLayout from "@/layouts/app-layout";
import ErrorBoundary from "@/components/ErrorBoundary";
import { useState } from "react";

interface Props {
  clientName: string;
  clientSlug: string;
  type: "contracts" | "renewals" | "petra";
}

export default function EmptyState({ clientName, clientSlug, type }: Props) {
  const [search, setSearch] = useState("");
  const isContracts = type === "contracts";
  const isPetra = type === "petra";
  
  let sectionTitle = "Contracts";
  let sectionParent = "CONTRACT";
  let sectionHref = `/${clientSlug}/contracts`;
  if (type === "renewals") {
    sectionTitle = "Contract Renewals";
    sectionParent = "CONTRACT";
    sectionHref = `/${clientSlug}/contract-renewals`;
  } else if (type === "petra") {
    sectionTitle = "PETRA Payment";
    sectionParent = "PETRA Payment";
    sectionHref = `/${clientSlug}petrapay`;
  }

  const title = `${clientName} ${sectionTitle}`;
  const breadcrumbs = [
    { title: sectionParent, href: "#" },
    { title: clientName, href: sectionHref },
  ];

  return (
    <ErrorBoundary>
      <AppLayout breadcrumbs={breadcrumbs}>
        <Head title={title} />
        <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <h1 className="text-2xl font-bold">{title}</h1>
            <div className="flex gap-4">
              <Input
                placeholder="Search..."
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                className="w-full sm:w-64"
                disabled
              />
            </div>
          </div>

          <Card>
            <CardHeader>
              <CardTitle>{title}</CardTitle>
              <div className="text-sm text-muted-foreground">
                Total Records: 0
              </div>
            </CardHeader>
            <CardContent>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>No.</TableHead>
                    <TableHead>Staff ID</TableHead>
                    <TableHead>Employee Name</TableHead>
                    <TableHead>{isPetra ? "Shop / Department" : "Position"}</TableHead>
                    <TableHead>{isPetra ? "Tier 2 / PETRA ID" : "Location / Department"}</TableHead>
                    <TableHead>{isPetra ? "Ghana Card" : "Start Date"}</TableHead>
                    <TableHead>{isPetra ? "Basic (GHS)" : (isContracts ? "End Date" : "Expired Date")}</TableHead>
                    <TableHead>{isPetra ? "PETRA (5%) (GHS)" : "Status"}</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  <TableRow>
                    <TableCell colSpan={8} className="text-center py-12 text-muted-foreground">
                      <div className="flex flex-col items-center justify-center gap-2">
                        <FileQuestion className="h-10 w-10 text-gray-400" />
                        <p className="text-base font-medium text-gray-700">
                          No {isPetra ? "PETRA contribution records" : (isContracts ? "contracts" : "renewals")} available for {clientName}
                        </p>
                        <p className="text-xs text-gray-500">
                          There are currently no {isPetra ? "PETRA payment" : (isContracts ? "contract" : "contract renewal")} records configured for this company.
                        </p>
                      </div>
                    </TableCell>
                  </TableRow>
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        </div>
      </AppLayout>
    </ErrorBoundary>
  );
}
