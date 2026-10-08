// resources/js/pages/Payroll/ClientSummary/Index.tsx
import { Head, Link, usePage } from "@inertiajs/react";
import AppLayout from "@/layouts/app-layout";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Briefcase, DollarSign, Receipt, ScrollText } from "lucide-react";

interface Client {
  id: number;
  client_name: string;
  short_name: string;
  client_dbname: string;
  color: string;
}

interface Props {
  client: Client;
}

export default function ClientSummary({ client }: Props) {
  const breadcrumbs = [
    { title: "Payroll", href: "/payroll" },
    { title: client.short_name || client.client_name, href: "#" },
  ];

  const menuItems = [
    {
      title: "Salary Payment",
      icon: DollarSign,
      href: `/payroll/${client.client_dbname}/bank-payment`,
      color: "bg-green-500",
    },
    {
      title: "Payslips",
      icon: Receipt,
      href: `/payslips?client=${client.client_dbname}`,
      color: "bg-blue-500",
    },
    {
      title: "Statutory Reports",
      icon: ScrollText,
      href: `/transsionstat?client=${client.client_dbname}`,
      color: "bg-purple-500",
    },
    {
      title: "SSNIT Pay",
      icon: Briefcase,
      href: `/ssnitpay?client=${client.client_dbname}`,
      color: "bg-orange-500",
    },
  ];

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title={client.short_name || client.client_name} />
      <div className="p-6">
        <div className="mb-8">
          <h1 className="text-3xl font-bold flex items-center gap-3">
            <div className="w-4 h-4 rounded-full" style={{ backgroundColor: client.color }} />
            {client.short_name || client.client_name}
          </h1>
          <p className="text-muted-foreground mt-2">Client DB: {client.client_dbname}</p>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          {menuItems.map((item) => (
            <Link key={item.title} href={item.href}>
              <Card className="hover:shadow-lg transition-shadow cursor-pointer border-2 hover:border-primary">
                <CardHeader className="pb-3">
                  <div className={`${item.color} w-12 h-12 rounded-lg flex items-center justify-center text-white`}>
                    <item.icon className="w-6 h-6" />
                  </div>
                </CardHeader>
                <CardContent>
                  <CardTitle className="text-lg">{item.title}</CardTitle>
                </CardContent>
              </Card>
            </Link>
          ))}
        </div>

        <div className="mt-12">
          <Card>
            <CardHeader>
              <CardTitle>Quick Stats - November 2025</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="text-center py-8 text-muted-foreground">
                Payroll processing for <strong>transsionnovember25</strong> and <strong>transsiontecnovember25</strong> is active.
              </div>
            </CardContent>
          </Card>
        </div>
      </div>
    </AppLayout>
  );
}
