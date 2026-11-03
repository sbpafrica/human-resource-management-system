import AppLayout from '@/layouts/app-layout';
import { Head, router } from '@inertiajs/react';
import React, { useState, useEffect } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { Switch } from '@/components/ui/switch';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/components/ui/dialog';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import {
  Plus,
  Trash2,
  CheckCircle2,
  AlertCircle,
  Eye,
  EyeOff,
  Database,
  Columns,
  Layers,
  FileText,
  HelpCircle,
  Globe,
} from 'lucide-react';

interface ClientItem {
  id: number;
  client_name: string;
  short_name: string;
  client_dbname: string;
}

interface PayrollColumnItem {
  id: number;
  client_id: number | null;
  client_dbname: string;
  column_name: string;
  display_label: string;
  data_type: string;
  position_after: string | null;
  show_in_payslip: boolean;
  payslip_section: 'earnings' | 'deductions' | 'tax_reference' | 'info';
  is_active: boolean;
  created_at?: string;
  updated_at?: string;
}

interface Props {
  clients: ClientItem[];
  selectedClient: string;
  columns: PayrollColumnItem[];
  existingColumns: string[];
  flash?: {
    success?: string;
    error?: string;
  };
}

export default function PayrollColumnsIndex({
  clients,
  selectedClient,
  columns,
  existingColumns,
  flash,
}: Props) {
  const [isDialogOpen, setIsDialogOpen] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);

  // Form State
  const [targetClient, setTargetClient] = useState(selectedClient || 'all');
  const [dialogExistingCols, setDialogExistingCols] = useState<string[]>(existingColumns);
  const [displayLabel, setDisplayLabel] = useState('');
  const [columnName, setColumnName] = useState('');
  const [isAutoSlug, setIsAutoSlug] = useState(true);
  const [dataType, setDataType] = useState('decimal(10,2)');
  const [positionAfter, setPositionAfter] = useState<string>('END');
  const [showInPayslip, setShowInPayslip] = useState(true);
  const [payslipSection, setPayslipSection] = useState<'earnings' | 'deductions' | 'tax_reference' | 'info'>('earnings');

  // Sync state when dialog opens or client changes
  useEffect(() => {
    if (isDialogOpen) {
      setTargetClient(selectedClient || 'all');
      setDialogExistingCols(existingColumns);
    }
  }, [isDialogOpen, selectedClient, existingColumns]);

  // Client switch handler
  const handleClientChange = (clientDb: string) => {
    router.get(
      '/admin/payroll-columns',
      { client: clientDb },
      { preserveState: true, preserveScroll: true }
    );
  };

  // Target client change inside dialog (fetches columns dynamically)
  const handleTargetClientChange = async (clientDb: string) => {
    setTargetClient(clientDb);
    try {
      const res = await fetch(`/admin/payroll-columns/columns?client_dbname=${clientDb}`);
      if (res.ok) {
        const data = await res.json();
        if (Array.isArray(data) && data.length > 0) {
          setDialogExistingCols(data);
        }
      }
    } catch (e) {
      // fallback to current existingColumns
    }
  };

  // Auto-slug display label to snake_case column_name
  const handleLabelChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const val = e.target.value;
    setDisplayLabel(val);
    if (isAutoSlug) {
      const slug = val
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '_')
        .replace(/^_+|_+$/g, '');
      setColumnName(slug);
    }
  };

  const handleColumnNameChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    setIsAutoSlug(false);
    const sanitized = e.target.value.toLowerCase().replace(/[^a-z0-9_]/g, '');
    setColumnName(sanitized);
  };

  const resetForm = () => {
    setDisplayLabel('');
    setColumnName('');
    setIsAutoSlug(true);
    setDataType('decimal(10,2)');
    setPositionAfter('END');
    setShowInPayslip(true);
    setPayslipSection('earnings');
    setTargetClient(selectedClient || 'all');
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!displayLabel.trim()) return;

    setIsSubmitting(true);
    router.post(
      '/admin/payroll-columns',
      {
        client_dbname: targetClient,
        display_label: displayLabel.trim(),
        column_name: columnName.trim() || undefined,
        data_type: dataType,
        position_after: positionAfter === 'END' ? null : positionAfter,
        show_in_payslip: showInPayslip,
        payslip_section: payslipSection,
      },
      {
        onSuccess: () => {
          setIsDialogOpen(false);
          resetForm();
          setIsSubmitting(false);
        },
        onError: () => {
          setIsSubmitting(false);
        },
      }
    );
  };

  const handleTogglePayslip = (colId: number) => {
    router.patch(
      `/admin/payroll-columns/${colId}/toggle-payslip`,
      {},
      { preserveScroll: true }
    );
  };

  const handleDeleteColumn = (col: PayrollColumnItem) => {
    if (
      confirm(
        `Are you sure you want to remove the custom column '${col.display_label}' from the payroll registry? (Existing database data will remain safe).`
      )
    ) {
      router.delete(`/admin/payroll-columns/${col.id}`, {
        preserveScroll: true,
      });
    }
  };

  const currentClientObj = clients.find((c) => c.client_dbname === selectedClient);
  const targetClientObj = clients.find((c) => c.client_dbname === targetClient);
  const payslipCount = columns.filter((c) => c.show_in_payslip).length;

  return (
    <AppLayout>
      <Head title="Admin - Payroll Columns Configuration" />

      <div className="p-6 space-y-6 max-w-7xl mx-auto">
        {/* Flash Notifications */}
        {flash?.success && (
          <div className="p-4 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-300 dark:border-emerald-800 rounded-lg text-emerald-800 dark:text-emerald-200 flex items-center gap-3">
            <CheckCircle2 className="w-5 h-5 text-emerald-600 shrink-0" />
            <span className="text-sm font-medium">{flash.success}</span>
          </div>
        )}
        {flash?.error && (
          <div className="p-4 bg-rose-50 dark:bg-rose-950/40 border border-rose-300 dark:border-rose-800 rounded-lg text-rose-800 dark:text-rose-200 flex items-center gap-3">
            <AlertCircle className="w-5 h-5 text-rose-600 shrink-0" />
            <span className="text-sm font-medium">{flash.error}</span>
          </div>
        )}

        {/* Page Header */}
        <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b pb-5">
          <div>
            <div className="flex items-center gap-2">
              <Columns className="w-6 h-6 text-blue-600" />
              <h1 className="text-2xl font-bold tracking-tight text-gray-900 dark:text-gray-100">
                Custom Payroll Columns
              </h1>
            </div>
            <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">
              Add and manage dynamic columns for any client or globally across all clients in the system.
            </p>
          </div>

          <div className="flex items-center gap-3">
            {/* Client Picker */}
            <div className="w-72">
              <Select value={selectedClient} onValueChange={handleClientChange}>
                <SelectTrigger className="w-full bg-white dark:bg-gray-800">
                  <SelectValue placeholder="Select Client" />
                </SelectTrigger>
                <SelectContent className="max-h-72">
                  {clients.map((c) => (
                    <SelectItem key={c.client_dbname} value={c.client_dbname}>
                      {c.client_name || c.short_name} {c.client_dbname !== 'all' ? `(${c.client_dbname})` : ''}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            {/* Add Column Dialog Trigger */}
            <Dialog open={isDialogOpen} onOpenChange={setIsDialogOpen}>
              <DialogTrigger asChild>
                <Button className="bg-blue-600 hover:bg-blue-700 text-white shadow-sm flex items-center gap-2">
                  <Plus className="w-4 h-4" />
                  Add New Column
                </Button>
              </DialogTrigger>

              <DialogContent className="max-w-xl">
                <DialogHeader>
                  <DialogTitle className="flex items-center gap-2">
                    <Database className="w-5 h-5 text-blue-600" />
                    Add Custom Payroll Column
                  </DialogTitle>
                  <DialogDescription>
                    Configure a new column. It will be added to all existing monthly tables and ensured on future uploads.
                  </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4 pt-2">
                  {/* Client Selector inside Dialog */}
                  <div className="space-y-1.5">
                    <Label htmlFor="targetClient">Target Client / Scope <span className="text-red-500">*</span></Label>
                    <Select value={targetClient} onValueChange={handleTargetClientChange}>
                      <SelectTrigger id="targetClient" className="bg-white dark:bg-gray-800">
                        <SelectValue placeholder="Select Target Client" />
                      </SelectTrigger>
                      <SelectContent className="max-h-60">
                        {clients.map((c) => (
                          <SelectItem key={c.client_dbname} value={c.client_dbname}>
                            {c.client_name || c.short_name} {c.client_dbname !== 'all' ? `(${c.client_dbname})` : ''}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                    <p className="text-xs text-muted-foreground flex items-center gap-1.5 mt-1">
                      {targetClient === 'all' ? (
                        <span className="text-blue-600 font-medium flex items-center gap-1">
                          <Globe className="w-3.5 h-3.5" /> Global: applies to ALL clients across the entire database.
                        </span>
                      ) : (
                        <span>Specific to: <strong>{targetClientObj?.client_name || targetClient}</strong></span>
                      )}
                    </p>
                  </div>

                  <div className="grid grid-cols-2 gap-4">
                    <div className="space-y-1.5">
                      <Label htmlFor="displayLabel">
                        Display Label <span className="text-red-500">*</span>
                      </Label>
                      <Input
                        id="displayLabel"
                        value={displayLabel}
                        onChange={handleLabelChange}
                        placeholder="e.g. Total Cash Emolument"
                        required
                        autoFocus
                      />
                      <p className="text-xs text-muted-foreground">Label for payslips & exports.</p>
                    </div>

                    <div className="space-y-1.5">
                      <Label htmlFor="columnName">
                        Database Column Name <span className="text-red-500">*</span>
                      </Label>
                      <Input
                        id="columnName"
                        value={columnName}
                        onChange={handleColumnNameChange}
                        placeholder="e.g. total_cash_emolument"
                        required
                        className="font-mono text-sm"
                      />
                      <p className="text-xs text-muted-foreground">SQL column identifier (snake_case).</p>
                    </div>
                  </div>

                  {/* Data Type & Position */}
                  <div className="grid grid-cols-2 gap-4">
                    <div className="space-y-1.5">
                      <Label htmlFor="dataType">Data Type</Label>
                      <Select value={dataType} onValueChange={setDataType}>
                        <SelectTrigger id="dataType">
                          <SelectValue placeholder="Select type" />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value="decimal(10,2)">Decimal (10, 2) - Standard Amount</SelectItem>
                          <SelectItem value="decimal(15,2)">Decimal (15, 2) - Large Amount</SelectItem>
                          <SelectItem value="int">Integer (Whole Number / Days)</SelectItem>
                          <SelectItem value="varchar(255)">Varchar (Text / Code)</SelectItem>
                          <SelectItem value="text">Long Text / Notes</SelectItem>
                          <SelectItem value="date">Date (YYYY-MM-DD)</SelectItem>
                          <SelectItem value="boolean">Boolean (Yes / No)</SelectItem>
                        </SelectContent>
                      </Select>
                      <p className="text-xs text-muted-foreground">MySQL column data type definition.</p>
                    </div>

                    <div className="space-y-1.5">
                      <Label htmlFor="positionAfter">Physical Position (AFTER)</Label>
                      <Select value={positionAfter} onValueChange={setPositionAfter}>
                        <SelectTrigger id="positionAfter">
                          <SelectValue placeholder="Select position" />
                        </SelectTrigger>
                        <SelectContent className="max-h-56">
                          <SelectItem value="END">At the end of table (Default)</SelectItem>
                          {dialogExistingCols.map((col) => (
                            <SelectItem key={col} value={col}>
                              After: {col}
                            </SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                      <p className="text-xs text-muted-foreground">Where to place column in the table structure.</p>
                    </div>
                  </div>

                  {/* Payslip Visibility Section */}
                  <div className="p-4 rounded-lg bg-gray-50 dark:bg-gray-800/60 border border-gray-200 dark:border-gray-700 space-y-3">
                    <div className="flex items-center justify-between">
                      <div>
                        <Label htmlFor="showInPayslip" className="text-sm font-semibold cursor-pointer">
                          Show in Employee Payslips?
                        </Label>
                        <p className="text-xs text-muted-foreground">
                          When enabled, this column automatically renders on employee payslip PDFs.
                        </p>
                      </div>
                      <Switch
                        id="showInPayslip"
                        checked={showInPayslip}
                        onCheckedChange={setShowInPayslip}
                      />
                    </div>

                    {showInPayslip && (
                      <div className="pt-2 border-t border-gray-200 dark:border-gray-700 space-y-1.5">
                        <Label htmlFor="payslipSection">Payslip Section</Label>
                        <Select
                          value={payslipSection}
                          onValueChange={(val: any) => setPayslipSection(val)}
                        >
                          <SelectTrigger id="payslipSection">
                            <SelectValue placeholder="Select section" />
                          </SelectTrigger>
                          <SelectContent>
                            <SelectItem value="earnings">Earnings & Allowances (Credits)</SelectItem>
                            <SelectItem value="deductions">Deductions (Debits)</SelectItem>
                            <SelectItem value="tax_reference">Tax Reference / Memo (e.g. Assessable Income)</SelectItem>
                            <SelectItem value="info">General Information / Meta</SelectItem>
                          </SelectContent>
                        </Select>
                        <p className="text-xs text-muted-foreground">
                          Controls which section in the PDF payslip this item appears under.
                        </p>
                      </div>
                    )}
                  </div>

                  <div className="bg-blue-50 dark:bg-blue-950/40 p-3 rounded text-xs text-blue-700 dark:text-blue-300 flex items-start gap-2">
                    <HelpCircle className="w-4 h-4 shrink-0 mt-0.5" />
                    <span>
                      Saving will immediately execute <code className="font-mono font-semibold">ALTER TABLE</code> on all monthly tables for{' '}
                      <strong>{targetClient === 'all' ? 'ALL clients across the database' : (targetClientObj?.client_name || targetClient)}</strong> and ensure it is created on future monthly uploads.
                    </span>
                  </div>

                  <DialogFooter className="pt-2">
                    <Button
                      type="button"
                      variant="outline"
                      onClick={() => setIsDialogOpen(false)}
                      disabled={isSubmitting}
                    >
                      Cancel
                    </Button>
                    <Button
                      type="submit"
                      className="bg-blue-600 hover:bg-blue-700 text-white"
                      disabled={isSubmitting || !displayLabel.trim()}
                    >
                      {isSubmitting ? 'Creating Column...' : 'Save & Apply Column'}
                    </Button>
                  </DialogFooter>
                </form>
              </DialogContent>
            </Dialog>
          </div>
        </div>

        {/* Stats Row */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">Custom Columns</CardTitle>
              <Database className="h-4 w-4 text-blue-600" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{columns.length}</div>
              <p className="text-xs text-muted-foreground mt-1">
                Active custom columns registered for {selectedClient === 'all' ? 'All Clients (Global)' : (currentClientObj?.client_name || selectedClient)}
              </p>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">Payslip Visible</CardTitle>
              <FileText className="h-4 w-4 text-emerald-600" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{payslipCount}</div>
              <p className="text-xs text-muted-foreground mt-1">
                Rendered on employee payslips when values exist
              </p>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">Active Schema Columns</CardTitle>
              <Layers className="h-4 w-4 text-purple-600" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{existingColumns.length}</div>
              <p className="text-xs text-muted-foreground mt-1">
                Total base columns currently detected on monthly table
              </p>
            </CardContent>
          </Card>
        </div>

        {/* Custom Columns Table */}
        <Card>
          <CardHeader>
            <div className="flex items-center justify-between">
              <div>
                <CardTitle>Registered Custom Columns</CardTitle>
                <CardDescription>
                  Custom payroll columns configured for{' '}
                  <span className="font-semibold text-blue-600">
                    {selectedClient === 'all' ? 'All Clients (Global Across System)' : (currentClientObj?.client_name || selectedClient)}
                  </span>
                </CardDescription>
              </div>
            </div>
          </CardHeader>

          <CardContent>
            {columns.length === 0 ? (
              <div className="text-center py-12 border border-dashed rounded-lg">
                <Database className="mx-auto h-10 w-10 text-gray-400" />
                <h3 className="mt-2 text-sm font-semibold text-gray-900 dark:text-gray-100">
                  No custom columns yet
                </h3>
                <p className="mt-1 text-sm text-gray-500">
                  Get started by adding a custom allowance, deduction, or tax reference column for this client or globally.
                </p>
                <div className="mt-4">
                  <Button
                    onClick={() => setIsDialogOpen(true)}
                    className="bg-blue-600 hover:bg-blue-700 text-white"
                  >
                    <Plus className="w-4 h-4 mr-2" />
                    Add Column Now
                  </Button>
                </div>
              </div>
            ) : (
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Display Label</TableHead>
                    <TableHead>Column Name</TableHead>
                    <TableHead>Data Type</TableHead>
                    <TableHead>Position</TableHead>
                    <TableHead>Payslip Section</TableHead>
                    <TableHead>Payslip Status</TableHead>
                    <TableHead className="text-right">Actions</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {columns.map((col) => (
                    <TableRow key={col.id}>
                      <TableCell className="font-medium text-gray-900 dark:text-gray-100">
                        {col.display_label}
                      </TableCell>
                      <TableCell>
                        <code className="text-xs bg-gray-100 dark:bg-gray-800 text-blue-600 dark:text-blue-400 px-2 py-1 rounded font-mono">
                          {col.column_name}
                        </code>
                      </TableCell>
                      <TableCell>
                        <Badge variant="outline" className="font-mono text-xs">
                          {col.data_type}
                        </Badge>
                      </TableCell>
                      <TableCell className="text-sm text-gray-500">
                        {col.position_after ? (
                          <span>
                            After <code className="text-xs bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 rounded">{col.position_after}</code>
                          </span>
                        ) : (
                          <span className="text-gray-400 italic">End of Table</span>
                        )}
                      </TableCell>
                      <TableCell>
                        {col.show_in_payslip ? (
                          <Badge
                            className={
                              col.payslip_section === 'earnings'
                                ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300'
                                : col.payslip_section === 'deductions'
                                ? 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300'
                                : 'bg-purple-100 text-purple-800 dark:bg-purple-950 dark:text-purple-300'
                            }
                          >
                            {col.payslip_section.toUpperCase()}
                          </Badge>
                        ) : (
                          <span className="text-gray-400 text-xs">-</span>
                        )}
                      </TableCell>
                      <TableCell>
                        <button
                          type="button"
                          onClick={() => handleTogglePayslip(col.id)}
                          className="flex items-center gap-1.5 text-xs font-medium cursor-pointer hover:underline"
                          title="Click to toggle visibility on employee payslip"
                        >
                          {col.show_in_payslip ? (
                            <span className="inline-flex items-center gap-1 text-emerald-600">
                              <Eye className="w-3.5 h-3.5" /> Visible
                            </span>
                          ) : (
                            <span className="inline-flex items-center gap-1 text-gray-400">
                              <EyeOff className="w-3.5 h-3.5" /> Hidden
                            </span>
                          )}
                        </button>
                      </TableCell>
                      <TableCell className="text-right">
                        <Button
                          variant="ghost"
                          size="sm"
                          onClick={() => handleDeleteColumn(col)}
                          className="text-red-600 hover:text-red-700 hover:bg-red-50 dark:hover:bg-red-950/40"
                          title="Remove from custom columns registry"
                        >
                          <Trash2 className="w-4 h-4" />
                        </Button>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            )}
          </CardContent>
        </Card>

        {/* Architecture & Workflow Guidance Card */}
        <Card className="bg-slate-50 dark:bg-slate-900/40 border-slate-200 dark:border-slate-800">
          <CardHeader>
            <CardTitle className="text-sm font-semibold flex items-center gap-2">
              <HelpCircle className="w-4 h-4 text-blue-500" />
              Dynamic Payroll Columns for All 33 Clients & Global System
            </CardTitle>
          </CardHeader>
          <CardContent className="text-xs text-gray-600 dark:text-gray-400 space-y-2">
            <p>
              • <strong>Client-Specific or Global:</strong> Select a specific client (e.g. Shefer, PZ, Transsion BFA, Nyonkopa, Melcom, CIV, Togo, Benin, etc.) to add a column exclusively for them, or choose <strong>★ All Clients (Global)</strong> to apply across every single client in the system.
            </p>
            <p>
              • <strong>Instant Safe Alterations:</strong> Saving a column immediately runs an SQL <code className="font-mono">ALTER TABLE ... ADD COLUMN ... AFTER ...</code> on all existing monthly tables for the chosen client(s).
            </p>
            <p>
              • <strong>Future Uploads:</strong> When new months are uploaded for any client, the monthly table creation automatically verifies and applies all custom columns.
            </p>
            <p>
              • <strong>Payslip Automation:</strong> Columns marked as "Show in Payslip" automatically render in individual and bulk PDF payslips for that client without requiring manual template alterations.
            </p>
          </CardContent>
        </Card>
      </div>
    </AppLayout>
  );
}
