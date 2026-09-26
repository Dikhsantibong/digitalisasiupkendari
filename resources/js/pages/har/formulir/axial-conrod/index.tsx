import { Head, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Check,
    Disc,
    Download,
    Eye,
    FileCode2,
    FileSpreadsheet,
    History,
    Info,
    Minus,
    Plus,
    Printer,
    RotateCcw,
    Save,
    SlidersHorizontal,
    Sparkles,
    Trash2,
    Wrench,
    X,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { MobileRowEditor } from '@/components/mobile/row-editor';
import { PageHeader } from '@/components/page-header';
import { PdfPreviewFrame } from '@/components/pdf-preview-frame';
import { RichTextEditor } from '@/components/rich-text-editor';
import { StatusBadge } from '@/components/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useCompactLayout } from '@/hooks/use-mobile-module';
import { usePermissions } from '@/hooks/use-permissions';
import harFormulir from '@/routes/har/formulir';
import axialConrodRoutes from '@/routes/har/formulir/axial-conrod';
import type { IdName } from '@/types';

type AxialConrodMeasurement = {
    cylinder: number;
    axial_check: 'baik' | 'tidak_baik';
    bolt_tightening: 'baik' | 'tidak_baik';
    notes: string;
};

type EmployeeOption = {
    id: number;
    name: string;
    nip: string | null;
    position: string | null;
    has_signature: boolean;
};

type MachineOption = {
    id: number;
    name: string;
    type: string | null;
    serial_number: string | null;
    capacity_kw: string | null;
};

type HistoryItem = {
    id: number;
    machine_id: number;
    test_date: string;
    document_number: string;
    revision: string;
    format: string;
    updated_at: string;
};

type Props = {
    unit: {
        id: number;
        name: string;
        service_unit_id: number | null;
        service_unit_name: string | null;
    };
    units: IdName[];
    machines: MachineOption[];
    selected_machine_id: number | null;
    selected_test_date: string;
    record: any | null;
    form_data: {
        document_number: string;
        revision: string;
        effective_date: string;
        test_date_raw: string;
        brand: string;
        model_type: string;
        serial_number: string;
        machine_number: string;
        installed_power: string;
        capable_power: string;
        rpm: string;
        cylinders_count: number;
        torque_standard: string;
        standard_allowed: string;
        notes: string;
        measurements: AxialConrodMeasurement[];
        manager_ul_id: number | null;
        manager_ul_name: string;
        manager_ul_title: string;
        tl_har_id: number | null;
        tl_har_name: string;
        tl_har_title: string;
        staff_har_id: number | null;
        staff_har_name: string;
        staff_har_title: string;
        page_margin_top: number;
        page_margin_bottom: number;
        page_margin_left: number;
        page_margin_right: number;
        line_spacing: string;
    };
    rendered_html: string;
    manager_options: EmployeeOption[];
    tl_options: EmployeeOption[];
    staff_options: EmployeeOption[];
    history: HistoryItem[];
    pdf_url: string;
    can_write: boolean;
};

export default function HarAxialConrodIndex({
    unit,
    units,
    machines,
    selected_machine_id,
    selected_test_date,
    record,
    form_data,
    rendered_html,
    manager_options,
    tl_options,
    staff_options,
    history,
    pdf_url,
    can_write,
}: Props) {
    const compact = useCompactLayout();
    const { can } = usePermissions();
    // Mode tab: form | html | pdf
    const [viewTab, setViewTab] = useState<'form' | 'html' | 'pdf'>(
        record?.format === 'html' ? 'html' : 'form'
    );
    const [isSaving, setIsSaving] = useState(false);
    const [historyOpen, setHistoryOpen] = useState(false);
    const [previewKey, setPreviewKey] = useState(Date.now());

    // Form states
    const [testDate, setTestDate] = useState(selected_test_date);
    const [docNumber, setDocNumber] = useState(form_data.document_number);
    const [revision, setRevision] = useState(form_data.revision);
    const [effectiveDate, setEffectiveDate] = useState(form_data.effective_date);

    // Technical specs
    const [brand, setBrand] = useState(form_data.brand || 'MAK');
    const [modelType, setModelType] = useState(form_data.model_type || '8M 453 AK');
    const [serialNumber, setSerialNumber] = useState(form_data.serial_number || '');
    const [machineNumber, setMachineNumber] = useState(form_data.machine_number || '3');
    const [installedPower, setInstalledPower] = useState(form_data.installed_power || '2544');
    const [capablePower, setCapablePower] = useState(form_data.capable_power || '');
    const [rpm, setRpm] = useState(form_data.rpm || '600');
    const [torqueStandard, setTorqueStandard] = useState(form_data.torque_standard || '750 NM');

    // Inspection data
    const [cylindersCount, setCylindersCount] = useState<number>(form_data.cylinders_count || 8);
    const [measurements, setMeasurements] = useState<AxialConrodMeasurement[]>(() => {
        if (form_data.measurements && form_data.measurements.length > 0) {
            return form_data.measurements;
        }

        const initial: AxialConrodMeasurement[] = [];

        for (let i = 1; i <= (form_data.cylinders_count || 8); i++) {
            initial.push({
                cylinder: i,
                axial_check: 'baik',
                bolt_tightening: 'baik',
                notes: '',
            });
        }

        return initial;
    });

    const [standardAllowed, setStandardAllowed] = useState(form_data.standard_allowed || '');
    const [notes, setNotes] = useState(form_data.notes || '');

    // Signatories
    const [managerUlId, setManagerUlId] = useState<string>(
        form_data.manager_ul_id ? String(form_data.manager_ul_id) : ''
    );
    const [managerUlName, setManagerUlName] = useState(form_data.manager_ul_name || '');
    const [managerUlTitle, setManagerUlTitle] = useState(form_data.manager_ul_title || '');

    const [tlHarId, setTlHarId] = useState<string>(
        form_data.tl_har_id ? String(form_data.tl_har_id) : ''
    );
    const [tlHarName, setTlHarName] = useState(form_data.tl_har_name || '');
    const [tlHarTitle, setTlHarTitle] = useState(form_data.tl_har_title || '');

    const [staffHarId, setStaffHarId] = useState<string>(
        form_data.staff_har_id ? String(form_data.staff_har_id) : ''
    );
    const [staffHarName, setStaffHarName] = useState(form_data.staff_har_name || '');
    const [staffHarTitle, setStaffHarTitle] = useState(form_data.staff_har_title || '');

    // Page settings
    const [marginTop, setMarginTop] = useState(form_data.page_margin_top ?? 10);
    const [marginBottom, setMarginBottom] = useState(form_data.page_margin_bottom ?? 10);
    const [marginLeft, setMarginLeft] = useState(form_data.page_margin_left ?? 12);
    const [marginRight, setMarginRight] = useState(form_data.page_margin_right ?? 12);
    const [lineSpacing, setLineSpacing] = useState(form_data.line_spacing ?? '1.15');

    // Rich text editor content
    const [htmlContent, setHtmlContent] = useState(rendered_html);

    // Synchronize cylinder rows when count changes
    const handleCylindersCountChange = (count: number) => {
        const nextCount = Math.max(1, Math.min(32, count));
        setCylindersCount(nextCount);
        setMeasurements((prev) => {
            const next = [...prev];

            if (nextCount > prev.length) {
                for (let i = prev.length + 1; i <= nextCount; i++) {
                    next.push({
                        cylinder: i,
                        axial_check: 'baik',
                        bolt_tightening: 'baik',
                        notes: '',
                    });
                }
            } else {
                return next.slice(0, nextCount);
            }

            return next;
        });
    };

    const handleMeasurementChange = (
        index: number,
        field: keyof AxialConrodMeasurement,
        value: any
    ) => {
        setMeasurements((prev) => {
            const next = [...prev];
            next[index] = { ...next[index], [field]: value };

            return next;
        });
    };

    const handleSetAllBaik = () => {
        setMeasurements((prev) =>
            prev.map((row) => ({
                ...row,
                axial_check: 'baik',
                bolt_tightening: 'baik',
            }))
        );
    };

    const handleFillScanValues = () => {
        setBrand('MAK');
        setModelType('8M 453 AK');
        setSerialNumber('');
        setMachineNumber('3');
        setInstalledPower('2544');
        setCapablePower('');
        setRpm('600');
        setTestDate('2026-08-20');
        setDocNumber('FMKD-314-10.3.3.a-B7');
        setRevision('03');
        setEffectiveDate('31 Juli 2024');
        setTorqueStandard('750 NM');
        setStandardAllowed('');
        setNotes('');

        setCylindersCount(8);
        const scanRows: AxialConrodMeasurement[] = [];

        for (let i = 1; i <= 8; i++) {
            scanRows.push({
                cylinder: i,
                axial_check: 'baik',
                bolt_tightening: 'baik',
                notes: '',
            });
        }

        setMeasurements(scanRows);

        setManagerUlName('SURYADI PRATAMA');
        setManagerUlTitle('PH. Manager Unit PLTD Wua-wua');
        setTlHarName('SURYADI PRATAMA');
        setTlHarTitle('Team Leader Pemeliharaan');
        setStaffHarName('RAHMAT RAHIM FAISAL');
        setStaffHarTitle('Staf Pemeliharaan');
    };

    // Auto-update signatory names on select
    useEffect(() => {
        if (managerUlId) {
            const emp = manager_options.find((e) => String(e.id) === managerUlId);

            if (emp) {
                setManagerUlName(emp.name);

                if (emp.position) {
setManagerUlTitle(emp.position);
}
            }
        }
    }, [managerUlId, manager_options]);

    useEffect(() => {
        if (tlHarId) {
            const emp = tl_options.find((e) => String(e.id) === tlHarId);

            if (emp) {
                setTlHarName(emp.name);

                if (emp.position) {
setTlHarTitle(emp.position);
}
            }
        }
    }, [tlHarId, tl_options]);

    useEffect(() => {
        if (staffHarId) {
            const emp = staff_options.find((e) => String(e.id) === staffHarId);

            if (emp) {
                setStaffHarName(emp.name);

                if (emp.position) {
setStaffHarTitle(emp.position);
}
            }
        }
    }, [staffHarId, staff_options]);

    // Live URL for PDF Preview & Download
    const previewPdfUrl = useMemo(() => {
        const url = new URL(pdf_url, window.location.origin);
        url.searchParams.set('document_number', docNumber);
        url.searchParams.set('revision', revision);
        url.searchParams.set('effective_date', effectiveDate);
        url.searchParams.set('brand', brand);
        url.searchParams.set('model_type', modelType);
        url.searchParams.set('serial_number', serialNumber);
        url.searchParams.set('machine_number', machineNumber);
        url.searchParams.set('installed_power', installedPower);
        url.searchParams.set('capable_power', capablePower);
        url.searchParams.set('rpm', rpm);
        url.searchParams.set('cylinders_count', String(cylindersCount));
        url.searchParams.set('torque_standard', torqueStandard);
        url.searchParams.set('standard_allowed', standardAllowed);
        url.searchParams.set('notes', notes);
        url.searchParams.set('measurements', JSON.stringify(measurements));

        if (managerUlId) {
url.searchParams.set('manager_ul_id', managerUlId);
}

        url.searchParams.set('manager_ul_name', managerUlName);
        url.searchParams.set('manager_ul_title', managerUlTitle);

        if (tlHarId) {
url.searchParams.set('tl_har_id', tlHarId);
}

        url.searchParams.set('tl_har_name', tlHarName);
        url.searchParams.set('tl_har_title', tlHarTitle);

        if (staffHarId) {
url.searchParams.set('staff_har_id', staffHarId);
}

        url.searchParams.set('staff_har_name', staffHarName);
        url.searchParams.set('staff_har_title', staffHarTitle);

        url.searchParams.set('page_margin_top', String(marginTop));
        url.searchParams.set('page_margin_bottom', String(marginBottom));
        url.searchParams.set('page_margin_left', String(marginLeft));
        url.searchParams.set('page_margin_right', String(marginRight));
        url.searchParams.set('line_spacing', lineSpacing);
        url.searchParams.set('ts', String(previewKey));

        return url.toString();
    }, [
        pdf_url,
        docNumber,
        revision,
        effectiveDate,
        brand,
        modelType,
        serialNumber,
        machineNumber,
        installedPower,
        capablePower,
        rpm,
        cylindersCount,
        torqueStandard,
        standardAllowed,
        notes,
        measurements,
        managerUlId,
        managerUlName,
        managerUlTitle,
        tlHarId,
        tlHarName,
        tlHarTitle,
        staffHarId,
        staffHarName,
        staffHarTitle,
        marginTop,
        marginBottom,
        marginLeft,
        marginRight,
        lineSpacing,
        previewKey,
    ]);

    const handleSave = () => {
        if (!selected_machine_id) {
            alert('Silakan pilih mesin terlebih dahulu.');

            return;
        }

        setIsSaving(true);
        router.post(
            axialConrodRoutes.store.url(),
            {
                unit_id: unit.id,
                machine_id: selected_machine_id,
                test_date: testDate,
                document_number: docNumber,
                revision: revision,
                effective_date: effectiveDate,
                brand: brand,
                model_type: modelType,
                serial_number: serialNumber,
                machine_number: machineNumber,
                installed_power: installedPower,
                capable_power: capablePower,
                rpm: rpm,
                cylinders_count: cylindersCount,
                torque_standard: torqueStandard,
                standard_allowed: standardAllowed,
                notes: notes,
                measurements: measurements,
                manager_ul_id: managerUlId ? Number(managerUlId) : null,
                manager_ul_name: managerUlName,
                manager_ul_title: managerUlTitle,
                tl_har_id: tlHarId ? Number(tlHarId) : null,
                tl_har_name: tlHarName,
                tl_har_title: tlHarTitle,
                staff_har_id: staffHarId ? Number(staffHarId) : null,
                staff_har_name: staffHarName,
                staff_har_title: staffHarTitle,
                page_margin_top: marginTop,
                page_margin_bottom: marginBottom,
                page_margin_left: marginLeft,
                page_margin_right: marginRight,
                line_spacing: lineSpacing,
                format: viewTab === 'html' ? 'html' : 'form',
                content_html: viewTab === 'html' ? htmlContent : null,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setPreviewKey(Date.now());
                },
                onFinish: () => setIsSaving(false),
            }
        );
    };

    const handleUnitChange = (newUnitId: string) => {
        router.get(
            axialConrodRoutes.index.url(),
            { unit_id: newUnitId },
            { preserveState: false }
        );
    };

    const handleMachineChange = (newMachineId: string) => {
        router.get(
            axialConrodRoutes.index.url(),
            {
                unit_id: unit.id,
                machine_id: newMachineId,
                test_date: testDate,
            },
            { preserveState: false }
        );
    };

    const handleDateChange = (newDate: string) => {
        setTestDate(newDate);
        router.get(
            axialConrodRoutes.index.url(),
            {
                unit_id: unit.id,
                machine_id: selected_machine_id,
                test_date: newDate,
            },
            { preserveState: false }
        );
    };

    const handleDownloadPdf = () => {
        const dlUrl = new URL(previewPdfUrl);
        dlUrl.searchParams.set('download', '1');
        window.open(dlUrl.toString(), '_blank');
    };

    const handlePrintPdf = () => {
        window.open(previewPdfUrl, '_blank');
    };

    return (
        <>
            <Head title={`Formulir Pemeriksaan Axial Conrod & Baut Conrod - ${unit.name}`} />

            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                {/* Header Navigation */}
                <PageHeader
                    title="Formulir Pemeriksaan Axial Conrod & Baut Conrod"
                    description="Pemeriksaan clearance axial connecting rod dan torsi pengencangan baut conrod pada tiap silinder mesin."
                    actions={
                        <div className="flex flex-wrap items-center gap-2">
                            {can('har.input.view') && (
                                <Button
                                    variant="outline"
                                    onClick={() => router.get(harFormulir.index().url, { unit_id: unit.id })}
                                    className="gap-2"
                                >
                                    <ArrowLeft className="size-4" />
                                    Kembali
                                </Button>
                            )}
                            <Button
                                variant="outline"
                                onClick={() => setHistoryOpen(true)}
                                className="gap-2"
                            >
                                <History className="size-4" />
                                Riwayat ({history.length})
                            </Button>
                            <Button
                                variant="outline"
                                onClick={handleDownloadPdf}
                                className="gap-2"
                            >
                                <Download className="size-4" />
                                Unduh PDF
                            </Button>
                            {can_write && (
                                <Button
                                    onClick={handleSave}
                                    disabled={isSaving}
                                    className="gap-2"
                                >
                                    <Save className="size-4" />
                                    {isSaving ? 'Menyimpan…' : 'Simpan Formulir'}
                                </Button>
                            )}
                        </div>
                    }
                />

                {/* Status Bar */}
                <div className="flex flex-wrap items-center justify-between gap-3 rounded-md border border-border bg-card p-3 text-[13px]">
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="text-muted-foreground">Unit:</span>
                        <strong className="text-foreground">{unit.name}</strong>
                        <span className="text-muted-foreground mx-1">•</span>
                        <span className="text-muted-foreground">Service Unit (UL):</span>
                        <span className="font-medium text-foreground">
                            {unit.service_unit_name || '—'}
                        </span>
                        <span className="text-muted-foreground mx-1">•</span>
                        <span className="text-muted-foreground">Status Dokumen:</span>
                        {record ? (
                            <StatusBadge tone="success">
                                Tersimpan ({record.format === 'html' ? 'Teks HTML' : 'Form'})
                            </StatusBadge>
                        ) : (
                            <StatusBadge tone="neutral">Belum Disimpan</StatusBadge>
                        )}
                    </div>
                    <div className="text-[12px] text-muted-foreground">
                        No. Dokumen: <span className="font-mono font-medium">{docNumber}</span> (Rev. {revision || '03'})
                    </div>
                </div>

                {/* 2-Column Layout */}
                <div className="flex flex-col lg:flex-row items-start gap-6 w-full">
                    {/* LEFT COLUMN: Metadata, Signatories, Page Settings */}
                    <div className="w-full lg:w-[360px] xl:w-[380px] shrink-0 space-y-5">
                        {/* Card 1: Metadata Formulir & Mesin */}
                        <div className="rounded-lg border border-border bg-card p-4 space-y-4 shadow-sm">
                            <div className="border-b border-border pb-2">
                                <h3 className="text-sm font-semibold text-foreground flex items-center gap-1.5">
                                    <Disc className="size-4 text-primary" />
                                    Metadata Formulir &amp; Mesin
                                </h3>
                                <p className="text-[11px] text-muted-foreground">
                                    Informasi dasar unit, mesin, nomor dokumen, dan spesifikasi teknis.
                                </p>
                            </div>

                            <div className="space-y-3 text-xs">
                                {/* Unit Selector */}
                                <div className="space-y-1">
                                    <Label className="text-xs">Unit Pembangkit</Label>
                                    {units.length > 1 ? (
                                        <Select
                                            value={String(unit.id)}
                                            onValueChange={handleUnitChange}
                                        >
                                            <SelectTrigger className="h-8 text-xs">
                                                <SelectValue placeholder="Pilih unit" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {units.map((u) => (
                                                    <SelectItem key={u.id} value={String(u.id)} className="text-xs">
                                                        {u.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    ) : (
                                        <Input value={unit.name} disabled className="h-8 text-xs" />
                                    )}
                                </div>

                                {/* Machine Selector */}
                                <div className="space-y-1">
                                    <Label className="text-xs">Mesin Pembangkit</Label>
                                    {machines.length > 0 ? (
                                        <Select
                                            value={selected_machine_id ? String(selected_machine_id) : ''}
                                            onValueChange={handleMachineChange}
                                        >
                                            <SelectTrigger className="h-8 text-xs">
                                                <SelectValue placeholder="Pilih mesin" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {machines.map((m) => (
                                                    <SelectItem key={m.id} value={String(m.id)} className="text-xs">
                                                        {m.name} {m.type ? `(${m.type})` : ''}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    ) : (
                                        <div className="text-xs text-muted-foreground">Tidak ada mesin aktif.</div>
                                    )}
                                </div>

                                {/* Tanggal Uji */}
                                <div className="space-y-1">
                                    <Label className="text-xs">Tanggal Pemeriksaan</Label>
                                    <Input
                                        type="date"
                                        value={testDate}
                                        onChange={(e) => handleDateChange(e.target.value)}
                                        className="h-8 text-xs"
                                    />
                                </div>

                                {/* Header Document Info */}
                                <div className="grid grid-cols-2 gap-2 pt-2 border-t border-border">
                                    <div className="space-y-1">
                                        <Label className="text-xs">No. Dokumen</Label>
                                        <Input
                                            value={docNumber}
                                            onChange={(e) => setDocNumber(e.target.value)}
                                            className="h-8 text-xs font-mono"
                                        />
                                    </div>
                                    <div className="space-y-1">
                                        <Label className="text-xs">Revisi</Label>
                                        <Input
                                            value={revision}
                                            onChange={(e) => setRevision(e.target.value)}
                                            className="h-8 text-xs"
                                        />
                                    </div>
                                </div>

                                <div className="space-y-1">
                                    <Label className="text-xs">Tanggal Efektif Dokumen</Label>
                                    <Input
                                        value={effectiveDate}
                                        onChange={(e) => setEffectiveDate(e.target.value)}
                                        className="h-8 text-xs"
                                    />
                                </div>

                                {/* Standar Torsi Pengencangan Baut */}
                                <div className="space-y-1 pt-2 border-t border-border">
                                    <Label className="text-xs font-semibold text-primary">
                                        Standar Torsi Baut Conrod
                                    </Label>
                                    <Input
                                        value={torqueStandard}
                                        onChange={(e) => setTorqueStandard(e.target.value)}
                                        placeholder="750 NM"
                                        className="h-8 text-xs font-semibold"
                                    />
                                    <p className="text-[10px] text-muted-foreground">
                                        Tampil pada judul kolom tabel cetak: &ldquo;PENGENCANGAN BAUT CON-ROD {torqueStandard}&rdquo;.
                                    </p>
                                </div>

                                {/* Technical Specs Machine */}
                                <div className="border-t border-border pt-3 space-y-2">
                                    <span className="text-[11px] font-semibold text-muted-foreground uppercase tracking-wider">
                                        Spesifikasi Mesin
                                    </span>
                                    <div className="grid grid-cols-2 gap-2">
                                        <div className="space-y-1">
                                            <Label className="text-[11px]">Merek</Label>
                                            <Input
                                                value={brand}
                                                onChange={(e) => setBrand(e.target.value)}
                                                placeholder="MAK"
                                                className="h-7 text-xs"
                                            />
                                        </div>
                                        <div className="space-y-1">
                                            <Label className="text-[11px]">Type Mesin</Label>
                                            <Input
                                                value={modelType}
                                                onChange={(e) => setModelType(e.target.value)}
                                                placeholder="8M 453 AK"
                                                className="h-7 text-xs"
                                            />
                                        </div>
                                    </div>

                                    <div className="grid grid-cols-2 gap-2">
                                        <div className="space-y-1">
                                            <Label className="text-[11px]">No. Seri</Label>
                                            <Input
                                                value={serialNumber}
                                                onChange={(e) => setSerialNumber(e.target.value)}
                                                placeholder="—"
                                                className="h-7 text-xs"
                                            />
                                        </div>
                                        <div className="space-y-1">
                                            <Label className="text-[11px]">Mesin No</Label>
                                            <Input
                                                value={machineNumber}
                                                onChange={(e) => setMachineNumber(e.target.value)}
                                                placeholder="3"
                                                className="h-7 text-xs"
                                            />
                                        </div>
                                    </div>

                                    <div className="grid grid-cols-3 gap-2">
                                        <div className="space-y-1">
                                            <Label className="text-[11px]">Daya Pasang</Label>
                                            <Input
                                                value={installedPower}
                                                onChange={(e) => setInstalledPower(e.target.value)}
                                                placeholder="2544"
                                                className="h-7 text-xs"
                                            />
                                        </div>
                                        <div className="space-y-1">
                                            <Label className="text-[11px]">Daya Mampu</Label>
                                            <Input
                                                value={capablePower}
                                                onChange={(e) => setCapablePower(e.target.value)}
                                                placeholder="—"
                                                className="h-7 text-xs"
                                            />
                                        </div>
                                        <div className="space-y-1">
                                            <Label className="text-[11px]">RPM</Label>
                                            <Input
                                                value={rpm}
                                                onChange={(e) => setRpm(e.target.value)}
                                                placeholder="600"
                                                className="h-7 text-xs"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Card 2: Penandatangan Dokumen */}
                        <div className="rounded-lg border border-border bg-card p-4 space-y-4 shadow-sm">
                            <div className="border-b border-border pb-2">
                                <h3 className="text-sm font-semibold text-foreground">
                                    Penandatangan Dokumen
                                </h3>
                                <p className="text-[11px] text-muted-foreground">
                                    3 posisi resmi (Mengetahui, Diperiksa, Dibuat).
                                </p>
                            </div>

                            <div className="space-y-3 text-xs">
                                {/* 1. Manager UL */}
                                <div className="space-y-1.5 rounded-md border border-border/70 p-2.5 bg-muted/20">
                                    <div className="flex items-center justify-between">
                                        <span className="font-semibold text-foreground">1. Mengetahui</span>
                                        <span className="text-[10px] text-muted-foreground">Manager Unit Layanan</span>
                                    </div>
                                    <Select
                                        value={managerUlId}
                                        onValueChange={(val) => setManagerUlId(val)}
                                    >
                                        <SelectTrigger className="h-7 text-xs">
                                            <SelectValue placeholder="Pilih Manager" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {manager_options.map((e) => (
                                                <SelectItem key={e.id} value={String(e.id)} className="text-xs">
                                                    {e.name} {e.position ? `— ${e.position}` : ''}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <div className="grid grid-cols-2 gap-1.5 pt-1">
                                        <Input
                                            value={managerUlName}
                                            onChange={(e) => setManagerUlName(e.target.value)}
                                            placeholder="Nama Manager"
                                            className="h-7 text-xs"
                                        />
                                        <Input
                                            value={managerUlTitle}
                                            onChange={(e) => setManagerUlTitle(e.target.value)}
                                            placeholder="Jabatan"
                                            className="h-7 text-xs"
                                        />
                                    </div>
                                </div>

                                {/* 2. Team Leader HAR */}
                                <div className="space-y-1.5 rounded-md border border-border/70 p-2.5 bg-muted/20">
                                    <div className="flex items-center justify-between">
                                        <span className="font-semibold text-foreground">2. Diperiksa</span>
                                        <span className="text-[10px] text-muted-foreground">TL Pemeliharaan</span>
                                    </div>
                                    <Select
                                        value={tlHarId}
                                        onValueChange={(val) => setTlHarId(val)}
                                    >
                                        <SelectTrigger className="h-7 text-xs">
                                            <SelectValue placeholder="Pilih TL Pemeliharaan" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {tl_options.map((e) => (
                                                <SelectItem key={e.id} value={String(e.id)} className="text-xs">
                                                    {e.name} {e.position ? `— ${e.position}` : ''}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <div className="grid grid-cols-2 gap-1.5 pt-1">
                                        <Input
                                            value={tlHarName}
                                            onChange={(e) => setTlHarName(e.target.value)}
                                            placeholder="Nama TL HAR"
                                            className="h-7 text-xs"
                                        />
                                        <Input
                                            value={tlHarTitle}
                                            onChange={(e) => setTlHarTitle(e.target.value)}
                                            placeholder="Jabatan"
                                            className="h-7 text-xs"
                                        />
                                    </div>
                                </div>

                                {/* 3. Staff HAR */}
                                <div className="space-y-1.5 rounded-md border border-border/70 p-2.5 bg-muted/20">
                                    <div className="flex items-center justify-between">
                                        <span className="font-semibold text-foreground">3. Dibuat</span>
                                        <span className="text-[10px] text-muted-foreground">Staf Pemeliharaan</span>
                                    </div>
                                    <Select
                                        value={staffHarId}
                                        onValueChange={(val) => setStaffHarId(val)}
                                    >
                                        <SelectTrigger className="h-7 text-xs">
                                            <SelectValue placeholder="Pilih Staf Pemeliharaan" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {staff_options.map((e) => (
                                                <SelectItem key={e.id} value={String(e.id)} className="text-xs">
                                                    {e.name} {e.position ? `— ${e.position}` : ''}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <div className="grid grid-cols-2 gap-1.5 pt-1">
                                        <Input
                                            value={staffHarName}
                                            onChange={(e) => setStaffHarName(e.target.value)}
                                            placeholder="Nama Staf HAR"
                                            className="h-7 text-xs"
                                        />
                                        <Input
                                            value={staffHarTitle}
                                            onChange={(e) => setStaffHarTitle(e.target.value)}
                                            placeholder="Jabatan"
                                            className="h-7 text-xs"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Card 3: Pengaturan Cetak PDF */}
                        <div className="rounded-lg border border-border bg-card p-4 space-y-4 shadow-sm">
                            <div className="border-b border-border pb-2">
                                <h3 className="text-sm font-semibold text-foreground flex items-center gap-1.5">
                                    <SlidersHorizontal className="size-4 text-primary" />
                                    Tata Letak Halaman PDF
                                </h3>
                                <p className="text-[11px] text-muted-foreground">
                                    Presisi margin dan spasi baris dokumen resmi A4.
                                </p>
                            </div>

                            <div className="space-y-3 text-xs">
                                <div className="grid grid-cols-2 gap-2">
                                    <div className="space-y-1">
                                        <Label className="text-[11px]">Margin Atas (mm)</Label>
                                        <Input
                                            type="number"
                                            value={marginTop}
                                            onChange={(e) => setMarginTop(Number(e.target.value))}
                                            className="h-7 text-xs"
                                        />
                                    </div>
                                    <div className="space-y-1">
                                        <Label className="text-[11px]">Margin Bawah (mm)</Label>
                                        <Input
                                            type="number"
                                            value={marginBottom}
                                            onChange={(e) => setMarginBottom(Number(e.target.value))}
                                            className="h-7 text-xs"
                                        />
                                    </div>
                                </div>
                                <div className="grid grid-cols-2 gap-2">
                                    <div className="space-y-1">
                                        <Label className="text-[11px]">Margin Kiri (mm)</Label>
                                        <Input
                                            type="number"
                                            value={marginLeft}
                                            onChange={(e) => setMarginLeft(Number(e.target.value))}
                                            className="h-7 text-xs"
                                        />
                                    </div>
                                    <div className="space-y-1">
                                        <Label className="text-[11px]">Margin Kanan (mm)</Label>
                                        <Input
                                            type="number"
                                            value={marginRight}
                                            onChange={(e) => setMarginRight(Number(e.target.value))}
                                            className="h-7 text-xs"
                                        />
                                    </div>
                                </div>
                                <div className="space-y-1">
                                    <Label className="text-[11px]">Line Spacing</Label>
                                    <Select value={lineSpacing} onValueChange={setLineSpacing}>
                                        <SelectTrigger className="h-7 text-xs">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="1.0" className="text-xs">1.0 (Ketat)</SelectItem>
                                            <SelectItem value="1.1" className="text-xs">1.1 (Sedang)</SelectItem>
                                            <SelectItem value="1.15" className="text-xs">1.15 (Standar Asli)</SelectItem>
                                            <SelectItem value="1.2" className="text-xs">1.2 (Longgar)</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* RIGHT COLUMN: Action Tabs & Form Inputs */}
                    <div className="flex-1 min-w-0 w-full space-y-4">
                        <div className="rounded-lg border border-border bg-card shadow-sm">
                            {/* Card Header with View Mode Tabs & Action Buttons */}
                            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-border p-4 bg-muted/10">
                                <div className="flex max-w-full flex-wrap items-center gap-2">
                                    <div className="flex max-w-full flex-wrap items-center rounded-lg border border-border bg-muted/40 p-1">
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant={viewTab === 'form' ? 'default' : 'ghost'}
                                            onClick={() => setViewTab('form')}
                                            className="h-7 text-xs gap-1.5"
                                        >
                                            <FileSpreadsheet className="size-3.5" />
                                            Form Pengukuran
                                        </Button>
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant={viewTab === 'html' ? 'default' : 'ghost'}
                                            onClick={() => setViewTab('html')}
                                            className="h-7 text-xs gap-1.5"
                                        >
                                            <FileCode2 className="size-3.5" />
                                            Teks / Template HTML
                                        </Button>
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant={viewTab === 'pdf' ? 'default' : 'ghost'}
                                            onClick={() => {
                                                setViewTab('pdf');
                                                setPreviewKey(Date.now());
                                            }}
                                            className="h-7 text-xs gap-1.5"
                                        >
                                            <Eye className="size-3.5" />
                                            Pratinjau Dokumen
                                        </Button>
                                    </div>
                                </div>

                                <div className="flex flex-wrap items-center gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={handleDownloadPdf}
                                        className="h-8 text-xs gap-1.5"
                                    >
                                        <Download className="size-3.5" />
                                        Unduh PDF
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={handlePrintPdf}
                                        className="h-8 text-xs gap-1.5"
                                    >
                                        <Printer className="size-3.5" />
                                        Cetak PDF
                                    </Button>
                                    {can_write && (
                                        <Button
                                            type="button"
                                            size="sm"
                                            onClick={handleSave}
                                            disabled={isSaving}
                                            className="h-8 text-xs gap-1.5 bg-primary text-primary-foreground shadow"
                                        >
                                            <Save className="size-3.5" />
                                            {isSaving ? 'Menyimpan...' : 'Simpan Formulir'}
                                        </Button>
                                    )}
                                </div>
                            </div>

                            {/* TAB 1: FORM PENGUKURAN */}
                            {viewTab === 'form' && (
                                <div className="p-4 space-y-6">
                                    {/* Info Banner */}
                                    <div className="flex items-start gap-2.5 rounded-lg border border-blue-200 bg-blue-50/50 p-3 text-xs text-blue-900 dark:border-blue-900/50 dark:bg-blue-950/20 dark:text-blue-200">
                                        <Info className="size-4 text-blue-600 shrink-0 mt-0.5" />
                                        <div>
                                            <span className="font-semibold">Format Standar Pemeriksaan Axial Conrod &amp; Baut Conrod:</span>{' '}
                                            Pemeriksaan clearance axial connecting rod dan verifikasi torsi pengencangan baut conrod pada tiap silinder mesin pembangkit. Klik tombol status untuk mengubah status antara <span className="font-bold text-emerald-600 dark:text-emerald-400">√ Baik</span> dan <span className="font-bold text-rose-600 dark:text-rose-400">✕ Tidak Baik</span>.
                                        </div>
                                    </div>

                                    {/* Table Section */}
                                    <div className="rounded-lg border border-border p-3.5 space-y-3 bg-muted/5">
                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                            <div>
                                                <h4 className="text-xs font-bold uppercase tracking-wider text-foreground flex items-center gap-1.5">
                                                    <Wrench className="size-3.5 text-primary" />
                                                    Tabel Pemeriksaan Tiap Silinder
                                                </h4>
                                                <p className="text-[11px] text-muted-foreground">
                                                    Pengecekan axial con-rod, pengencangan baut con-rod ({torqueStandard}), dan catatan teknis per silinder.
                                                </p>
                                            </div>

                                            <div className="flex flex-wrap items-center gap-2">
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={handleFillScanValues}
                                                    className="h-7 text-xs gap-1 text-primary border-primary/40 hover:bg-primary/5"
                                                >
                                                    <Sparkles className="size-3.5" />
                                                    Isi Data Scan
                                                </Button>
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={handleSetAllBaik}
                                                    className="h-7 text-xs gap-1 text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-950/30"
                                                >
                                                    <Check className="size-3.5" />
                                                    Set Semua √ (Baik)
                                                </Button>
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() => handleCylindersCountChange(cylindersCount - 1)}
                                                    disabled={cylindersCount <= 1}
                                                    className="h-7 text-xs gap-1 px-2"
                                                >
                                                    <Minus className="size-3" />
                                                    Kurang Silinder
                                                </Button>
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() => handleCylindersCountChange(cylindersCount + 1)}
                                                    disabled={cylindersCount >= 32}
                                                    className="h-7 text-xs gap-1 px-2"
                                                >
                                                    <Plus className="size-3" />
                                                    Tambah Silinder
                                                </Button>
                                            </div>
                                        </div>

                                        {compact ? (
<MobileRowEditor
    rows={measurements}
    canWrite
    rowKey={(row) => row.cylinder}
    title={(row) => `Silinder ${row.cylinder}`}
    subtitle={(row) => `Axial ${row.axial_check === 'baik' ? 'baik' : 'tidak baik'} · Baut ${row.bolt_tightening === 'baik' ? 'baik' : 'tidak baik'}`}
    onChange={(index, key, value) => handleMeasurementChange(index, key, value)}
    fields={[
        { key: 'axial_check', label: 'Pemeriksaan axial conrod', type: 'select', options: ['baik', 'tidak_baik'], optionLabels: { baik: '✓ Baik', tidak_baik: '✕ Tidak baik' }, optionTone: (option) => (option === 'tidak_baik' ? 'border-rose-600 bg-rose-600 text-white' : 'border-emerald-600 bg-emerald-600 text-white'), parse: (value) => (value === '' ? 'baik' : value) },
        { key: 'bolt_tightening', label: 'Kekencangan baut conrod', type: 'select', options: ['baik', 'tidak_baik'], optionLabels: { baik: '✓ Baik', tidak_baik: '✕ Tidak baik' }, optionTone: (option) => (option === 'tidak_baik' ? 'border-rose-600 bg-rose-600 text-white' : 'border-emerald-600 bg-emerald-600 text-white'), parse: (value) => (value === '' ? 'baik' : value) },
        { key: 'notes', label: 'Keterangan', placeholder: 'Catatan silinder (opsional)' },
    ]}
/>
                                        ) : (
                                        <div className="overflow-x-auto border border-border rounded-lg bg-background">
                                            <table className="w-full text-xs border-collapse">
                                                <thead>
                                                    <tr className="bg-muted/60 border-b border-border text-foreground font-semibold">
                                                        <th className="p-2 text-center border-r border-border w-16">
                                                            CYL NO.
                                                        </th>
                                                        <th className="p-2 text-center border-r border-border min-w-[200px]">
                                                            PENGECEKAN AXIAL CON-ROD
                                                        </th>
                                                        <th className="p-2 text-center border-r border-border min-w-[220px]">
                                                            PENGENCANGAN BAUT CON-ROD {torqueStandard}
                                                        </th>
                                                        <th className="p-2 text-left border-r border-border min-w-[200px]">
                                                            KETERANGAN
                                                        </th>
                                                        <th className="p-2 text-center w-10">
                                                            #
                                                        </th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {measurements.map((row, idx) => {
                                                        const isAxialBaik = row.axial_check === 'baik';
                                                        const isBoltBaik = row.bolt_tightening === 'baik';

                                                        return (
                                                            <tr key={row.cylinder} className="border-b border-border hover:bg-muted/20">
                                                                <td className="p-2 text-center font-bold border-r border-border bg-muted/10">
                                                                    {row.cylinder}
                                                                </td>
                                                                <td className="p-1.5 border-r border-border text-center">
                                                                    <div className="inline-flex items-center rounded-md border border-border bg-muted/40 p-0.5">
                                                                        <button
                                                                            type="button"
                                                                            onClick={() => handleMeasurementChange(idx, 'axial_check', 'baik')}
                                                                            className={`px-3 py-1 rounded text-xs font-semibold transition-colors ${
                                                                                isAxialBaik
                                                                                    ? 'bg-emerald-600 text-white shadow-sm'
                                                                                    : 'text-muted-foreground hover:text-foreground'
                                                                            }`}
                                                                        >
                                                                            ✓ Baik
                                                                        </button>
                                                                        <button
                                                                            type="button"
                                                                            onClick={() => handleMeasurementChange(idx, 'axial_check', 'tidak_baik')}
                                                                            className={`px-3 py-1 rounded text-xs font-semibold transition-colors ${
                                                                                !isAxialBaik
                                                                                    ? 'bg-rose-600 text-white shadow-sm'
                                                                                    : 'text-muted-foreground hover:text-foreground'
                                                                            }`}
                                                                        >
                                                                            ✕ Tidak Baik
                                                                        </button>
                                                                    </div>
                                                                </td>
                                                                <td className="p-1.5 border-r border-border text-center">
                                                                    <div className="inline-flex items-center rounded-md border border-border bg-muted/40 p-0.5">
                                                                        <button
                                                                            type="button"
                                                                            onClick={() => handleMeasurementChange(idx, 'bolt_tightening', 'baik')}
                                                                            className={`px-3 py-1 rounded text-xs font-semibold transition-colors ${
                                                                                isBoltBaik
                                                                                    ? 'bg-emerald-600 text-white shadow-sm'
                                                                                    : 'text-muted-foreground hover:text-foreground'
                                                                            }`}
                                                                        >
                                                                            ✓ Baik
                                                                        </button>
                                                                        <button
                                                                            type="button"
                                                                            onClick={() => handleMeasurementChange(idx, 'bolt_tightening', 'tidak_baik')}
                                                                            className={`px-3 py-1 rounded text-xs font-semibold transition-colors ${
                                                                                !isBoltBaik
                                                                                    ? 'bg-rose-600 text-white shadow-sm'
                                                                                    : 'text-muted-foreground hover:text-foreground'
                                                                            }`}
                                                                        >
                                                                            ✕ Tidak Baik
                                                                        </button>
                                                                    </div>
                                                                </td>
                                                                <td className="p-1.5 border-r border-border">
                                                                    <Input
                                                                        value={row.notes}
                                                                        onChange={(e) => handleMeasurementChange(idx, 'notes', e.target.value)}
                                                                        placeholder="Catatan / keterangan silinder (opsional)"
                                                                        className="h-7 text-xs"
                                                                    />
                                                                </td>
                                                                <td className="p-1.5 text-center">
                                                                    <Button
                                                                        type="button"
                                                                        variant="ghost"
                                                                        size="icon"
                                                                        onClick={() => {
                                                                            if (measurements.length > 1) {
                                                                                handleCylindersCountChange(cylindersCount - 1);
                                                                            }
                                                                        }}
                                                                        disabled={measurements.length <= 1}
                                                                        className="size-6 text-muted-foreground hover:text-destructive"
                                                                    >
                                                                        <Trash2 className="size-3" />
                                                                    </Button>
                                                                </td>
                                                            </tr>
                                                        );
                                                    })}
                                                </tbody>
                                            </table>
                                        </div>
                                        )}
                                    </div>

                                    {/* SECTION 2: STANDAR YANG DIIZINKAN & CATATAN */}
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        {/* Standar yang diizinkan */}
                                        <div className="rounded-lg border border-border p-3.5 space-y-2 bg-muted/5">
                                            <div className="flex items-center justify-between">
                                                <Label className="text-xs font-bold uppercase tracking-wider text-primary">
                                                    Standar Yang di Izinkan
                                                </Label>
                                                <span className="text-[10px] text-muted-foreground">Referensi / Batas Nilai</span>
                                            </div>
                                            <textarea
                                                value={standardAllowed}
                                                onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => setStandardAllowed(e.target.value)}
                                                placeholder="Contoh: Sesuai petunjuk manual book pabrik / clearance standar..."
                                                rows={3}
                                                className="w-full rounded-md border border-input bg-background p-2.5 text-xs focus:outline-none focus:ring-1 focus:ring-ring resize-none"
                                            />
                                        </div>

                                        {/* Catatan Umum */}
                                        <div className="rounded-lg border border-border p-3.5 space-y-2 bg-muted/5">
                                            <div className="flex items-center justify-between">
                                                <Label className="text-xs font-bold uppercase tracking-wider text-primary">
                                                    Catatan Umum Pemeriksaan
                                                </Label>
                                                <span className="text-[10px] text-muted-foreground">Tampil di bagian bawah dokumen</span>
                                            </div>
                                            <textarea
                                                value={notes}
                                                onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => setNotes(e.target.value)}
                                                placeholder="Catatan tambahan kondisi baut, bearing, atau conrod..."
                                                rows={3}
                                                className="w-full rounded-md border border-input bg-background p-2.5 text-xs focus:outline-none focus:ring-1 focus:ring-ring resize-none"
                                            />
                                        </div>
                                    </div>

                                    {/* Action Footer */}
                                    <div className="flex flex-wrap items-center justify-between gap-3 border-t border-border pt-4">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={() => {
                                                if (confirm('Reset formulir ke pengaturan awal?')) {
                                                    router.post(axialConrodRoutes.reset.url(), {
                                                        record_id: record?.id,
                                                        unit_id: unit.id,
                                                        machine_id: selected_machine_id,
                                                        test_date: testDate,
                                                    });
                                                }
                                            }}
                                            className="h-8 text-xs gap-1.5 text-muted-foreground hover:text-destructive"
                                        >
                                            <RotateCcw className="size-3.5" />
                                            Reset Formulir
                                        </Button>

                                        <div className="flex items-center gap-2">
                                            {can_write && (
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    onClick={handleSave}
                                                    disabled={isSaving}
                                                    className="h-8 text-xs gap-1.5 bg-primary text-primary-foreground shadow"
                                                >
                                                    <Check className="size-3.5" />
                                                    {isSaving ? 'Menyimpan...' : 'Simpan Perubahan'}
                                                </Button>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            )}

                            {/* TAB 2: TEKS / TEMPLATE HTML */}
                            {viewTab === 'html' && (
                                <div className="p-4 space-y-4">
                                    <div className="flex items-start gap-2.5 rounded-lg border border-amber-200 bg-amber-50/50 p-3 text-xs text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/20 dark:text-amber-200">
                                        <Info className="size-4 text-amber-600 shrink-0 mt-0.5" />
                                        <div>
                                            <span className="font-semibold">Mode Kustom HTML:</span> Anda dapat mengedit teks dan struktur dokumen secara langsung. Mode ini menimpa tampilan bawaan saat dicetak.
                                        </div>
                                    </div>

                                    <RichTextEditor
                                        value={htmlContent}
                                        onChange={setHtmlContent}
                                    />

                                    <div className="flex justify-end gap-2 border-t border-border pt-3">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={() => setHtmlContent(rendered_html)}
                                            className="h-8 text-xs gap-1.5"
                                        >
                                            <RotateCcw className="size-3.5" />
                                            Kembalikan Template Asli
                                        </Button>
                                        {can_write && (
                                            <Button
                                                type="button"
                                                size="sm"
                                                onClick={handleSave}
                                                disabled={isSaving}
                                                className="h-8 text-xs gap-1.5 bg-primary text-primary-foreground shadow"
                                            >
                                                <Save className="size-3.5" />
                                                {isSaving ? 'Menyimpan...' : 'Simpan Perubahan HTML'}
                                            </Button>
                                        )}
                                    </div>
                                </div>
                            )}

                            {/* TAB 3: PRATINJAU DOKUMEN PDF */}
                            {viewTab === 'pdf' && (
                                <div className="p-4 space-y-3">
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <Badge variant="outline" className="text-xs">
                                                A4 Portrait • Standar UPDK Kendari
                                            </Badge>
                                            <span className="text-[11px] text-muted-foreground font-mono">
                                                FMKD-314-10.3.3.a-B7 (Rev. {revision || '03'})
                                            </span>
                                        </div>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={() => setPreviewKey(Date.now())}
                                            className="h-7 text-xs gap-1"
                                        >
                                            <RotateCcw className="size-3" />
                                            Segarkan Pratinjau
                                        </Button>
                                    </div>

                                    <div className="w-full rounded-lg border border-border overflow-hidden bg-muted/20 shadow-inner">
                                        <PdfPreviewFrame
                                            key={previewKey}
                                            src={previewPdfUrl}
                                            className="w-full h-[850px] border-0"
                                            title="Pratinjau PDF Formulir Pemeriksaan Axial Conrod"
                                        />
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>

            {/* History Modal Dialog */}
            <Dialog open={historyOpen} onOpenChange={setHistoryOpen}>
                <DialogContent className="max-w-2xl">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2 text-base">
                            <History className="size-4 text-primary" />
                            Riwayat Pemeriksaan Axial Conrod &amp; Baut Conrod
                        </DialogTitle>
                        <DialogDescription className="text-xs">
                            Daftar catatan formulir yang tersimpan pada {unit.name}.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="max-h-96 overflow-y-auto divide-y divide-border border rounded-md">
                        {history && history.length > 0 ? (
                            history.map((item) => (
                                <div
                                    key={item.id}
                                    className="p-3 flex items-center justify-between hover:bg-muted/40 transition-colors text-xs"
                                >
                                    <div>
                                        <div className="font-semibold text-foreground">
                                            Tanggal: {item.test_date}
                                        </div>
                                        <div className="text-[11px] text-muted-foreground font-mono">
                                            No. Dok: {item.document_number} (Rev: {item.revision || '03'}) • Mode: {item.format}
                                        </div>
                                    </div>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        onClick={() => {
                                            setHistoryOpen(false);
                                            router.get(
                                                axialConrodRoutes.index.url(),
                                                {
                                                    unit_id: unit.id,
                                                    machine_id: item.machine_id,
                                                    test_date: item.test_date,
                                                    record_id: item.id,
                                                },
                                                { preserveState: false }
                                            );
                                        }}
                                        className="h-7 text-xs"
                                    >
                                        Buka Formulir
                                    </Button>
                                </div>
                            ))
                        ) : (
                            <div className="p-4 text-center text-xs text-muted-foreground">
                                Belum ada riwayat formulir tersimpan.
                            </div>
                        )}
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}
