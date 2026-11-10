import AppLayout from '@/layouts/app-layout';
import { Head, router } from '@inertiajs/react';
import React, { useState, useEffect, useMemo } from 'react';
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
  SelectGroup,
  SelectItem,
  SelectLabel,
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
  Pencil,
  Lock,
  ShieldCheck,
  RotateCcw,
  Search,
  SlidersHorizontal,
  Sparkles,
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
  header_aliases?: string | null;
  data_type: string;
  position_after: string | null;
  show_in_payslip: boolean;
  payslip_section: 'earnings' | 'deductions' | 'tax_reference' | 'info';
  is_active: boolean;
  created_at?: string;
  updated_at?: string;
}

interface StandardColumnItem {
  id: number | null;
  client_dbname: string;
  is_custom: boolean;
  column_name: string;
  display_label: string;
  default_label: string;
  header_aliases: string | null;
  default_aliases: string;
  data_type: string;
  show_in_payslip: boolean;
  payslip_section: 'earnings' | 'deductions' | 'tax_reference' | 'info';
  description: string;
  has_override: boolean;
  locked?: boolean;
}

interface Props {
  clients: ClientItem[];
  selectedClient: string;
  columns: PayrollColumnItem[];
  standardColumns?: StandardColumnItem[];
  existingColumns: string[];
  isTailored?: boolean;
  showAll?: boolean;
  totalSystemColumns?: number;
  flash?: {
    success?: string;
    error?: string;
  };
}

export default function PayrollColumnsIndex({
  clients,
  selectedClient,
  columns,
  standardColumns = [],
  existingColumns,
  isTailored = false,
  showAll = false,
  totalSystemColumns = 410,
  flash,
}: Props) {
  // Navigation Tab State
  const [activeTab, setActiveTab] = useState<'custom' | 'standard'>('custom');

  // Custom Column Create Dialog State
  const [isDialogOpen, setIsDialogOpen] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);

  // Custom Column Form State
  const [targetClient, setTargetClient] = useState(selectedClient || 'all');
  const [dialogExistingCols, setDialogExistingCols] = useState<string[]>(existingColumns);
  const [displayLabel, setDisplayLabel] = useState('');
  const [columnName, setColumnName] = useState('');
  const [isAutoSlug, setIsAutoSlug] = useState(true);
  const [dataType, setDataType] = useState('decimal(10,2)');
  const [positionAfter, setPositionAfter] = useState<string>('END');
  const [showInPayslip, setShowInPayslip] = useState(true);
  const [payslipSection, setPayslipSection] = useState<'earnings' | 'deductions' | 'tax_reference' | 'info'>('earnings');

  // Custom Column Edit Dialog State
  const [isEditDialogOpen, setIsEditDialogOpen] = useState(false);
  const [editingCol, setEditingCol] = useState<PayrollColumnItem | null>(null);
  const [editDisplayLabel, setEditDisplayLabel] = useState('');
  const [editHeaderAliases, setEditHeaderAliases] = useState('');
  const [editPositionAfter, setEditPositionAfter] = useState<string>('END');
  const [editShowInPayslip, setEditShowInPayslip] = useState(true);
  const [editPayslipSection, setEditPayslipSection] = useState<'earnings' | 'deductions' | 'tax_reference' | 'info'>('earnings');
  const [isEditSubmitting, setIsEditSubmitting] = useState(false);

  // Standard Column Edit Dialog State
  const [isStandardDialogOpen, setIsStandardDialogOpen] = useState(false);
  const [editingStandardCol, setEditingStandardCol] = useState<StandardColumnItem | null>(null);
  const [stdDisplayLabel, setStdDisplayLabel] = useState('');
  const [stdHeaderAliases, setStdHeaderAliases] = useState('');
  const [stdShowInPayslip, setStdShowInPayslip] = useState(true);
  const [stdPayslipSection, setStdPayslipSection] = useState<'earnings' | 'deductions' | 'tax_reference' | 'info'>('earnings');
  const [isStdSubmitting, setIsStdSubmitting] = useState(false);

  // Standard Columns Filter & Search State
  const [stdSearchQuery, setStdSearchQuery] = useState('');
  const [stdSectionFilter, setStdSectionFilter] = useState<string>('all');
  const [stdStatusFilter, setStdStatusFilter] = useState<'all' | 'customized' | 'default'>('all');

  // Dynamic Map of column_name -> active display_label (instantly reflects standard column edits)
  const columnLabelMap = useMemo(() => {
    const map: Record<string, string> = {};
    (standardColumns || []).forEach((sc) => {
      map[sc.column_name.toLowerCase()] = sc.display_label || sc.default_label;
    });
    (columns || []).forEach((cc) => {
      map[cc.column_name.toLowerCase()] = cc.display_label;
    });
    return map;
  }, [standardColumns, columns]);

  // Normalize position values so legacy values without prefix seamlessly match 'after:'
  const normalizePosValue = (pos: string | null | undefined): string => {
    if (!pos || pos === 'END') return 'END';
    if (pos === 'FIRST') return 'FIRST';
    if (pos.startsWith('after:') || pos.startsWith('before:')) return pos;
    return `after:${pos}`;
  };

  // Helper to format position nicely with colored badges and dynamic standard column labels
  const formatPosition = (pos: string | null | undefined) => {
    if (!pos || pos === 'END') {
      return <span className="text-gray-400 italic">End of Table</span>;
    }
    if (pos === 'FIRST') {
      return (
        <span className="inline-flex items-center gap-1 text-xs text-purple-700 bg-purple-50 dark:bg-purple-950/40 px-2 py-0.5 rounded border border-purple-200 dark:border-purple-800 font-medium">
          Beginning of Table
        </span>
      );
    }

    let isBefore = false;
    let colKey = pos;
    if (pos.startsWith('before:')) {
      isBefore = true;
      colKey = pos.substring(7);
    } else if (pos.startsWith('after:')) {
      isBefore = false;
      colKey = pos.substring(6);
    }

    const label = columnLabelMap[colKey.toLowerCase()] || colKey;
    const hasLabel = label !== colKey;

    return (
      <span className="inline-flex items-center gap-1.5 text-xs">
        <span
          className={`px-1.5 py-0.5 rounded font-bold uppercase text-[10px] tracking-wide ${
            isBefore
              ? 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 border border-amber-300'
              : 'bg-blue-100 text-blue-800 dark:bg-blue-950/60 dark:text-blue-300 border border-blue-300'
          }`}
        >
          {isBefore ? 'Before' : 'After'}
        </span>
        <span className="font-semibold text-gray-900 dark:text-gray-100">{label}</span>
        {hasLabel && (
          <code className="text-[11px] text-gray-500 font-mono">({colKey})</code>
        )}
      </span>
    );
  };

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

  // Custom Column Edit Handlers
  const handleOpenEdit = (col: PayrollColumnItem) => {
    setEditingCol(col);
    setEditDisplayLabel(col.display_label);
    setEditHeaderAliases(col.header_aliases || '');
    setEditPositionAfter(col.position_after || 'END');
    setEditShowInPayslip(col.show_in_payslip);
    setEditPayslipSection(col.payslip_section || 'earnings');
    setIsEditDialogOpen(true);
  };

  const handleEditSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!editingCol) return;

    setIsEditSubmitting(true);
    router.put(
      `/admin/payroll-columns/${editingCol.id}`,
      {
        display_label: editDisplayLabel,
        header_aliases: editHeaderAliases,
        position_after: editPositionAfter === 'END' ? null : editPositionAfter,
        show_in_payslip: editShowInPayslip,
        payslip_section: editPayslipSection,
      },
      {
        preserveScroll: true,
        onSuccess: () => {
          setIsEditDialogOpen(false);
          setEditingCol(null);
        },
        onFinish: () => {
          setIsEditSubmitting(false);
        },
      }
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

  // Standard Column Edit Handlers
  const handleOpenStandardEdit = (col: StandardColumnItem) => {
    if (col.column_name === 'staff_id') {
      alert('The primary employee identity column (staff_id) is permanently protected and cannot be modified.');
      return;
    }
    setEditingStandardCol(col);
    setStdDisplayLabel(col.display_label);
    setStdHeaderAliases(col.header_aliases || '');
    setStdShowInPayslip(col.show_in_payslip);
    setStdPayslipSection(col.payslip_section || 'earnings');
    setIsStandardDialogOpen(true);
  };

  const handleStandardEditSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!editingStandardCol) return;
    if (!stdDisplayLabel.trim()) return;

    setIsStdSubmitting(true);
    router.post(
      '/admin/payroll-columns/standard',
      {
        client_dbname: selectedClient,
        column_name: editingStandardCol.column_name,
        display_label: stdDisplayLabel.trim(),
        header_aliases: stdHeaderAliases.trim() || null,
        show_in_payslip: stdShowInPayslip,
        payslip_section: stdPayslipSection,
      },
      {
        preserveScroll: true,
        onSuccess: () => {
          setIsStandardDialogOpen(false);
          setEditingStandardCol(null);
        },
        onFinish: () => {
          setIsStdSubmitting(false);
        },
      }
    );
  };

  const handleResetStandard = (col: StandardColumnItem) => {
    if (
      confirm(
        `Reset '${col.display_label}' back to default label '${col.default_label}' and default aliases?`
      )
    ) {
      router.post(
        '/admin/payroll-columns/standard-reset',
        {
          client_dbname: selectedClient,
          column_name: col.column_name,
        },
        {
          preserveScroll: true,
          onSuccess: () => {
            if (editingStandardCol?.column_name === col.column_name) {
              setIsStandardDialogOpen(false);
              setEditingStandardCol(null);
            }
          },
        }
      );
    }
  };

  // Filtered standard columns
  const filteredStandardColumns = useMemo(() => {
    return standardColumns.filter((col) => {
      // Search text filter
      if (stdSearchQuery.trim()) {
        const query = stdSearchQuery.toLowerCase();
        const matchesName = col.column_name.toLowerCase().includes(query);
        const matchesLabel = col.display_label.toLowerCase().includes(query);
        const matchesDefault = col.default_label.toLowerCase().includes(query);
        const matchesAliases = (col.header_aliases || '').toLowerCase().includes(query);
        const matchesDefAliases = (col.default_aliases || '').toLowerCase().includes(query);
        if (!matchesName && !matchesLabel && !matchesDefault && !matchesAliases && !matchesDefAliases) {
          return false;
        }
      }

      // Section filter
      if (stdSectionFilter !== 'all' && col.payslip_section !== stdSectionFilter) {
        return false;
      }

      // Status filter
      if (stdStatusFilter === 'customized' && !col.has_override) {
        return false;
      }
      if (stdStatusFilter === 'default' && col.has_override) {
        return false;
      }

      return true;
    });
  }, [standardColumns, stdSearchQuery, stdSectionFilter, stdStatusFilter]);

  const currentClientObj = clients.find((c) => c.client_dbname === selectedClient);
  const targetClientObj = clients.find((c) => c.client_dbname === targetClient);
  const payslipCount = columns.filter((c) => c.show_in_payslip).length;
  const standardOverridesCount = standardColumns.filter((c) => c.has_override).length;

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
                Payroll Columns Configuration
              </h1>
            </div>
            <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">
              Manage custom payroll columns and configure display names & Excel header mappings for all database columns with zero database schema changes.
            </p>
          </div>

          <div className="flex flex-wrap items-center gap-3">
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

            {/* Add Custom Column Dialog Trigger */}
            <Dialog open={isDialogOpen} onOpenChange={setIsDialogOpen}>
              <DialogTrigger asChild>
                <Button className="bg-blue-600 hover:bg-blue-700 text-white shadow-sm flex items-center gap-2">
                  <Plus className="w-4 h-4" />
                  Add Custom Column
                </Button>
              </DialogTrigger>

              <DialogContent className="max-w-xl">
                <DialogHeader>
                  <DialogTitle className="flex items-center gap-2">
                    <Database className="w-5 h-5 text-blue-600" />
                    Add Custom Payroll Column
                  </DialogTitle>
                  <DialogDescription>
                    Configure a new custom column. It will be added to existing monthly tables and ensured on future uploads.
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
                      <Label htmlFor="positionAfter">Position in Table & Template</Label>
                      <Select value={normalizePosValue(positionAfter)} onValueChange={setPositionAfter}>
                        <SelectTrigger id="positionAfter">
                          <SelectValue placeholder="Select position (After / Before)" />
                        </SelectTrigger>
                        <SelectContent className="max-h-72">
                          <SelectItem value="END">At the end of table (Default)</SelectItem>
                          <SelectItem value="FIRST">At the beginning of table (First)</SelectItem>

                          <SelectGroup>
                            <SelectLabel className="text-[11px] font-bold uppercase tracking-wider text-blue-700 dark:text-blue-400 bg-blue-50 dark:bg-blue-950/50 px-2 py-1 mt-1 rounded">
                              Place AFTER (Right of Column)
                            </SelectLabel>
                            {dialogExistingCols.map((col) => {
                              const label = columnLabelMap[col.toLowerCase()] || col;
                              return (
                                <SelectItem key={`after:${col}`} value={`after:${col}`}>
                                  After: {label} {label !== col ? `(${col})` : ''}
                                </SelectItem>
                              );
                            })}
                          </SelectGroup>

                          <SelectGroup>
                            <SelectLabel className="text-[11px] font-bold uppercase tracking-wider text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/50 px-2 py-1 mt-1 rounded">
                              Place BEFORE (Left of Column)
                            </SelectLabel>
                            {dialogExistingCols.map((col) => {
                              const label = columnLabelMap[col.toLowerCase()] || col;
                              return (
                                <SelectItem key={`before:${col}`} value={`before:${col}`}>
                                  Before: {label} {label !== col ? `(${col})` : ''}
                                </SelectItem>
                              );
                            })}
                          </SelectGroup>
                        </SelectContent>
                      </Select>
                      <p className="text-xs text-muted-foreground">Choose whether this custom column sits before or after an existing standard column.</p>
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
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <Card className="cursor-pointer hover:border-blue-300 transition-colors" onClick={() => setActiveTab('custom')}>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">Custom Columns</CardTitle>
              <Database className="h-4 w-4 text-blue-600" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{columns.length}</div>
              <p className="text-xs text-muted-foreground mt-1">
                Custom additions for {selectedClient === 'all' ? 'All Clients' : (currentClientObj?.client_name || selectedClient)}
              </p>
            </CardContent>
          </Card>

          <Card className="cursor-pointer hover:border-indigo-300 transition-colors" onClick={() => setActiveTab('standard')}>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">Standard Base Columns</CardTitle>
              <Layers className="h-4 w-4 text-indigo-600" />
            </CardHeader>
            <CardContent>
              <div className="flex items-center gap-2">
                <span className="text-2xl font-bold">{standardColumns.length}</span>
                {standardOverridesCount > 0 && (
                  <Badge className="bg-indigo-100 text-indigo-800 dark:bg-indigo-950 dark:text-indigo-300 text-[11px]">
                    {standardOverridesCount} renamed
                  </Badge>
                )}
              </div>
              <p className="text-xs text-muted-foreground mt-1">
                {isTailored ? `Tailored for ${currentClientObj?.short_name || 'selected client'}` : 'Base system columns (All Clients)'}
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
                Custom items rendered on PDF payslips
              </p>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">Physical Table Columns</CardTitle>
              <ShieldCheck className="h-4 w-4 text-purple-600" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{existingColumns.length}</div>
              <p className="text-xs text-muted-foreground mt-1">
                Total schema columns on current monthly table
              </p>
            </CardContent>
          </Card>
        </div>

        {/* Tab Switcher */}
        <div className="flex items-center gap-2 border-b border-gray-200 dark:border-gray-800 pb-2">
          <button
            type="button"
            onClick={() => setActiveTab('custom')}
            className={`flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-semibold transition-all cursor-pointer ${
              activeTab === 'custom'
                ? 'bg-blue-600 text-white shadow-sm'
                : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100 dark:text-gray-400 dark:hover:text-gray-100 dark:hover:bg-gray-800'
            }`}
          >
            <Database className="w-4 h-4" />
            <span>Custom Payroll Columns</span>
            <Badge
              variant="outline"
              className={`ml-1 text-xs ${
                activeTab === 'custom' ? 'border-blue-400 bg-blue-700/50 text-white' : 'text-gray-600 dark:text-gray-400'
              }`}
            >
              {columns.length}
            </Badge>
          </button>

          <button
            type="button"
            onClick={() => setActiveTab('standard')}
            className={`flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-semibold transition-all cursor-pointer ${
              activeTab === 'standard'
                ? 'bg-blue-600 text-white shadow-sm'
                : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100 dark:text-gray-400 dark:hover:text-gray-100 dark:hover:bg-gray-800'
            }`}
          >
            <Layers className="w-4 h-4" />
            <span>Standard Base Columns</span>
            <Badge
              variant="outline"
              className={`ml-1 text-xs ${
                activeTab === 'standard' ? 'border-blue-400 bg-blue-700/50 text-white' : 'text-gray-600 dark:text-gray-400'
              }`}
            >
              {standardColumns.length}
            </Badge>
            {standardOverridesCount > 0 && (
              <span className="w-2 h-2 rounded-full bg-amber-400 shrink-0" title={`${standardOverridesCount} column(s) customized`} />
            )}
          </button>
        </div>

        {/* ================= TAB 1: CUSTOM COLUMNS ================= */}
        {activeTab === 'custom' && (
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
                <div className="overflow-x-auto">
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
                            <div className="font-medium">{col.display_label}</div>
                            {col.header_aliases && (
                              <div
                                className="text-[11px] text-muted-foreground mt-0.5 truncate max-w-xs"
                                title={`Recognized Excel Aliases: ${col.header_aliases}`}
                              >
                                <span className="text-blue-600 font-medium">Aliases:</span> {col.header_aliases}
                              </div>
                            )}
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
                          <TableCell className="text-sm">
                            {formatPosition(col.position_after)}
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
                            <div className="flex items-center justify-end gap-1">
                              <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => handleOpenEdit(col)}
                                className="text-blue-600 hover:text-blue-700 hover:bg-blue-50 dark:hover:bg-blue-950/40"
                                title="Edit display name & header aliases"
                              >
                                <Pencil className="w-4 h-4" />
                              </Button>
                              <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => handleDeleteColumn(col)}
                                className="text-red-600 hover:text-red-700 hover:bg-red-50 dark:hover:bg-red-950/40"
                                title="Remove from custom columns registry"
                              >
                                <Trash2 className="w-4 h-4" />
                              </Button>
                            </div>
                          </TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                </div>
              )}
            </CardContent>
          </Card>
        )}

        {/* ================= TAB 2: STANDARD BASE COLUMNS ================= */}
        {activeTab === 'standard' && (
          <div className="space-y-4">
            {/* Standard Columns Explanatory Callout */}
            <div className="p-4 bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-950/30 dark:to-indigo-950/30 border border-blue-200 dark:border-blue-900 rounded-lg flex items-start gap-3">
              <Sparkles className="w-5 h-5 text-blue-600 shrink-0 mt-0.5" />
              <div className="text-xs space-y-1 text-blue-900 dark:text-blue-200">
                <p className="font-semibold text-sm text-blue-950 dark:text-blue-100">
                  Universal Column Renaming & Excel Header Ingestion
                </p>
                <p>
                  You can customize the display name of <strong>any standard base column</strong> (such as Basic Salary, Overtime, SSNIT, PAYE, Petra, Net Pay).
                </p>
                <p>
                  • <strong>Zero Database Breakage:</strong> The physical database column name (<code className="font-mono font-semibold">column_name</code>) remains untouched. Existing monthly tables and queries continue to function seamlessly.
                </p>
                <p>
                  • <strong>Flexible Excel Uploads:</strong> When uploading Excel files, the system will recognize both the <em>new display name</em>, the <em>original default name</em>, and any <em>custom aliases</em> you configure.
                </p>
                <p>
                  • <strong>Protected ID:</strong> The primary employee identifier (<code className="font-mono font-semibold">staff_id</code>) is permanently locked to preserve data integrity.
                </p>
              </div>
            </div>

            {/* Filter & Search Bar */}
            <div className="flex flex-col sm:flex-row items-center gap-3 bg-white dark:bg-gray-800 p-3 rounded-lg border border-gray-200 dark:border-gray-700">
              <div className="relative flex-1 w-full">
                <Search className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
                <Input
                  value={stdSearchQuery}
                  onChange={(e) => setStdSearchQuery(e.target.value)}
                  placeholder="Search standard columns by name, label, or aliases (e.g. basic, ssnit, allowance, gross)..."
                  className="pl-9 bg-gray-50 dark:bg-gray-900"
                />
              </div>

              <div className="flex items-center gap-2 w-full sm:w-auto">
                <Select value={stdSectionFilter} onValueChange={setStdSectionFilter}>
                  <SelectTrigger className="w-[160px] bg-gray-50 dark:bg-gray-900 text-xs">
                    <SelectValue placeholder="Payslip Section" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="all">All Sections</SelectItem>
                    <SelectItem value="earnings">Earnings</SelectItem>
                    <SelectItem value="deductions">Deductions</SelectItem>
                    <SelectItem value="tax_reference">Tax Reference</SelectItem>
                    <SelectItem value="info">Info</SelectItem>
                  </SelectContent>
                </Select>

                <Select value={stdStatusFilter} onValueChange={(val: any) => setStdStatusFilter(val)}>
                  <SelectTrigger className="w-[150px] bg-gray-50 dark:bg-gray-900 text-xs">
                    <SelectValue placeholder="Status" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="all">All Columns</SelectItem>
                    <SelectItem value="customized">Customized Only</SelectItem>
                    <SelectItem value="default">Default Only</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>

            {/* Standard Columns Table */}
            <Card>
              <CardHeader className="pb-3">
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                  <div>
                    <CardTitle className="text-lg flex items-center gap-2">
                      Standard Base Columns Directory
                      {isTailored && (
                        <Badge className="bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 text-xs font-medium">
                          Tailored to {currentClientObj?.short_name || selectedClient}
                        </Badge>
                      )}
                    </CardTitle>
                    <CardDescription className="mt-1">
                      Showing <span className="font-semibold text-gray-900 dark:text-gray-100">{filteredStandardColumns.length}</span> of{' '}
                      <span className="font-semibold text-gray-900 dark:text-gray-100">{standardColumns.length}</span> standard base columns{' '}
                      {isTailored ? (
                        <>
                          tailored for <span className="font-semibold text-blue-600">{currentClientObj?.client_name || selectedClient}</span>
                        </>
                      ) : (
                        <>(All System Scope)</>
                      )}
                    </CardDescription>
                  </div>

                  {selectedClient !== 'all' && (
                    <div className="flex items-center gap-2">
                      {isTailored ? (
                        <Button
                          type="button"
                          variant="outline"
                          size="sm"
                          onClick={() => {
                            router.get(
                              '/admin/payroll-columns',
                              { client: selectedClient, show_all: 1 },
                              { preserveState: true, preserveScroll: true }
                            );
                          }}
                          className="text-xs text-gray-600 hover:text-gray-900"
                        >
                          Show All System Columns ({totalSystemColumns || 410})
                        </Button>
                      ) : (
                        <Button
                          type="button"
                          variant="outline"
                          size="sm"
                          onClick={() => {
                            router.get(
                              '/admin/payroll-columns',
                              { client: selectedClient },
                              { preserveState: true, preserveScroll: true }
                            );
                          }}
                          className="text-xs text-emerald-700 border-emerald-300 hover:bg-emerald-50 dark:hover:bg-emerald-950"
                        >
                          Filter to {currentClientObj?.short_name || 'Client'} Columns Only
                        </Button>
                      )}
                    </div>
                  )}
                </div>
              </CardHeader>

              <CardContent>
                <div className="overflow-x-auto">
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>Display Label (Active)</TableHead>
                        <TableHead>Database Column</TableHead>
                        <TableHead>Default Recognized Aliases</TableHead>
                        <TableHead>Payslip Section</TableHead>
                        <TableHead>Payslip Status</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead className="text-right">Actions</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {filteredStandardColumns.map((col) => {
                        const isStaffId = Boolean(col.locked) || ['staff_id', 'staffid', 'staffida', 'employ', 'id', 'employid'].includes(col.column_name.toLowerCase());

                        return (
                          <TableRow key={col.column_name} className={col.has_override ? 'bg-blue-50/30 dark:bg-blue-950/20' : ''}>
                            {/* Display Label */}
                            <TableCell className="font-medium text-gray-900 dark:text-gray-100">
                              <div className="flex items-center gap-2">
                                <span className="font-semibold">{col.display_label}</span>
                                {col.has_override && (
                                  <Badge className="bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300 text-[10px] py-0 px-1.5">
                                    Customized
                                  </Badge>
                                )}
                              </div>
                              {col.has_override && col.display_label !== col.default_label && (
                                <div className="text-[11px] text-gray-500 mt-0.5">
                                  Default: <span className="italic">{col.default_label}</span>
                                </div>
                              )}
                              {col.header_aliases && (
                                <div
                                  className="text-[11px] text-muted-foreground mt-0.5 truncate max-w-xs"
                                  title={`Custom Excel Aliases: ${col.header_aliases}`}
                                >
                                  <span className="text-emerald-600 font-medium">Custom Aliases:</span> {col.header_aliases}
                                </div>
                              )}
                            </TableCell>

                            {/* Column Name */}
                            <TableCell>
                              <div className="flex items-center gap-1.5">
                                <code className="text-xs bg-gray-100 dark:bg-gray-800 text-blue-600 dark:text-blue-400 px-2 py-1 rounded font-mono">
                                  {col.column_name}
                                </code>
                                <Lock className="w-3 h-3 text-gray-400 shrink-0" title="Physical column name locked in database" />
                              </div>
                            </TableCell>

                            {/* Recognized Default Aliases */}
                            <TableCell className="text-xs text-gray-500 max-w-xs truncate" title={col.default_aliases}>
                              {col.default_aliases || '-'}
                            </TableCell>

                            {/* Section */}
                            <TableCell>
                              <Badge
                                className={
                                  col.payslip_section === 'earnings'
                                    ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300'
                                    : col.payslip_section === 'deductions'
                                    ? 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300'
                                    : col.payslip_section === 'tax_reference'
                                    ? 'bg-purple-100 text-purple-800 dark:bg-purple-950 dark:text-purple-300'
                                    : 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300'
                                }
                              >
                                {col.payslip_section.toUpperCase()}
                              </Badge>
                            </TableCell>

                            {/* Payslip Visibility */}
                            <TableCell>
                              {col.show_in_payslip ? (
                                <span className="inline-flex items-center gap-1 text-xs text-emerald-600 font-medium">
                                  <Eye className="w-3.5 h-3.5" /> Visible
                                </span>
                              ) : (
                                <span className="inline-flex items-center gap-1 text-xs text-gray-400 font-medium">
                                  <EyeOff className="w-3.5 h-3.5" /> Hidden
                                </span>
                              )}
                            </TableCell>

                            {/* Status */}
                            <TableCell>
                              {col.has_override ? (
                                <Badge variant="outline" className="text-blue-600 border-blue-300 text-xs">
                                  Overridden
                                </Badge>
                              ) : (
                                <span className="text-xs text-gray-400">System Default</span>
                              )}
                            </TableCell>

                            {/* Actions */}
                            <TableCell className="text-right">
                              {isStaffId ? (
                                <div className="flex items-center justify-end gap-1 text-xs text-amber-700 dark:text-amber-400 font-medium">
                                  <ShieldCheck className="w-4 h-4 text-amber-600" />
                                  <span>Protected ID</span>
                                </div>
                              ) : (
                                <div className="flex items-center justify-end gap-1">
                                  <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => handleOpenStandardEdit(col)}
                                    className="text-blue-600 hover:text-blue-700 hover:bg-blue-50 dark:hover:bg-blue-950/40"
                                    title="Edit display name & Excel header mapping"
                                  >
                                    <Pencil className="w-4 h-4 mr-1" />
                                    <span>Edit</span>
                                  </Button>

                                  {col.has_override && (
                                    <Button
                                      variant="ghost"
                                      size="sm"
                                      onClick={() => handleResetStandard(col)}
                                      className="text-amber-600 hover:text-amber-700 hover:bg-amber-50 dark:hover:bg-amber-950/40"
                                      title="Reset this column to system defaults"
                                    >
                                      <RotateCcw className="w-3.5 h-3.5" />
                                    </Button>
                                  )}
                                </div>
                              )}
                            </TableCell>
                          </TableRow>
                        );
                      })}
                    </TableBody>
                  </Table>
                </div>
              </CardContent>
            </Card>
          </div>
        )}

        {/* Architecture & Workflow Guidance Card */}
        <Card className="bg-slate-50 dark:bg-slate-900/40 border-slate-200 dark:border-slate-800">
          <CardHeader>
            <CardTitle className="text-sm font-semibold flex items-center gap-2">
              <HelpCircle className="w-4 h-4 text-blue-500" />
              Dynamic Payroll Architecture across All 33 Clients & Global System
            </CardTitle>
          </CardHeader>
          <CardContent className="text-xs text-gray-600 dark:text-gray-400 space-y-2">
            <p>
              • <strong>Client-Specific or Global:</strong> Select a specific client (e.g. Shefer, PZ, Transsion BFA, Nyonkopa, Melcom, CIV, Togo, Benin, etc.) to configure columns exclusively for them, or choose <strong>★ All Clients (Global)</strong> to apply system-wide.
            </p>
            <p>
              • <strong>Safe Uploads with Changed Headers:</strong> When an Excel payroll file is uploaded, the header resolver automatically normalizes and matches column headers against active display labels, standard names, and any configured aliases.
            </p>
            <p>
              • <strong>Template Synchronization:</strong> Downloadable Excel payroll templates immediately reflect customized column headers in real time.
            </p>
            <p>
              • <strong>Payslip Automation:</strong> Standard and custom columns marked as "Show in Payslip" dynamically render in individual and bulk PDF payslips without requiring code changes.
            </p>
          </CardContent>
        </Card>

        {/* ================= EDIT CUSTOM COLUMN DIALOG ================= */}
        <Dialog open={isEditDialogOpen} onOpenChange={setIsEditDialogOpen}>
          <DialogContent className="max-w-xl">
            <DialogHeader>
              <DialogTitle className="flex items-center gap-2">
                <Pencil className="w-5 h-5 text-blue-600" />
                Edit Custom Payroll Column
              </DialogTitle>
              <DialogDescription>
                Update the display name or alternate header aliases. The underlying database column is safely locked to prevent data corruption.
              </DialogDescription>
            </DialogHeader>

            {editingCol && (
              <form onSubmit={handleEditSubmit} className="space-y-4 pt-2">
                {/* Locked DB Column Indicator */}
                <div className="p-3 bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800 rounded-lg flex items-start gap-2.5 text-xs text-amber-900 dark:text-amber-200">
                  <Lock className="w-4 h-4 text-amber-600 shrink-0 mt-0.5" />
                  <div>
                    <strong>Database Column Name: </strong>
                    <code className="font-mono bg-amber-100 dark:bg-amber-900/60 px-1.5 py-0.5 rounded text-amber-900 dark:text-amber-100 font-semibold">
                      {editingCol.column_name}
                    </code>
                    <span className="block mt-1 text-amber-700 dark:text-amber-300">
                      This physical MySQL column remains unchanged to protect historical payroll months and existing queries.
                    </span>
                  </div>
                </div>

                {/* Display Label */}
                <div className="space-y-1.5">
                  <Label htmlFor="editDisplayLabel">
                    Display Label <span className="text-red-500">*</span>
                  </Label>
                  <Input
                    id="editDisplayLabel"
                    value={editDisplayLabel}
                    onChange={(e) => setEditDisplayLabel(e.target.value)}
                    placeholder="e.g. Total Cash Emolument"
                    required
                    autoFocus
                  />
                  <p className="text-xs text-muted-foreground">
                    The updated name that will appear on downloaded Excel templates, payslips, and preview tables.
                  </p>
                </div>

                {/* Alternate Header Aliases */}
                <div className="space-y-1.5">
                  <Label htmlFor="editHeaderAliases" className="flex items-center gap-1.5 font-medium">
                    <ShieldCheck className="w-4 h-4 text-emerald-600" />
                    Excel Header Aliases (Secure Upload)
                  </Label>
                  <Input
                    id="editHeaderAliases"
                    value={editHeaderAliases}
                    onChange={(e) => setEditHeaderAliases(e.target.value)}
                    placeholder="e.g. Total Cash Emoluments, Cash Emoluments, Total Cash"
                  />
                  <p className="text-xs text-muted-foreground">
                    Comma-separated list of recognized header names in Excel files. When users upload files using any of these old or new header names, they map securely to the database column without affecting old formats.
                  </p>
                </div>

                {/* Position After & Payslip Section */}
                <div className="grid grid-cols-2 gap-4">
                  <div className="space-y-1.5">
                    <Label htmlFor="editPositionAfter">Template Physical Position</Label>
                    <Select value={normalizePosValue(editPositionAfter)} onValueChange={setEditPositionAfter}>
                      <SelectTrigger id="editPositionAfter">
                        <SelectValue placeholder="Select position (After / Before)" />
                      </SelectTrigger>
                      <SelectContent className="max-h-72">
                        <SelectItem value="END">At the end of table (Default)</SelectItem>
                        <SelectItem value="FIRST">At the beginning of table (First)</SelectItem>

                        <SelectGroup>
                          <SelectLabel className="text-[11px] font-bold uppercase tracking-wider text-blue-700 dark:text-blue-400 bg-blue-50 dark:bg-blue-950/50 px-2 py-1 mt-1 rounded">
                            Place AFTER (Right of Column)
                          </SelectLabel>
                          {dialogExistingCols.map((col) => {
                            const label = columnLabelMap[col.toLowerCase()] || col;
                            return (
                              <SelectItem key={`after:${col}`} value={`after:${col}`}>
                                After: {label} {label !== col ? `(${col})` : ''}
                              </SelectItem>
                            );
                          })}
                        </SelectGroup>

                        <SelectGroup>
                          <SelectLabel className="text-[11px] font-bold uppercase tracking-wider text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/50 px-2 py-1 mt-1 rounded">
                            Place BEFORE (Left of Column)
                          </SelectLabel>
                          {dialogExistingCols.map((col) => {
                            const label = columnLabelMap[col.toLowerCase()] || col;
                            return (
                              <SelectItem key={`before:${col}`} value={`before:${col}`}>
                                Before: {label} {label !== col ? `(${col})` : ''}
                              </SelectItem>
                            );
                          })}
                        </SelectGroup>
                      </SelectContent>
                    </Select>
                  </div>

                  <div className="space-y-1.5">
                    <Label htmlFor="editPayslipSection">Payslip Section</Label>
                    <Select
                      value={editPayslipSection}
                      onValueChange={(val: any) => setEditPayslipSection(val)}
                    >
                      <SelectTrigger id="editPayslipSection">
                        <SelectValue placeholder="Select section" />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="earnings">Earnings & Allowances (Credits)</SelectItem>
                        <SelectItem value="deductions">Deductions (Debits)</SelectItem>
                        <SelectItem value="tax_reference">Tax Reference / Memo (e.g. Assessable Income)</SelectItem>
                        <SelectItem value="info">General Information / Meta</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                </div>

                {/* Show in Payslip Toggle */}
                <div className="p-3 rounded-lg bg-gray-50 dark:bg-gray-800/60 border border-gray-200 dark:border-gray-700 flex items-center justify-between">
                  <div>
                    <Label htmlFor="editShowInPayslip" className="text-sm font-semibold cursor-pointer">
                      Show in Employee Payslips?
                    </Label>
                    <p className="text-xs text-muted-foreground">
                      Renders on employee PDF payslips under the selected section.
                    </p>
                  </div>
                  <Switch
                    id="editShowInPayslip"
                    checked={editShowInPayslip}
                    onCheckedChange={setEditShowInPayslip}
                  />
                </div>

                <DialogFooter className="pt-2">
                  <Button
                    type="button"
                    variant="outline"
                    onClick={() => setIsEditDialogOpen(false)}
                    disabled={isEditSubmitting}
                  >
                    Cancel
                  </Button>
                  <Button
                    type="submit"
                    className="bg-blue-600 hover:bg-blue-700 text-white"
                    disabled={isEditSubmitting || !editDisplayLabel.trim()}
                  >
                    {isEditSubmitting ? 'Saving...' : 'Save & Update Column'}
                  </Button>
                </DialogFooter>
              </form>
            )}
          </DialogContent>
        </Dialog>

        {/* ================= EDIT STANDARD BASE COLUMN DIALOG ================= */}
        <Dialog open={isStandardDialogOpen} onOpenChange={setIsStandardDialogOpen}>
          <DialogContent className="max-w-xl">
            <DialogHeader>
              <DialogTitle className="flex items-center gap-2">
                <SlidersHorizontal className="w-5 h-5 text-indigo-600" />
                Customize Standard Column Display & Header Mapping
              </DialogTitle>
              <DialogDescription>
                Customize how this base column appears on Excel templates, payslips, and preview tables. The physical database column remains completely untouched.
              </DialogDescription>
            </DialogHeader>

            {editingStandardCol && (
              <form onSubmit={handleStandardEditSubmit} className="space-y-4 pt-2">
                {/* Locked DB Column & Canonical Notice */}
                <div className="p-3 bg-indigo-50 dark:bg-indigo-950/40 border border-indigo-200 dark:border-indigo-800 rounded-lg space-y-1.5 text-xs text-indigo-950 dark:text-indigo-200">
                  <div className="flex items-center gap-2">
                    <Lock className="w-4 h-4 text-indigo-600 shrink-0" />
                    <span>
                      Database Column Name: <code className="font-mono font-semibold bg-indigo-100 dark:bg-indigo-900/60 px-1.5 py-0.5 rounded">{editingStandardCol.column_name}</code>
                    </span>
                  </div>
                  <div className="text-[11px] text-indigo-700 dark:text-indigo-300">
                    System Default Label: <strong>{editingStandardCol.default_label}</strong>
                  </div>
                </div>

                {/* Display Label */}
                <div className="space-y-1.5">
                  <Label htmlFor="stdDisplayLabel">
                    Display Label <span className="text-red-500">*</span>
                  </Label>
                  <Input
                    id="stdDisplayLabel"
                    value={stdDisplayLabel}
                    onChange={(e) => setStdDisplayLabel(e.target.value)}
                    placeholder={editingStandardCol.default_label}
                    required
                    autoFocus
                  />
                  <p className="text-xs text-muted-foreground">
                    The name that will show on downloadable Excel templates and employee payslips.
                  </p>
                </div>

                {/* Custom Aliases */}
                <div className="space-y-1.5">
                  <Label htmlFor="stdHeaderAliases" className="flex items-center gap-1.5 font-medium">
                    <ShieldCheck className="w-4 h-4 text-emerald-600" />
                    Additional Excel Header Aliases
                  </Label>
                  <Input
                    id="stdHeaderAliases"
                    value={stdHeaderAliases}
                    onChange={(e) => setStdHeaderAliases(e.target.value)}
                    placeholder="e.g. Lunch Allowance, Canteen, Staff Food"
                  />
                  <p className="text-xs text-muted-foreground">
                    Comma-separated alternate header names from your uploaded files. All of these headers (plus the original default header and the new display label) will be accepted securely.
                  </p>
                </div>

                {/* Built-in Recognized Aliases Notice */}
                <div className="p-2.5 rounded bg-gray-50 dark:bg-gray-800 text-[11px] text-gray-500 dark:text-gray-400">
                  <span className="font-semibold text-gray-700 dark:text-gray-300">Built-in default aliases: </span>
                  {editingStandardCol.default_aliases || 'None'}
                </div>

                {/* Payslip Section & Visibility */}
                <div className="grid grid-cols-2 gap-4">
                  <div className="space-y-1.5">
                    <Label htmlFor="stdPayslipSection">Payslip Section</Label>
                    <Select
                      value={stdPayslipSection}
                      onValueChange={(val: any) => setStdPayslipSection(val)}
                    >
                      <SelectTrigger id="stdPayslipSection">
                        <SelectValue placeholder="Select section" />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="earnings">Earnings & Allowances (Credits)</SelectItem>
                        <SelectItem value="deductions">Deductions (Debits)</SelectItem>
                        <SelectItem value="tax_reference">Tax Reference / Memo (e.g. Assessable Income)</SelectItem>
                        <SelectItem value="info">General Information / Meta</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>

                  <div className="space-y-1.5">
                    <Label htmlFor="stdShowInPayslip">Payslip Visibility</Label>
                    <div className="h-10 flex items-center justify-between px-3 border border-gray-200 dark:border-gray-700 rounded-md bg-white dark:bg-gray-800">
                      <span className="text-xs text-gray-600 dark:text-gray-300">Show on PDF</span>
                      <Switch
                        id="stdShowInPayslip"
                        checked={stdShowInPayslip}
                        onCheckedChange={setStdShowInPayslip}
                      />
                    </div>
                  </div>
                </div>

                <DialogFooter className="pt-2 flex items-center justify-between">
                  {editingStandardCol.has_override ? (
                    <Button
                      type="button"
                      variant="ghost"
                      onClick={() => handleResetStandard(editingStandardCol)}
                      className="text-amber-600 hover:text-amber-700 hover:bg-amber-50 dark:hover:bg-amber-950/40 text-xs flex items-center gap-1.5"
                    >
                      <RotateCcw className="w-3.5 h-3.5" />
                      <span>Reset to Default</span>
                    </Button>
                  ) : (
                    <div />
                  )}

                  <div className="flex items-center gap-2">
                    <Button
                      type="button"
                      variant="outline"
                      onClick={() => setIsStandardDialogOpen(false)}
                      disabled={isStdSubmitting}
                    >
                      Cancel
                    </Button>
                    <Button
                      type="submit"
                      className="bg-blue-600 hover:bg-blue-700 text-white"
                      disabled={isStdSubmitting || !stdDisplayLabel.trim()}
                    >
                      {isStdSubmitting ? 'Saving...' : 'Save & Apply'}
                    </Button>
                  </div>
                </DialogFooter>
              </form>
            )}
          </DialogContent>
        </Dialog>
      </div>
    </AppLayout>
  );
}
