import { Head, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Download,
    Eye,
    FileCode2,
    FileSpreadsheet,
    History,
    Info,
    Printer,
    RotateCcw,
    Save,
    SlidersHorizontal,
    Sparkles,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
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
import { dashboard } from '@/routes';
import harFormulir from '@/routes/har/formulir';
import clearanceValveRoutes from '@/routes/har/formulir/clearance-valve';
import type { IdName } from '@/types';

type ClearanceValveMeasurement = {
    cylinder: number;
    ex_before_r: string;
    ex_before_l: string;
    ex_after_r: string;
    ex_after_l: string;
    in_before_r: string;
    in_before_l: string;
    in_after_r: string;
    in_after_l: string;
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
        standard_ex: string;
        standard_in: string;
        standard_allowed: string | null;
        visual_inspection: string;
        cylinder_notes: string;
        measurements: ClearanceValveMeasurement[];
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

export default function ClearanceValveIndex({
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
    const [viewTab, setViewTab] = useState<'form' | 'html' | 'pdf'>(
        record?.format === 'html' ? 'html' : 'form'
    );
    const [previewKey, setPreviewKey] = useState(0);
    const [isSaving, setIsSaving] = useState(false);
    const [showHistory, setShowHistory] = useState(false);

    // Form state
    const [machineId, setMachineId] = useState<number>(
        selected_machine_id || machines[0]?.id || 0
    );
    const [testDate, setTestDate] = useState<string>(
        form_data.test_date_raw || selected_test_date
    );
    const [docNumber, setDocNumber] = useState<string>(
        form_data.document_number || 'FMKD-314-10.3.3.a-B3'
    );
    const [revision, setRevision] = useState<string>(form_data.revision || '02');
    const [effectiveDate, setEffectiveDate] = useState<string>(
        form_data.effective_date || '31 Juli 2024'
    );

    // Technical Specs
    const [brand, setBrand] = useState<string>(form_data.brand || 'MAK');
    const [modelType, setModelType] = useState<string>(
        form_data.model_type || '8M 453 C'
    );
    const [serialNumber, setSerialNumber] = useState<string>(
        form_data.serial_number || ''
    );
    const [machineNumber, setMachineNumber] = useState<string>(
        form_data.machine_number || '4'
    );
    const [installedPower, setInstalledPower] = useState<string>(
        form_data.installed_power || '2800'
    );
    const [capablePower, setCapablePower] = useState<string>(
        form_data.capable_power || ''
    );
    const [rpm, setRpm] = useState<string>(form_data.rpm || '600');

    // Standards
    const [standardEx, setStandardEx] = useState<string>(
        form_data.standard_ex || '0.60 mm'
    );
    const [standardIn, setStandardIn] = useState<string>(
        form_data.standard_in || '0.30 mm'
    );
    const [standardAllowed, setStandardAllowed] = useState<string>(
        form_data.standard_allowed || '± 0.05 mm'
    );

    // Cylinder measurements
    const [cylindersCount, setCylindersCount] = useState<number>(
        form_data.cylinders_count || 8
    );
    const [measurements, setMeasurements] = useState<ClearanceValveMeasurement[]>(() => {
        const initial = form_data.measurements || [];
        if (initial.length === 0) {
            return Array.from({ length: 8 }, (_, i) => ({
                cylinder: i + 1,
                ex_before_r: '',
                ex_before_l: '',
                ex_after_r: '',
                ex_after_l: '',
                in_before_r: '',
                in_before_l: '',
                in_after_r: '',
                in_after_l: '',
            }));
        }
        return initial;
    });

    const [cylinderNotes, setCylinderNotes] = useState<string>(
        form_data.cylinder_notes || ''
    );
    const [visualInspection, setVisualInspection] = useState<string>(
        form_data.visual_inspection || ''
    );

    // Signatories
    const [managerUlId, setManagerUlId] = useState<string>(
        form_data.manager_ul_id ? String(form_data.manager_ul_id) : ''
    );
    const [managerUlName, setManagerUlName] = useState<string>(
        form_data.manager_ul_name || ''
    );
    const [managerUlTitle, setManagerUlTitle] = useState<string>(
        form_data.manager_ul_title || 'Manager UL'
    );

    const [tlHarId, setTlHarId] = useState<string>(
        form_data.tl_har_id ? String(form_data.tl_har_id) : ''
    );
    const [tlHarName, setTlHarName] = useState<string>(
        form_data.tl_har_name || ''
    );
    const [tlHarTitle, setTlHarTitle] = useState<string>(
        form_data.tl_har_title || 'Team Leader Pemeliharaan'
    );

    const [staffHarId, setStaffHarId] = useState<string>(
        form_data.staff_har_id ? String(form_data.staff_har_id) : ''
    );
    const [staffHarName, setStaffHarName] = useState<string>(
        form_data.staff_har_name || ''
    );
    const [staffHarTitle, setStaffHarTitle] = useState<string>(
        form_data.staff_har_title || 'Staff Pemeliharaan'
    );

    // Page layout settings
    const [marginTop, setMarginTop] = useState<number>(
        form_data.page_margin_top ?? 10
    );
    const [marginBottom, setMarginBottom] = useState<number>(
        form_data.page_margin_bottom ?? 10
    );
    const [marginLeft, setMarginLeft] = useState<number>(
        form_data.page_margin_left ?? 12
    );
    const [marginRight, setMarginRight] = useState<number>(
        form_data.page_margin_right ?? 12
    );
    const [lineSpacing, setLineSpacing] = useState<string>(
        form_data.line_spacing || '1.15'
    );

    // HTML Content for rich-text editor
    const [htmlContent, setHtmlContent] = useState<string>(rendered_html);

    useEffect(() => {
        setHtmlContent(rendered_html);
    }, [rendered_html]);

    // Update cylinder count
    const handleCylinderCountChange = (count: number) => {
        const safeCount = Math.max(1, Math.min(32, count));
        setCylindersCount(safeCount);
        setMeasurements((prev) => {
            const next: ClearanceValveMeasurement[] = [];
            for (let i = 1; i <= safeCount; i++) {
                const existing = prev.find((p) => p.cylinder === i);
                if (existing) {
                    next.push(existing);
                } else {
                    next.push({
                        cylinder: i,
                        ex_before_r: '',
                        ex_before_l: '',
                        ex_after_r: '',
                        ex_after_l: '',
                        in_before_r: '',
                        in_before_l: '',
                        in_after_r: '',
                        in_after_l: '',
                    });
                }
            }
            return next;
        });
        setPreviewKey((k) => k + 1);
    };

    // Update single cell in measurement matrix
    const updateMeasurementCell = (
        cylinder: number,
        field: keyof Omit<ClearanceValveMeasurement, 'cylinder'>,
        val: string
    ) => {
        setMeasurements((prev) =>
            prev.map((item) =>
                item.cylinder === cylinder ? { ...item, [field]: val } : item
            )
        );
    };

    // Clear all measurements
    const handleClearMeasurements = () => {
        setMeasurements((prev) =>
            prev.map((row) => ({
                cylinder: row.cylinder,
                ex_before_r: '',
                ex_before_l: '',
                ex_after_r: '',
                ex_after_l: '',
                in_before_r: '',
                in_before_l: '',
                in_after_r: '',
                in_after_l: '',
            }))
        );
    };

    // Fill sample values matching scan document
    const handleFillScanExample = () => {
        const scanValues: Record<number, Partial<ClearanceValveMeasurement>> = {
            1: { ex_before_r: '0.65', ex_before_l: '0.65', ex_after_r: '0.60', ex_after_l: '0.60', in_before_r: '0.35', in_before_l: '0.35', in_after_r: '0.30', in_after_l: '0.30' },
            2: { ex_before_r: '0.60', ex_before_l: '0.65', ex_after_r: '0.60', ex_after_l: '0.60', in_before_r: '0.30', in_before_l: '0.35', in_after_r: '0.30', in_after_l: '0.30' },
            3: { ex_before_r: '0.70', ex_before_l: '0.70', ex_after_r: '0.60', ex_after_l: '0.60', in_before_r: '0.35', in_before_l: '0.30', in_after_r: '0.30', in_after_l: '0.30' },
            4: { ex_before_r: '0.65', ex_before_l: '0.60', ex_after_r: '0.60', ex_after_l: '0.60', in_before_r: '0.30', in_before_l: '0.30', in_after_r: '0.30', in_after_l: '0.30' },
            5: { ex_before_r: '0.60', ex_before_l: '0.60', ex_after_r: '0.60', ex_after_l: '0.60', in_before_r: '0.35', in_before_l: '0.35', in_after_r: '0.30', in_after_l: '0.30' },
            6: { ex_before_r: '0.70', ex_before_l: '0.65', ex_after_r: '0.60', ex_after_l: '0.60', in_before_r: '0.30', in_before_l: '0.30', in_after_r: '0.30', in_after_l: '0.30' },
            7: { ex_before_r: '0.65', ex_before_l: '0.65', ex_after_r: '0.60', ex_after_l: '0.60', in_before_r: '0.35', in_before_l: '0.30', in_after_r: '0.30', in_after_l: '0.30' },
            8: { ex_before_r: '0.65', ex_before_l: '0.60', ex_after_r: '0.60', ex_after_l: '0.60', in_before_r: '0.30', in_before_l: '0.35', in_after_r: '0.30', in_after_l: '0.30' },
        };

        setMeasurements((prev) =>
            prev.map((row) => ({
                ...row,
                ...(scanValues[row.cylinder] || {
                    ex_before_r: '0.65',
                    ex_before_l: '0.65',
                    ex_after_r: '0.60',
                    ex_after_l: '0.60',
                    in_before_r: '0.35',
                    in_before_l: '0.35',
                    in_after_r: '0.30',
                    in_after_l: '0.30',
                }),
            }))
        );
        setCylinderNotes('Penyetelan celah katup intake dan exhaust telah disesuaikan dengan rekomendasi manual book.');
        setVisualInspection('Kondisi valve spring, rocker arm, push rod, dan lock nut dalam kondisi baik dan tidak ada keausan abnormal.');
    };

    // When unit changes in selector
    const handleUnitChange = (newUnitId: string) => {
        router.get(
            clearanceValveRoutes.index().url,
            { unit_id: Number(newUnitId) },
            { preserveState: false }
        );
    };

    // When machine changes in selector
    const handleMachineChange = (newMachineId: string) => {
        const m = machines.find((mach) => String(mach.id) === newMachineId);
        if (m) {
            setMachineId(m.id);
            if (m.type) setModelType(m.type);
            if (m.serial_number) setSerialNumber(m.serial_number);
            if (m.capacity_kw) setInstalledPower(m.capacity_kw);
            setMachineNumber(
                m.name.replace(/MIRRLEES\s*#/i, '').replace(/MESIN\s*#/i, '').replace(/UNIT\s*#/i, '').trim()
            );
            // Fetch test for this machine and current date
            router.get(
                clearanceValveRoutes.index().url,
                {
                    unit_id: unit.id,
                    machine_id: m.id,
                    test_date: testDate,
                },
                { preserveState: true }
            );
        }
    };

    // When date changes
    const handleDateChange = (newDate: string) => {
        setTestDate(newDate);
        router.get(
            clearanceValveRoutes.index().url,
            {
                unit_id: unit.id,
                machine_id: machineId,
                test_date: newDate,
            },
            { preserveState: true }
        );
    };

    // Manager UL selection
    const handleManagerUlChange = (empId: string) => {
        setManagerUlId(empId);
        const emp = manager_options.find((e) => String(e.id) === empId);
        if (emp) {
            setManagerUlName(emp.name);
            setManagerUlTitle(
                emp.position || `Manager UL ${unit.service_unit_name || unit.name}`
            );
        }
    };

    // TL Har selection
    const handleTlHarChange = (empId: string) => {
        setTlHarId(empId);
        const emp = tl_options.find((e) => String(e.id) === empId);
        if (emp) {
            setTlHarName(emp.name);
            setTlHarTitle(emp.position || 'Team Leader Pemeliharaan');
        }
    };

    // Staff Har selection
    const handleStaffHarChange = (empId: string) => {
        setStaffHarId(empId);
        const emp = staff_options.find((e) => String(e.id) === empId);
        if (emp) {
            setStaffHarName(emp.name);
            setStaffHarTitle(emp.position || 'Staff Pemeliharaan');
        }
    };

    // Live computed PDF URL for preview with current parameters
    const previewPdfUrl = useMemo(() => {
        const separator = pdf_url.includes('?') ? '&' : '?';
        const params = new URLSearchParams();
        params.set('v', String(previewKey));
        params.set('cylinders_count', String(cylindersCount));
        params.set('page_margin_top', String(marginTop));
        params.set('page_margin_bottom', String(marginBottom));
        params.set('page_margin_left', String(marginLeft));
        params.set('page_margin_right', String(marginRight));
        params.set('line_spacing', lineSpacing);
        return `${pdf_url}${separator}${params.toString()}`;
    }, [
        pdf_url,
        previewKey,
        cylindersCount,
        marginTop,
        marginBottom,
        marginLeft,
        marginRight,
        lineSpacing,
    ]);

    // Save action
    const handleSave = (onSuccessCallback?: () => void) => {
        setIsSaving(true);
        const payload = {
            unit_id: unit.id,
            machine_id: machineId,
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
            standard_ex: standardEx,
            standard_in: standardIn,
            standard_allowed: standardAllowed || null,
            visual_inspection: visualInspection,
            cylinder_notes: cylinderNotes,
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
        };

        router.post(clearanceValveRoutes.store().url, payload, {
            preserveScroll: true,
            onSuccess: () => {
                setPreviewKey((k) => k + 1);
                if (onSuccessCallback) {
                    onSuccessCallback();
                }
            },
            onFinish: () => setIsSaving(false),
        });
    };

    const handleDownloadPdf = () => {
        const separator = pdf_url.includes('?') ? '&' : '?';
        const params = new URLSearchParams();
        params.set('download', '1');
        params.set('cylinders_count', String(cylindersCount));
        params.set('page_margin_top', String(marginTop));
        params.set('page_margin_bottom', String(marginBottom));
        params.set('page_margin_left', String(marginLeft));
        params.set('page_margin_right', String(marginRight));
        params.set('line_spacing', lineSpacing);
        window.open(`${pdf_url}${separator}${params.toString()}`, '_blank');
    };

    const handleOpenPreview = () => {
        if (can_write) {
            handleSave(() => {
                setViewTab('pdf');
                setPreviewKey((k) => k + 1);
            });
        } else {
            setViewTab('pdf');
            setPreviewKey((k) => k + 1);
        }
    };

    // Find if selected signatories have signatures
    const selectedManagerUl = manager_options.find(
        (e) => String(e.id) === managerUlId
    );
    const selectedTl = tl_options.find((e) => String(e.id) === tlHarId);
    const selectedStaff = staff_options.find((e) => String(e.id) === staffHarId);

    return (
        <>
            <Head title={`Formulir Pengukuran Clearance Valve - ${unit.name}`} />

            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Formulir Pengukuran Clearance Valve"
                    description="Input celah katup intake & exhaust per silinder, atur penandatangan & margin layout, dan cetak PDF resmi."
                    actions={
                        <div className="flex flex-wrap items-center gap-2">
                            <Button
                                variant="outline"
                                onClick={() => router.get(harFormulir.index().url, { unit_id: unit.id })}
                                className="gap-2"
                            >
                                <ArrowLeft className="size-4" />
                                Kembali
                            </Button>
                            <Button
                                variant="outline"
                                onClick={() => setShowHistory(true)}
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
                                    onClick={() => handleSave()}
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
                        No. Dokumen: <span className="font-mono font-medium">{docNumber}</span> (Rev. {revision})
                    </div>
                </div>

                {/* 2-Column Layout */}
                <div className="flex flex-col lg:flex-row items-start gap-6 w-full">
                    {/* LEFT COLUMN: Metadata, Signatories, Page Settings */}
                    <div className="w-full lg:w-[380px] xl:w-[420px] shrink-0 space-y-5">
                        {/* Card 1: Metadata Formulir & Mesin */}
                        <div className="rounded-lg border border-border bg-card p-4 space-y-4">
                            <div className="border-b border-border pb-2">
                                <h3 className="text-sm font-semibold text-foreground">
                                    Metadata Formulir &amp; Mesin
                                </h3>
                                <p className="text-[12px] text-muted-foreground">
                                    Informasi dasar unit, mesin, dan pengujian.
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
                                                    <SelectItem key={u.id} value={String(u.id)}>
                                                        {u.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    ) : (
                                        <Input
                                            value={unit.name}
                                            disabled
                                            className="h-8 text-xs bg-muted"
                                        />
                                    )}
                                </div>

                                {/* Machine Selector - strictly filtered to this unit */}
                                <div className="space-y-1">
                                    <div className="flex items-center justify-between">
                                        <Label className="text-xs">Pilihan Mesin (Unit Ini)</Label>
                                        <Badge variant="outline" className="text-[10px] py-0">
                                            {machines.length} Mesin
                                        </Badge>
                                    </div>
                                    <Select
                                        value={String(machineId)}
                                        onValueChange={handleMachineChange}
                                    >
                                        <SelectTrigger className="h-8 text-xs">
                                            <SelectValue placeholder="Pilih mesin..." />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {machines.map((m) => (
                                                <SelectItem key={m.id} value={String(m.id)}>
                                                    {m.name} {m.type ? `(${m.type})` : ''}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>

                                {/* Tanggal Pelaksanaan Test */}
                                <div className="space-y-1">
                                    <Label className="text-xs">Tanggal Pelaksanaan Test</Label>
                                    <Input
                                        type="date"
                                        value={testDate}
                                        onChange={(e) => handleDateChange(e.target.value)}
                                        className="h-8 text-xs"
                                    />
                                </div>

                                {/* Nomor Dokumen & Revisi */}
                                <div className="grid grid-cols-2 gap-2">
                                    <div className="space-y-1">
                                        <Label className="text-xs">No. Dokumen</Label>
                                        <Input
                                            value={docNumber}
                                            onChange={(e) => setDocNumber(e.target.value)}
                                            className="h-8 text-xs"
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

                                {/* Data Spesifikasi Teknis Mesin */}
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
                                                placeholder="8M 453 C"
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
                                                placeholder="No. seri mesin"
                                                className="h-7 text-xs"
                                            />
                                        </div>
                                        <div className="space-y-1">
                                            <Label className="text-[11px]">Mesin No</Label>
                                            <Input
                                                value={machineNumber}
                                                onChange={(e) => setMachineNumber(e.target.value)}
                                                placeholder="4"
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
                                                placeholder="2800"
                                                className="h-7 text-xs"
                                            />
                                        </div>
                                        <div className="space-y-1">
                                            <Label className="text-[11px]">Daya Mampu</Label>
                                            <Input
                                                value={capablePower}
                                                onChange={(e) => setCapablePower(e.target.value)}
                                                placeholder="2000"
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

                                    {/* Standar Pabrik & Batas Ijin */}
                                    <div className="grid grid-cols-2 gap-2 pt-1">
                                        <div className="space-y-1">
                                            <Label className="text-[11px]">Standar EX</Label>
                                            <Input
                                                value={standardEx}
                                                onChange={(e) => setStandardEx(e.target.value)}
                                                placeholder="0.60 mm"
                                                className="h-7 text-xs"
                                            />
                                        </div>
                                        <div className="space-y-1">
                                            <Label className="text-[11px]">Standar IN</Label>
                                            <Input
                                                value={standardIn}
                                                onChange={(e) => setStandardIn(e.target.value)}
                                                placeholder="0.30 mm"
                                                className="h-7 text-xs"
                                            />
                                        </div>
                                    </div>
                                    <div className="space-y-1">
                                        <Label className="text-[11px]">Standar Yang Diijinkan</Label>
                                        <Input
                                            value={standardAllowed || ''}
                                            onChange={(e) => setStandardAllowed(e.target.value)}
                                            placeholder="± 0.05 mm (Opsional)"
                                            className="h-7 text-xs"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Card 2: Penandatangan Dokumen (3 Pihak) */}
                        <div className="rounded-lg border border-border bg-card p-4 space-y-4">
                            <div className="border-b border-border pb-2">
                                <h3 className="text-sm font-semibold text-foreground">
                                    Penandatangan Dokumen
                                </h3>
                                <p className="text-[12px] text-muted-foreground">
                                    Struktur hierarki: Manager UL (Kiri), TL Har (Tengah), Staf Har (Kanan).
                                </p>
                            </div>

                            <div className="space-y-4 text-xs">
                                {/* 1. Manager UL (Mengetahui - Kiri) */}
                                <div className="space-y-2 rounded-md border border-border/70 p-2.5 bg-muted/20">
                                    <div className="flex items-center justify-between">
                                        <Label className="font-semibold text-foreground">
                                            1. Mengetahui (Manager UL)
                                        </Label>
                                        {selectedManagerUl?.has_signature ? (
                                            <Badge variant="outline" className="border-emerald-500/40 text-emerald-700 bg-emerald-50 text-[10px] py-0">
                                                TTD Siap
                                            </Badge>
                                        ) : (
                                            <Badge variant="outline" className="text-muted-foreground text-[10px] py-0">
                                                Tanpa TTD
                                            </Badge>
                                        )}
                                    </div>
                                    <Select
                                        value={managerUlId}
                                        onValueChange={handleManagerUlChange}
                                    >
                                        <SelectTrigger className="h-7 text-xs">
                                            <SelectValue placeholder="Pilih Manager UL..." />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {manager_options.map((e) => (
                                                <SelectItem key={e.id} value={String(e.id)}>
                                                    {e.name} {e.position ? `(${e.position})` : ''}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <div className="grid grid-cols-2 gap-2">
                                        <Input
                                            value={managerUlName}
                                            onChange={(e) => setManagerUlName(e.target.value)}
                                            placeholder="Nama Manager UL"
                                            className="h-7 text-xs"
                                        />
                                        <Input
                                            value={managerUlTitle}
                                            onChange={(e) => setManagerUlTitle(e.target.value)}
                                            placeholder="Jabatan Manager"
                                            className="h-7 text-xs"
                                        />
                                    </div>
                                </div>

                                {/* 2. TL Pemeliharaan (Diperiksa - Tengah) */}
                                <div className="space-y-2 rounded-md border border-border/70 p-2.5 bg-muted/20">
                                    <div className="flex items-center justify-between">
                                        <Label className="font-semibold text-foreground">
                                            2. Diperiksa (TL Pemeliharaan)
                                        </Label>
                                        {selectedTl?.has_signature ? (
                                            <Badge variant="outline" className="border-emerald-500/40 text-emerald-700 bg-emerald-50 text-[10px] py-0">
                                                TTD Siap
                                            </Badge>
                                        ) : (
                                            <Badge variant="outline" className="text-muted-foreground text-[10px] py-0">
                                                Tanpa TTD
                                            </Badge>
                                        )}
                                    </div>
                                    <Select
                                        value={tlHarId}
                                        onValueChange={handleTlHarChange}
                                    >
                                        <SelectTrigger className="h-7 text-xs">
                                            <SelectValue placeholder="Pilih TL Pemeliharaan..." />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {tl_options.map((e) => (
                                                <SelectItem key={e.id} value={String(e.id)}>
                                                    {e.name} {e.position ? `(${e.position})` : ''}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <div className="grid grid-cols-2 gap-2">
                                        <Input
                                            value={tlHarName}
                                            onChange={(e) => setTlHarName(e.target.value)}
                                            placeholder="Nama TL Pemeliharaan"
                                            className="h-7 text-xs"
                                        />
                                        <Input
                                            value={tlHarTitle}
                                            onChange={(e) => setTlHarTitle(e.target.value)}
                                            placeholder="Jabatan TL"
                                            className="h-7 text-xs"
                                        />
                                    </div>
                                </div>

                                {/* 3. Staff Pemeliharaan (Dibuat - Kanan) */}
                                <div className="space-y-2 rounded-md border border-border/70 p-2.5 bg-muted/20">
                                    <div className="flex items-center justify-between">
                                        <Label className="font-semibold text-foreground">
                                            3. Dibuat (Staff Pemeliharaan)
                                        </Label>
                                        {selectedStaff?.has_signature ? (
                                            <Badge variant="outline" className="border-emerald-500/40 text-emerald-700 bg-emerald-50 text-[10px] py-0">
                                                TTD Siap
                                            </Badge>
                                        ) : (
                                            <Badge variant="outline" className="text-muted-foreground text-[10px] py-0">
                                                Tanpa TTD
                                            </Badge>
                                        )}
                                    </div>
                                    <Select
                                        value={staffHarId}
                                        onValueChange={handleStaffHarChange}
                                    >
                                        <SelectTrigger className="h-7 text-xs">
                                            <SelectValue placeholder="Pilih Staff Pemeliharaan..." />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {staff_options.map((e) => (
                                                <SelectItem key={e.id} value={String(e.id)}>
                                                    {e.name} {e.position ? `(${e.position})` : ''}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <div className="grid grid-cols-2 gap-2">
                                        <Input
                                            value={staffHarName}
                                            onChange={(e) => setStaffHarName(e.target.value)}
                                            placeholder="Nama Staff"
                                            className="h-7 text-xs"
                                        />
                                        <Input
                                            value={staffHarTitle}
                                            onChange={(e) => setStaffHarTitle(e.target.value)}
                                            placeholder="Jabatan Staff"
                                            className="h-7 text-xs"
                                        />
                                    </div>
                                </div>

                                <div className="rounded-md border border-border bg-card p-2 text-[11px] text-muted-foreground">
                                    <Info className="size-3.5 inline mr-1 text-primary" />
                                    Tanda tangan digital pegawai otomatis terpasang pada dokumen PDF jika sudah diunggah pada master pegawai.
                                </div>
                            </div>
                        </div>

                        {/* Card 3: Page Settings (Pengaturan Halaman PDF) */}
                        <div className="rounded-lg border border-border bg-card p-4 space-y-4">
                            <div className="border-b border-border pb-2">
                                <h3 className="text-sm font-semibold text-foreground">
                                    Page Settings
                                </h3>
                                <p className="text-[12px] text-muted-foreground">
                                    Pengaturan layout &amp; margin PDF cetak.
                                </p>
                            </div>

                            <div className="space-y-3 text-xs">
                                <div className="grid grid-cols-4 gap-2">
                                    <div className="space-y-1">
                                        <Label className="text-[11px]">Atas (mm)</Label>
                                        <Input
                                            type="number"
                                            min={0}
                                            max={50}
                                            value={marginTop}
                                            onChange={(e) => setMarginTop(Number(e.target.value))}
                                            className="h-7 text-xs text-center"
                                        />
                                    </div>
                                    <div className="space-y-1">
                                        <Label className="text-[11px]">Bawah (mm)</Label>
                                        <Input
                                            type="number"
                                            min={0}
                                            max={50}
                                            value={marginBottom}
                                            onChange={(e) => setMarginBottom(Number(e.target.value))}
                                            className="h-7 text-xs text-center"
                                        />
                                    </div>
                                    <div className="space-y-1">
                                        <Label className="text-[11px]">Kiri (mm)</Label>
                                        <Input
                                            type="number"
                                            min={0}
                                            max={50}
                                            value={marginLeft}
                                            onChange={(e) => setMarginLeft(Number(e.target.value))}
                                            className="h-7 text-xs text-center"
                                        />
                                    </div>
                                    <div className="space-y-1">
                                        <Label className="text-[11px]">Kanan (mm)</Label>
                                        <Input
                                            type="number"
                                            min={0}
                                            max={50}
                                            value={marginRight}
                                            onChange={(e) => setMarginRight(Number(e.target.value))}
                                            className="h-7 text-xs text-center"
                                        />
                                    </div>
                                </div>

                                <div className="space-y-1">
                                    <Label className="text-[11px]">Spasi Baris</Label>
                                    <Select value={lineSpacing} onValueChange={setLineSpacing}>
                                        <SelectTrigger className="h-7 text-xs">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="1.0">1,0 (Rapat)</SelectItem>
                                            <SelectItem value="1.15">1,15 (Standar ISO)</SelectItem>
                                            <SelectItem value="1.5">1,5 (Lebar)</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* RIGHT COLUMN: Form Editor, HTML Editor, PDF Preview */}
                    <div className="flex-1 w-full min-w-0 flex flex-col gap-4">
                        <div className="flex flex-1 flex-col justify-between rounded-lg border border-border bg-card shadow-sm">
                            {/* Card Header with View Tabs */}
                            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-border p-4">
                                <div>
                                    <h3 className="text-base font-semibold text-foreground">
                                        Isi Formulir &amp; Dokumen
                                    </h3>
                                    <p className="text-[12px] text-muted-foreground">
                                        Kop surat, tanggal, spesifikasi, dan tanda tangan otomatis disesuaikan pada PDF.
                                    </p>
                                </div>

                                {/* Tabs switch */}
                                <div className="flex flex-wrap overflow-hidden rounded-md border border-border text-xs">
                                    <button
                                        type="button"
                                        onClick={() => setViewTab('form')}
                                        className={`flex items-center gap-1.5 px-3 py-1.5 transition-colors whitespace-nowrap ${
                                            viewTab === 'form'
                                                ? 'bg-primary text-primary-foreground font-medium'
                                                : 'bg-secondary text-secondary-foreground hover:bg-secondary/80'
                                        }`}
                                    >
                                        <SlidersHorizontal className="size-3.5" />
                                        Editor Formulir
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => setViewTab('html')}
                                        className={`flex items-center gap-1.5 px-3 py-1.5 transition-colors whitespace-nowrap ${
                                            viewTab === 'html'
                                                ? 'bg-primary text-primary-foreground font-medium'
                                                : 'bg-secondary text-secondary-foreground hover:bg-secondary/80'
                                        }`}
                                    >
                                        <FileCode2 className="size-3.5" />
                                        Editor Teks (HTML)
                                    </button>
                                    <button
                                        type="button"
                                        onClick={handleOpenPreview}
                                        className={`flex items-center gap-1.5 px-3 py-1.5 transition-colors whitespace-nowrap ${
                                            viewTab === 'pdf'
                                                ? 'bg-primary text-primary-foreground font-medium'
                                                : 'bg-secondary text-secondary-foreground hover:bg-secondary/80'
                                        }`}
                                    >
                                        <Printer className="size-3.5" />
                                        Pratinjau PDF
                                    </button>
                                </div>
                            </div>

                            {/* TAB 1: FORM MEASUREMENT INPUT */}
                            {viewTab === 'form' && (
                                <div className="p-4 space-y-4">
                                    {/* Toolbar Silinder & Quick Actions */}
                                    <div className="flex flex-wrap items-center justify-between gap-3 rounded-md border border-border bg-muted/20 p-2.5 text-xs">
                                        <div className="flex items-center gap-2">
                                            <Label className="text-xs font-semibold">Jumlah Silinder:</Label>
                                            <div className="flex items-center gap-1">
                                                {[6, 8, 12, 16].map((num) => (
                                                    <Button
                                                        key={num}
                                                        type="button"
                                                        size="sm"
                                                        variant={cylindersCount === num ? 'default' : 'outline'}
                                                        onClick={() => handleCylinderCountChange(num)}
                                                        className="h-7 px-2.5 text-xs"
                                                    >
                                                        {num}
                                                    </Button>
                                                ))}
                                                <Input
                                                    type="number"
                                                    min={1}
                                                    max={32}
                                                    value={cylindersCount}
                                                    onChange={(e) => handleCylinderCountChange(Number(e.target.value))}
                                                    className="h-7 w-16 text-xs text-center ml-1"
                                                />
                                            </div>
                                        </div>

                                        <div className="flex items-center gap-1.5">
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                onClick={handleFillScanExample}
                                                className="h-7 gap-1 text-[11px] text-primary border-primary/30 hover:bg-primary/5"
                                            >
                                                <Sparkles className="size-3.5" />
                                                Isi Contoh Scan
                                            </Button>
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                onClick={handleClearMeasurements}
                                                className="h-7 gap-1 text-[11px]"
                                            >
                                                <RotateCcw className="size-3.5" />
                                                Reset Nilai
                                            </Button>
                                        </div>
                                    </div>

                                    {/* Measurement Matrix Table */}
                                    <div className="overflow-x-auto rounded-md border border-border">
                                        <table className="w-full border-collapse text-xs min-w-[700px]">
                                            <thead>
                                                <tr className="border-b border-border bg-muted/40 font-semibold text-center">
                                                    <th rowSpan={3} className="border-r border-border p-2 w-12">
                                                        NO
                                                    </th>
                                                    <th colSpan={4} className="border-r border-border p-2 tracking-wider bg-blue-500/10 text-foreground font-bold">
                                                        EXHAUST
                                                    </th>
                                                    <th colSpan={4} className="p-2 tracking-wider bg-emerald-500/10 text-foreground font-bold">
                                                        INTAKE
                                                    </th>
                                                </tr>
                                                <tr className="border-b border-border bg-muted/25 font-semibold text-center text-[11px]">
                                                    <th colSpan={2} className="border-r border-border p-1 bg-blue-500/5">
                                                        sebelum
                                                    </th>
                                                    <th colSpan={2} className="border-r border-border p-1 bg-blue-500/10 font-bold text-primary">
                                                        sesudah
                                                    </th>
                                                    <th colSpan={2} className="border-r border-border p-1 bg-emerald-500/5">
                                                        sebelum
                                                    </th>
                                                    <th colSpan={2} className="p-1 bg-emerald-500/10 font-bold text-primary">
                                                        sesudah
                                                    </th>
                                                </tr>
                                                <tr className="border-b border-border bg-muted/15 font-semibold text-center text-[11px]">
                                                    <th className="border-r border-border p-1 w-14">R</th>
                                                    <th className="border-r border-border p-1 w-14">L</th>
                                                    <th className="border-r border-border p-1 w-14 text-primary font-bold">R</th>
                                                    <th className="border-r border-border p-1 w-14 text-primary font-bold">L</th>
                                                    <th className="border-r border-border p-1 w-14">R</th>
                                                    <th className="border-r border-border p-1 w-14">L</th>
                                                    <th className="border-r border-border p-1 w-14 text-primary font-bold">R</th>
                                                    <th className="p-1 w-14 text-primary font-bold">L</th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-border">
                                                {measurements.map((row) => (
                                                    <tr
                                                        key={row.cylinder}
                                                        className="hover:bg-muted/20 text-center transition-colors"
                                                    >
                                                        <td className="border-r border-border p-2 font-bold text-center bg-muted/10">
                                                            {row.cylinder}
                                                        </td>
                                                        {/* Exhaust sebelum */}
                                                        <td className="border-r border-border p-1">
                                                            <Input
                                                                value={row.ex_before_r}
                                                                onChange={(e) => updateMeasurementCell(row.cylinder, 'ex_before_r', e.target.value)}
                                                                className="h-7 text-center text-xs p-1"
                                                                placeholder="—"
                                                            />
                                                        </td>
                                                        <td className="border-r border-border p-1">
                                                            <Input
                                                                value={row.ex_before_l}
                                                                onChange={(e) => updateMeasurementCell(row.cylinder, 'ex_before_l', e.target.value)}
                                                                className="h-7 text-center text-xs p-1"
                                                                placeholder="—"
                                                            />
                                                        </td>
                                                        {/* Exhaust sesudah */}
                                                        <td className="border-r border-border p-1 bg-blue-50/20 dark:bg-blue-950/10">
                                                            <Input
                                                                value={row.ex_after_r}
                                                                onChange={(e) => updateMeasurementCell(row.cylinder, 'ex_after_r', e.target.value)}
                                                                className="h-7 text-center text-xs p-1 font-semibold text-blue-600 dark:text-blue-400"
                                                                placeholder="0,6"
                                                            />
                                                        </td>
                                                        <td className="border-r border-border p-1 bg-blue-50/20 dark:bg-blue-950/10">
                                                            <Input
                                                                value={row.ex_after_l}
                                                                onChange={(e) => updateMeasurementCell(row.cylinder, 'ex_after_l', e.target.value)}
                                                                className="h-7 text-center text-xs p-1 font-semibold text-blue-600 dark:text-blue-400"
                                                                placeholder="0,6"
                                                            />
                                                        </td>
                                                        {/* Intake sebelum */}
                                                        <td className="border-r border-border p-1">
                                                            <Input
                                                                value={row.in_before_r}
                                                                onChange={(e) => updateMeasurementCell(row.cylinder, 'in_before_r', e.target.value)}
                                                                className="h-7 text-center text-xs p-1"
                                                                placeholder="—"
                                                            />
                                                        </td>
                                                        <td className="border-r border-border p-1">
                                                            <Input
                                                                value={row.in_before_l}
                                                                onChange={(e) => updateMeasurementCell(row.cylinder, 'in_before_l', e.target.value)}
                                                                className="h-7 text-center text-xs p-1"
                                                                placeholder="—"
                                                            />
                                                        </td>
                                                        {/* Intake sesudah */}
                                                        <td className="border-r border-border p-1 bg-emerald-50/20 dark:bg-emerald-950/10">
                                                            <Input
                                                                value={row.in_after_r}
                                                                onChange={(e) => updateMeasurementCell(row.cylinder, 'in_after_r', e.target.value)}
                                                                className="h-7 text-center text-xs p-1 font-semibold text-emerald-600 dark:text-emerald-400"
                                                                placeholder="0,3"
                                                            />
                                                        </td>
                                                        <td className="p-1 bg-emerald-50/20 dark:bg-emerald-950/10">
                                                            <Input
                                                                value={row.in_after_l}
                                                                onChange={(e) => updateMeasurementCell(row.cylinder, 'in_after_l', e.target.value)}
                                                                className="h-7 text-center text-xs p-1 font-semibold text-emerald-600 dark:text-emerald-400"
                                                                placeholder="0,3"
                                                            />
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>

                                    {/* Standar & Catatan Box */}
                                    <div className="grid gap-3 md:grid-cols-2">
                                        <div className="rounded-md border border-border bg-muted/20 p-3 space-y-2 text-xs">
                                            <span className="font-semibold text-foreground">
                                                Standar Batas Celah Katup:
                                            </span>
                                            <div className="space-y-1.5 text-muted-foreground text-[11px]">
                                                <div className="flex items-center justify-between">
                                                    <span>Standar Pabrik Exhaust (EX):</span>
                                                    <strong className="text-foreground font-mono">{standardEx || '0.60 mm'}</strong>
                                                </div>
                                                <div className="flex items-center justify-between">
                                                    <span>Standar Pabrik Intake (IN):</span>
                                                    <strong className="text-foreground font-mono">{standardIn || '0.30 mm'}</strong>
                                                </div>
                                                <div className="flex items-center justify-between">
                                                    <span>Standar yang Diijinkan:</span>
                                                    <strong className="text-foreground font-mono">{standardAllowed || '± 0.05 mm'}</strong>
                                                </div>
                                            </div>
                                        </div>

                                        <div className="space-y-1.5 text-xs">
                                            <Label className="font-semibold">Catatan Silinder / Keterangan:</Label>
                                            <textarea
                                                rows={3}
                                                value={cylinderNotes}
                                                onChange={(e) => setCylinderNotes(e.target.value)}
                                                placeholder="Tuliskan keterangan detail hasil penyetelan celah katup..."
                                                className="w-full rounded-md border border-input bg-background p-2 text-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                            />
                                        </div>
                                    </div>

                                    <div className="space-y-1.5 text-xs">
                                        <Label className="font-semibold">Pemeriksaan Visual / Catatan Tambahan:</Label>
                                        <textarea
                                            rows={2}
                                            value={visualInspection}
                                            onChange={(e) => setVisualInspection(e.target.value)}
                                            placeholder="Pemeriksaan visual valve spring, rocker arm, push rod, lock nut..."
                                            className="w-full rounded-md border border-input bg-background p-2 text-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                        />
                                    </div>
                                </div>
                            )}

                            {/* TAB 2: RICH TEXT HTML EDITOR */}
                            {viewTab === 'html' && (
                                <div className="p-4 space-y-3">
                                    <div className="rounded-md border border-amber-300 bg-amber-50 p-2.5 text-xs text-amber-800 dark:border-amber-800/60 dark:bg-amber-950/40 dark:text-amber-300">
                                        Mode Editor Teks (HTML) memungkinkan penyesuaian isi dokumen secara langsung. PDF yang diekspor akan mengikuti teks hasil pengeditan ini jika disimpan pada mode ini.
                                    </div>
                                    <div className="rounded-md border border-border bg-card">
                                        <RichTextEditor
                                            value={htmlContent}
                                            onChange={setHtmlContent}
                                            disabled={!can_write}
                                            autoGrow
                                        />
                                    </div>
                                </div>
                            )}

                            {/* TAB 3: PDF PREVIEW */}
                            {viewTab === 'pdf' && (
                                <div className="p-4 space-y-3">
                                    <div className="flex items-center justify-between text-xs text-muted-foreground">
                                        <span>
                                            Pratinjau ini identik dengan hasil cetak PDF A4 resmi PT PLN Nusantara Power.
                                        </span>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() => setPreviewKey((k) => k + 1)}
                                            className="h-7 gap-1 text-xs"
                                        >
                                            <RotateCcw className="size-3.5" />
                                            Segarkan Pratinjau
                                        </Button>
                                    </div>
                                    <PdfPreviewFrame
                                        key={`${previewKey}-${cylindersCount}`}
                                        title="Pratinjau PDF Clearance Valve"
                                        src={previewPdfUrl}
                                        className="h-[750px] w-full rounded-md border border-border bg-white"
                                    />
                                </div>
                            )}

                            {/* Card Bottom Actions */}
                            <div className="flex flex-wrap items-center justify-between gap-3 border-t border-border bg-muted/10 p-4">
                                <div className="flex items-center gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => setShowHistory(true)}
                                        className="gap-1.5 text-xs"
                                    >
                                        <History className="size-3.5" />
                                        Riwayat ({history.length})
                                    </Button>
                                </div>

                                <div className="flex flex-wrap items-center gap-2">
                                    <Button
                                        variant="secondary"
                                        onClick={() => router.get(harFormulir.index().url, { unit_id: unit.id })}
                                    >
                                        Batal
                                    </Button>
                                    <Button
                                        variant="outline"
                                        onClick={handleOpenPreview}
                                        className="gap-1.5"
                                    >
                                        <Printer className="size-4" />
                                        Pratinjau PDF
                                    </Button>
                                    <Button
                                        variant="outline"
                                        onClick={handleDownloadPdf}
                                        className="gap-1.5"
                                    >
                                        <Download className="size-4" />
                                        Unduh PDF
                                    </Button>
                                    {can_write && (
                                        <Button
                                            onClick={() => handleSave()}
                                            disabled={isSaving}
                                            className="gap-1.5 bg-primary text-primary-foreground hover:bg-primary/90"
                                        >
                                            <Save className="size-4" />
                                            {isSaving ? 'Menyimpan…' : 'Simpan Perubahan'}
                                        </Button>
                                    )}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Dialog Riwayat Pengujian */}
            <Dialog open={showHistory} onOpenChange={setShowHistory}>
                <DialogContent className="max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Riwayat Formulir Clearance Valve - {unit.name}</DialogTitle>
                        <DialogDescription>
                            Daftar catatan pengukuran clearance valve yang pernah disimpan untuk unit dan mesin ini.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="max-h-[380px] overflow-y-auto divide-y divide-border">
                        {history.length === 0 ? (
                            <div className="py-8 text-center text-xs text-muted-foreground">
                                Belum ada riwayat pengujian untuk mesin ini.
                            </div>
                        ) : (
                            history.map((item) => (
                                <div
                                    key={item.id}
                                    className="flex items-center justify-between py-2.5 text-xs hover:bg-muted/30 px-2 rounded"
                                >
                                    <div>
                                        <div className="font-semibold text-foreground">
                                            Tanggal: {item.test_date}
                                        </div>
                                        <div className="text-muted-foreground text-[11px]">
                                            No. Dok: {item.document_number} (Rev. {item.revision}) • Mode: {item.format}
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-1.5">
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() => {
                                                setShowHistory(false);
                                                router.get(clearanceValveRoutes.index().url, {
                                                    unit_id: unit.id,
                                                    machine_id: item.machine_id,
                                                    test_date: item.test_date,
                                                    record_id: item.id,
                                                });
                                            }}
                                            className="h-7 text-xs"
                                        >
                                            Buka Dokumen
                                        </Button>
                                    </div>
                                </div>
                            ))
                        )}
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}

ClearanceValveIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Formulir Pemeliharaan', href: harFormulir.index() },
        { title: 'Pengukuran Clearance Valve', href: clearanceValveRoutes.index() },
    ],
};
