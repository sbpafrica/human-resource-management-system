import { Head } from "@inertiajs/react";
import { Button } from "@/components/ui/button";
import AppLayout from "@/layouts/app-layout";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { useState } from "react";
import ErrorBoundary from "@/components/ErrorBoundary";

const breadcrumbs = [
  { title: "Century", href: "/centurystat" },
];

export default function CenturyStatIndex({ flash }: { flash?: { success?: string; error?: string } }) {
  const [month, setMonth] = useState<number>(new Date().getMonth() + 1);
  const [year, setYear] = useState<number>(new Date().getFullYear());

  const handleGenerate = () => {
    const url = route("centurystat.generate", { month, year });
    window.location.href = url;
  };

  return (
    <ErrorBoundary>
      <AppLayout breadcrumbs={breadcrumbs}>
        <Head title="Century Statutory Reports" />
        <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
          <h1 className="text-2xl font-bold">Generate GRA Report</h1>
          <div className="flex flex-col sm:flex-row gap-4">
            <Select value={month.toString()} onValueChange={(value) => setMonth(parseInt(value))}>
              <SelectTrigger className="w-full sm:w-48">
                <SelectValue placeholder="Select Month" />
              </SelectTrigger>
              <SelectContent>
                {Array.from({ length: 12 }, (_, i) => i + 1).map((m) => (
                  <SelectItem key={m} value={m.toString()}>
                    {new Date(0, m - 1).toLocaleString("default", { month: "long" })}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            <Select value={year.toString()} onValueChange={(value) => setYear(parseInt(value))}>
              <SelectTrigger className="w-full sm:w-48">
                <SelectValue placeholder="Select Year" />
              </SelectTrigger>
              <SelectContent>
                {Array.from({ length: 11 }, (_, i) => new Date().getFullYear() - 5 + i).map((y) => (
                  <SelectItem key={y} value={y.toString()}>
                    {y}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            <Button onClick={handleGenerate} className="w-full sm:w-auto">
              Generate Report
            </Button>
          </div>
        </div>
      </AppLayout>
    </ErrorBoundary>
  );
}
