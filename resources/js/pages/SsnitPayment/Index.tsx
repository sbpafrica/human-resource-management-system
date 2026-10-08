import AppLayout from "@/layouts/app-layout";
import { Head, Link } from "@inertiajs/react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Building2, Users } from "lucide-react";

const breadcrumbs = [{ title: "SSNIT Payment", href: "/ssnit-payment" }];

export default function SsnitPaymentIndex({ clients }: { clients: any[] }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="SSNIT Payment" />
            <div className="p-6">
                <div className="flex items-center justify-between mb-8">
                    <div>
                        <h1 className="text-4xl font-bold flex items-center gap-3">
                            <Users className="h-10 w-10 text-purple-600" />
                            SSNIT Payment Summary
                        </h1>
                        <p className="text-muted-foreground mt-2">Select a client to view SSNIT contribution details</p>
                    </div>
                </div>

                {clients.length === 0 ? (
                    <Card className="p-12 text-center">
                        <p className="text-gray-500">No active clients found.</p>
                    </Card>
                ) : (
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                        {clients.map((client) => (
                            <Link
                                key={client.client_dbname}
                                href={route('ssnitpayment.client', client.client_dbname)}
                                className="transform transition-all hover:scale-105"
                            >
                                <Card className="h-full hover:shadow-2xl border-2 hover:border-purple-500 cursor-pointer">
                                    <CardHeader>
                                        <CardTitle className="flex items-center gap-3">
                                            <Building2 className="h-9 w-9 text-purple-600" />
                                            {client.name}
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <p className="text-sm text-gray-600">Click to view SSNIT details</p>
                                        <p className="text-xs font-mono mt-3 bg-gray-100 p-2 rounded">
                                            {client.client_dbname}
                                        </p>
                                    </CardContent>
                                </Card>
                            </Link>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
