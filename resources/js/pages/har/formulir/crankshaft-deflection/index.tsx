import { Head, router } from '@inertiajs/react';
import {
    ArrowLeft,
    CheckCircle2,
    Clock,
    Download,
    Eye,
    FileCode2,
    FileText,
    History,
    Info,
    Printer,
    RotateCcw,
    Save,
    Sliders,
    SlidersHorizontal,
    Sparkles,
    Trash2,
} from 'lucide-react';
import { useMemo, useState } from 'react';
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
import crankshaftRoutes from '@/routes/har/formulir/crankshaft-deflection';

type CrankshaftMeasurement = {
    cylinder: number;
    pos_a: string;
    pos_b: string;
    pos_c: string;
    pos_d: string;
    pos_e: string;
};

type SignatoryOption = {
    id: number;
    name: string;
    nip: string | null;
    position: string | null;
    has_signature: boolean;
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

type MachineItem = {
    id: number;
    name: string;
    type: string | null;
    serial_number: string | null;
    capacity_kw: string | null;
};

type Props = {
    unit: {
        id: number;
        name: string;
        service_unit_id: number | null;
        service_unit_name?: string | null;
    };
    units: Array<{ id: number; name: string }>;
    machines: MachineItem[];
    selected_machine_id?: number | null;
    selected_test_date: string;
    record: {
        id: number;
        test_date: string;
        format: string;
        content_html?: string | null;
        cylinders_count?: number;
    } | null;
    form_data: {
        test_date: string;
        test_date_raw: string;
        document_number: string;
        revision: string;
        effective_date: string;
        brand: string;
        model_type: string;
        serial_number: string;
        machine_number: string;
        installed_power: string;
        capable_power: string;
        rpm: string;
        cylinders_count: number;
        standard_min: string;
        standard_max: string;
        standard_allowed: string;
        visual_inspection: string;
        cylinder_notes: string;
        measurements: CrankshaftMeasurement[];
        manager_ul_id?: number | null;
        manager_ul_name?: string | null;
        manager_ul_title?: string | null;
        tl_har_id?: number | null;
        tl_har_name?: string | null;
        tl_har_title?: string | null;
        staff_har_id?: number | null;
        staff_har_name?: string | null;
        staff_har_title?: string | null;
        page_margin_top?: number;
        page_margin_bottom?: number;
        page_margin_left?: number;
        page_margin_right?: number;
        line_spacing?: string;
    };
    rendered_html: string;
    manager_options: SignatoryOption[];
    tl_options: SignatoryOption[];
    staff_options: SignatoryOption[];
    history: HistoryItem[];
    pdf_url: string;
    can_write: boolean;
};

export default function CrankshaftDeflectionIndex({
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
        form_data.document_number || 'FMKD-314-10.3.3.a-B2'
    );
    const [revision, setRevision] = useState<string>(form_data.revision || '02');
    const [effectiveDate, setEffectiveDate] = useState<string>(
        form_data.effective_date || '31 Juli 2024'
    );

    // Technical Specs
    const [brand, setBrand] = useState<string>(form_data.brand || 'MAK');
    const [modelType, setModelType] = useState<string>(
        form_data.model_type || '8M 453 AK'
    );
    const [serialNumber, setSerialNumber] = useState<string>(
        form_data.serial_number || ''
    );
    const [machineNumber, setMachineNumber] = useState<string>(
        form_data.machine_number || '1'
    );
    const [installedPower, setInstalledPower] = useState<string>(
        form_data.installed_power || '2544'
    );
    const [capablePower, setCapablePower] = useState<string>(
        form_data.capable_power || ''
    );
    const [rpm, setRpm] = useState<string>(form_data.rpm || '600');

    // Cylinder measurements
    const [cylindersCount, setCylindersCount] = useState<number>(
        form_data.cylinders_count || 8
    );
    const [measurements, setMeasurements] = useState<CrankshaftMeasurement[]>(
        () => {
            const initial = form_data.measurements || [];
            if (initial.length === 0) {
                return Array.from({ length: 8 }, (_, i) => ({
                    cylinder: i + 1,
                    pos_a: '0',
                    pos_b: '0',
                    pos_c: '0',
                    pos_d: '0',
                    pos_e: '0',
                }));
            }
            return initial;
        }
    );
    const [cylinderNotes, setCylinderNotes] = useState<string>(
        form_data.cylinder_notes || ''
    );
    const [standardMin, setStandardMin] = useState<string>(
        form_data.standard_min || '-0.06'
    );
    const [standardMax, setStandardMax] = useState<string>(
        form_data.standard_max || '+0.08'
    );
    const [standardAllowed, setStandardAllowed] = useState<string>(
        form_data.standard_allowed || 'Min : -0.06, Max : +0.08'
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
        form_data.manager_ul_title || 'PH.Manager Unit PLTD Wua-wua'
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

    // Selected Signatory helpers
    const selectedManagerUl = useMemo(
        () => manager_options.find((e) => String(e.id) === managerUlId),
        [manager_options, managerUlId]
    );
    const selectedTl = useMemo(
        () => tl_options.find((e) => String(e.id) === tlHarId),
        [tl_options, tlHarId]
    );
    const selectedStaff = useMemo(
        () => staff_options.find((e) => String(e.id) === staffHarId),
        [staff_options, staffHarId]
    );

    // Page Settings
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
        form_data.line_spacing ?? '1.15'
    );

    // HTML text editor content
    const [contentHtml, setContentHtml] = useState<string>(rendered_html);

    // Selected machine object
    const currentMachine = useMemo(
        () => machines.find((m) => m.id === machineId),
        [machines, machineId]
    );

    // Update cylinder count dynamically
    const handleCylinderCountChange = (count: number) => {
        if (count < 1 || count > 32) return;
        setCylindersCount(count);

        setMeasurements((prev) => {
            const newMeasurements: CrankshaftMeasurement[] = [];
            const prevMap = new Map(prev.map((m) => [m.cylinder, m]));

            for (let i = 1; i <= count; i++) {
                if (prevMap.has(i)) {
                    newMeasurements.push(prevMap.get(i)!);
                } else {
                    newMeasurements.push({
                        cylinder: i,
                        pos_a: '0',
                        pos_b: '0',
                        pos_c: '0',
                        pos_d: '0',
                        pos_e: '0',
                    });
                }
            }
            return newMeasurements;
        });
    };

    // Update single cell in measurement matrix
    const handleMeasurementChange = (
        cylinder: number,
        field: keyof Omit<CrankshaftMeasurement, 'cylinder'>,
        val: string
    ) => {
        setMeasurements((prev) =>
            prev.map((m) => (m.cylinder === cylinder ? { ...m, [field]: val } : m))
        );
    };

    // Pre-fill with scanned document sample values
    const handleFillSample = () => {
        setCylindersCount(8);
        setBrand('MAK');
        setModelType('8M 453 AK');
        setMachineNumber('1');
        setInstalledPower('2544');
        setCapablePower('');
        setRpm('600');
        setStandardMin('-0.06');
        setStandardMax('+0.08');
        setStandardAllowed('Min : -0.06, Max : +0.08');
        setVisualInspection(
            'Pemeriksaan visual pada permukaan crankshaft, web, dan main journal dalam kondisi baik, tidak ada keretakan atau keausan abnormal.'
        );
        setCylinderNotes(
            'Catatan: Pengukuran dilakukan pada posisi dial meter terpasang di antara web crankshaft saat kondisi mesin dingin sesuai manual pabrik.'
        );

        const sampleVals: Record<number, { a: string; b: string; c: string; d: string; e: string }> = {
            1: { a: '0', b: '-0,01', c: '-0,03', d: '-0,015', e: '-0,01' },
            2: { a: '0', b: '0', c: '-0,02', d: '-0,01', e: '-0,01' },
            3: { a: '0', b: '0,01', c: '0,005', d: '0,01', e: '0,01' },
            4: { a: '0', b: '0,01', c: '0,015', d: '0,015', e: '0,01' },
            5: { a: '0', b: '0,01', c: '0,02', d: '0,01', e: '0,005' },
            6: { a: '0', b: '0,005', c: '0,01', d: '0,01', e: '0,01' },
            7: { a: '0', b: '0', c: '0', d: '0', e: '0,005' },
            8: { a: '0', b: '-0,015', c: '-0,025', d: '-0,015', e: '-0,01' },
        };

        setMeasurements(
            Array.from({ length: 8 }, (_, idx) => {
                const cyl = idx + 1;
                const s = sampleVals[cyl];
                return {
                    cylinder: cyl,
                    pos_a: s ? s.a : '0',
                    pos_b: s ? s.b : '0',
                    pos_c: s ? s.c : '0',
                    pos_d: s ? s.d : '0',
                    pos_e: s ? s.e : '0',
                };
            })
        );
    };

    // Reset all measurements to 0
    const handleResetMeasurements = () => {
        setMeasurements((prev) =>
            prev.map((m) => ({
                cylinder: m.cylinder,
                pos_a: '0',
                pos_b: '0',
                pos_c: '0',
                pos_d: '0',
                pos_e: '0',
            }))
        );
    };

    // Switch active machine
    const handleMachineChange = (mIdStr: string) => {
        const newMachineId = parseInt(mIdStr, 10);
        setMachineId(newMachineId);
        const mObj = machines.find((m) => m.id === newMachineId);
        if (mObj) {
            setModelType(mObj.type || '8M 453 AK');
            setSerialNumber(mObj.serial_number || '');
            setInstalledPower(mObj.capacity_kw || '2544');
            setMachineNumber(strReplaceMachineName(mObj.name));
        }

        router.get(
            crankshaftRoutes.index({
                query: {
                    unit_id: unit.id,
                    machine_id: newMachineId,
                    test_date: testDate,
                },
            }),
            {},
            { preserveState: true }
        );
    };

    // Switch unit
    const handleUnitChange = (uIdStr: string) => {
        router.get(
            crankshaftRoutes.index({
                query: {
                    unit_id: parseInt(uIdStr, 10),
                    test_date: testDate,
                },
            })
        );
    };

    // Switch date
    const handleDateChange = (newDate: string) => {
        setTestDate(newDate);
        router.get(
            crankshaftRoutes.index({
                query: {
                    unit_id: unit.id,
                    machine_id: machineId,
                    test_date: newDate,
                },
            }),
            {},
            { preserveState: true }
        );
    };

    const strReplaceMachineName = (name: string): string => {
        return name.replace(/MIRRLEES\s*#/i, '').trim();
    };

    // Signatory handlers
    const handleManagerSelect = (empIdStr: string) => {
        setManagerUlId(empIdStr);
        const emp = manager_options.find((e) => String(e.id) === empIdStr);
        if (emp) {
            setManagerUlName(emp.name);
            setManagerUlTitle(emp.position || `Manager ${unit.service_unit_name || unit.name}`);
        }
    };

    const handleTlSelect = (empIdStr: string) => {
        setTlHarId(empIdStr);
        const emp = tl_options.find((e) => String(e.id) === empIdStr);
        if (emp) {
            setTlHarName(emp.name);
            setTlHarTitle(emp.position || 'Team Leader Pemeliharaan');
        }
    };

    const handleStaffSelect = (empIdStr: string) => {
        setStaffHarId(empIdStr);
        const emp = staff_options.find((e) => String(e.id) === empIdStr);
        if (emp) {
            setStaffHarName(emp.name);
            setStaffHarTitle(emp.position || 'Staff Pemeliharaan');
        }
    };

    // Save action
    const handleSave = () => {
        if (!can_write) return;
        setIsSaving(true);

        router.post(
            crankshaftRoutes.store.url(),
            {
                unit_id: unit.id,
                machine_id: machineId,
                test_date: testDate,
                document_number: docNumber,
                revision,
                effective_date: effectiveDate,
                brand,
                model_type: modelType,
                serial_number: serialNumber,
                machine_number: machineNumber,
                installed_power: installedPower,
                capable_power: capablePower,
                rpm,
                cylinders_count: cylindersCount,
                standard_min: standardMin,
                standard_max: standardMax,
                standard_allowed: standardAllowed,
                visual_inspection: visualInspection,
                cylinder_notes: cylinderNotes,
                measurements,
                manager_ul_id: managerUlId ? parseInt(managerUlId, 10) : null,
                manager_ul_name: managerUlName,
                manager_ul_title: managerUlTitle,
                tl_har_id: tlHarId ? parseInt(tlHarId, 10) : null,
                tl_har_name: tlHarName,
                tl_har_title: tlHarTitle,
                staff_har_id: staffHarId ? parseInt(staffHarId, 10) : null,
                staff_har_name: staffHarName,
                staff_har_title: staffHarTitle,
                page_margin_top: marginTop,
                page_margin_bottom: marginBottom,
                page_margin_left: marginLeft,
                page_margin_right: marginRight,
                line_spacing: lineSpacing,
                format: viewTab === 'html' ? 'html' : 'form',
                content_html: viewTab === 'html' ? contentHtml : null,
            },
            {
                preserveScroll: true,
                onFinish: () => {
                    setIsSaving(false);
                    setPreviewKey((k) => k + 1);
                },
            }
        );
    };

    // Download PDF directly
    const handleDownloadPdf = () => {
        const url = new URL(pdf_url, window.location.origin);
        url.searchParams.set('download', '1');
        url.searchParams.set('cylinders_count', String(cylindersCount));
        url.searchParams.set('standard_min', standardMin);
        url.searchParams.set('standard_max', standardMax);
        url.searchParams.set('page_margin_top', String(marginTop));
        url.searchParams.set('page_margin_bottom', String(marginBottom));
        url.searchParams.set('page_margin_left', String(marginLeft));
        url.searchParams.set('page_margin_right', String(marginRight));
        url.searchParams.set('line_spacing', lineSpacing);
        window.open(url.toString(), '_blank');
    };

    // Live URL for PDF Preview iframe
    const previewPdfUrl = useMemo(() => {
        const url = new URL(pdf_url, window.location.origin);
        url.searchParams.set('cylinders_count', String(cylindersCount));
        url.searchParams.set('standard_min', standardMin);
        url.searchParams.set('standard_max', standardMax);
        url.searchParams.set('page_margin_top', String(marginTop));
        url.searchParams.set('page_margin_bottom', String(marginBottom));
        url.searchParams.set('page_margin_left', String(marginLeft));
        url.searchParams.set('page_margin_right', String(marginRight));
        url.searchParams.set('line_spacing', lineSpacing);
        return url.toString();
    }, [
        pdf_url,
        cylindersCount,
        standardMin,
        standardMax,
        marginTop,
        marginBottom,
        marginLeft,
        marginRight,
        lineSpacing,
    ]);

    return (
        <>
            <Head title={`Formulir Pengukuran Defleksi Crankshaft - ${unit.name}`} />

            <div className="flex flex-col gap-6 p-4 md:p-6 w-full max-w-[1600px] mx-auto">
                {/* Header */}
                <PageHeader
                    title="Formulir Pengukuran Defleksi Crankshaft"
                    description={`Pengukuran kelurusan (alignment) dan defleksi poros engkol tiap silinder mesin (${unit.name}).`}
                    actions={
                        <div className="flex flex-wrap items-center gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => router.get(harFormulir.index())}
                                className="h-9 gap-1.5"
                            >
                                <ArrowLeft className="size-4" />
                                <span>Kembali</span>
                            </Button>

                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setShowHistory(true)}
                                className="h-9 gap-1.5"
                            >
                                <History className="size-4" />
                                <span>Riwayat ({history.length})</span>
                            </Button>

                            <Button
                                variant="outline"
                                size="sm"
                                onClick={handleDownloadPdf}
                                className="h-9 gap-1.5"
                            >
                                <Download className="size-4" />
                                <span>Unduh PDF</span>
                            </Button>

                            <Button
                                size="sm"
                                onClick={handleSave}
                                disabled={isSaving || !can_write}
                                className="h-9 gap-1.5 bg-primary text-primary-foreground hover:bg-primary/90 shadow-sm"
                            >
                                <Save className="size-4" />
                                <span>{isSaving ? 'Menyimpan...' : 'Simpan Formulir'}</span>
                            </Button>
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
                                        <div className="rounded-md border border-input bg-muted/40 px-3 py-1.5 font-medium text-xs">
                                            {unit.name}
                                        </div>
                                    )}
                                </div>

                                {/* Machine Selector */}
                                <div className="space-y-1">
                                    <Label className="text-xs">Mesin Pembangkit</Label>
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

                                {/* Tanggal Pengujian */}
                                <div className="space-y-1">
                                    <Label className="text-xs">Tanggal Pengukuran (TGL)</Label>
                                    <Input
                                        type="date"
                                        value={testDate}
                                        onChange={(e) => handleDateChange(e.target.value)}
                                        className="h-8 text-xs"
                                    />
                                </div>

                                {/* Metadata Dokumen Resmi PLN */}
                                <div className="grid grid-cols-2 gap-2 pt-1 border-t border-border/60">
                                    <div className="space-y-1">
                                        <Label className="text-[11px] text-muted-foreground">
                                            No. Dokumen
                                        </Label>
                                        <Input
                                            value={docNumber}
                                            onChange={(e) => setDocNumber(e.target.value)}
                                            className="h-7 text-xs font-mono"
                                        />
                                    </div>
                                    <div className="space-y-1">
                                        <Label className="text-[11px] text-muted-foreground">
                                            Revisi
                                        </Label>
                                        <Input
                                            value={revision}
                                            onChange={(e) => setRevision(e.target.value)}
                                            className="h-7 text-xs font-mono"
                                        />
                                    </div>
                                </div>

                                <div className="space-y-1">
                                    <Label className="text-[11px] text-muted-foreground">
                                        Tanggal Efektif
                                    </Label>
                                    <Input
                                        value={effectiveDate}
                                        onChange={(e) => setEffectiveDate(e.target.value)}
                                        className="h-7 text-xs"
                                    />
                                </div>

                                {/* Technical Specs Section */}
                                <div className="pt-2 border-t border-border/60 space-y-2">
                                    <Label className="text-[11px] font-semibold text-foreground uppercase tracking-wider">
                                        Spesifikasi Teknis Mesin
                                    </Label>

                                    <div className="grid grid-cols-2 gap-2">
                                        <div className="space-y-1">
                                            <Label className="text-[11px]">Merek</Label>
                                            <Input
                                                value={brand}
                                                onChange={(e) => setBrand(e.target.value)}
                                                placeholder="Contoh: MAK"
                                                className="h-7 text-xs"
                                            />
                                        </div>
                                        <div className="space-y-1">
                                            <Label className="text-[11px]">Type</Label>
                                            <Input
                                                value={modelType}
                                                onChange={(e) => setModelType(e.target.value)}
                                                placeholder="Contoh: 8M 453 AK"
                                                className="h-7 text-xs"
                                            />
                                        </div>
                                    </div>

                                    <div className="grid grid-cols-2 gap-2">
                                        <div className="space-y-1">
                                            <Label className="text-[11px]">Daya Terpasang</Label>
                                            <Input
                                                value={installedPower}
                                                onChange={(e) => setInstalledPower(e.target.value)}
                                                placeholder="Contoh: 2544"
                                                className="h-7 text-xs"
                                            />
                                        </div>
                                        <div className="space-y-1">
                                            <Label className="text-[11px]">Daya Mampu</Label>
                                            <Input
                                                value={capablePower}
                                                onChange={(e) => setCapablePower(e.target.value)}
                                                placeholder="Contoh: —"
                                                className="h-7 text-xs"
                                            />
                                        </div>
                                    </div>

                                    <div className="grid grid-cols-3 gap-2">
                                        <div className="space-y-1">
                                            <Label className="text-[11px]">No. Seri</Label>
                                            <Input
                                                value={serialNumber}
                                                onChange={(e) => setSerialNumber(e.target.value)}
                                                placeholder="No Seri"
                                                className="h-7 text-xs"
                                            />
                                        </div>
                                        <div className="space-y-1">
                                            <Label className="text-[11px]">Mesin No</Label>
                                            <Input
                                                value={machineNumber}
                                                onChange={(e) => setMachineNumber(e.target.value)}
                                                placeholder="1"
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

                        {/* Card 2: Penandatangan Dokumen (3 Pihak) */}
                        <div className="rounded-lg border border-border bg-card p-4 space-y-4">
                            <div className="border-b border-border pb-2">
                                <h3 className="text-sm font-semibold text-foreground">
                                    Penandatangan Dokumen (3 Pihak)
                                </h3>
                                <p className="text-[12px] text-muted-foreground">
                                    Manager UL, TL Pemeliharaan, dan Staf Pelaksana.
                                </p>
                            </div>

                            <div className="space-y-3.5 text-xs">
                                {/* 1. Manager UL (Mengetahui - Kiri) */}
                                <div className="space-y-2 rounded-md border border-border/70 p-2.5 bg-muted/20">
                                    <div className="flex items-center justify-between">
                                        <Label className="font-semibold text-foreground">
                                            1. Mengetahui (Manager UL / PH)
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
                                        onValueChange={handleManagerSelect}
                                    >
                                        <SelectTrigger className="h-7 text-xs">
                                            <SelectValue placeholder="Pilih Manager..." />
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
                                        onValueChange={handleTlSelect}
                                    >
                                        <SelectTrigger className="h-7 text-xs">
                                            <SelectValue placeholder="Pilih Team Leader..." />
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

                                {/* 3. Staff Pemeliharaan (Pelaksana - Kanan) */}
                                <div className="space-y-2 rounded-md border border-border/70 p-2.5 bg-muted/20">
                                    <div className="flex items-center justify-between">
                                        <Label className="font-semibold text-foreground">
                                            3. Pelaksana (Staff Pemeliharaan)
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
                                        onValueChange={handleStaffSelect}
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
                                            className="h-7 text-xs text-center font-mono"
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
                                            className="h-7 text-xs text-center font-mono"
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
                                            className="h-7 text-xs text-center font-mono"
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
                                            className="h-7 text-xs text-center font-mono"
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
                                            <SelectItem value="1.25">1,25 (Sedang)</SelectItem>
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
                            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-border px-4 py-3 bg-muted/20">
                                <div className="flex items-center gap-2">
                                    <Sliders className="size-4 text-primary" />
                                    <span className="text-sm font-semibold text-foreground">
                                        Lembar Kerja Pengukuran Defleksi Crankshaft
                                    </span>
                                </div>

                                {/* Tabs switch */}
                                <div className="flex items-center rounded-md border border-border bg-background p-0.5 text-xs">
                                    <button
                                        type="button"
                                        onClick={() => setViewTab('form')}
                                        className={`flex items-center gap-1.5 px-3 py-1.5 rounded-sm font-medium transition-colors ${
                                            viewTab === 'form'
                                                ? 'bg-primary text-primary-foreground shadow-xs'
                                                : 'text-muted-foreground hover:text-foreground'
                                        }`}
                                    >
                                        <SlidersHorizontal className="size-3.5" />
                                        <span>Editor Formulir</span>
                                    </button>

                                    <button
                                        type="button"
                                        onClick={() => setViewTab('html')}
                                        className={`flex items-center gap-1.5 px-3 py-1.5 rounded-sm font-medium transition-colors ${
                                            viewTab === 'html'
                                                ? 'bg-primary text-primary-foreground shadow-xs'
                                                : 'text-muted-foreground hover:text-foreground'
                                        }`}
                                    >
                                        <FileCode2 className="size-3.5" />
                                        <span>Editor Teks (HTML)</span>
                                    </button>

                                    <button
                                        type="button"
                                        onClick={() => setViewTab('pdf')}
                                        className={`flex items-center gap-1.5 px-3 py-1.5 rounded-sm font-medium transition-colors ${
                                            viewTab === 'pdf'
                                                ? 'bg-primary text-primary-foreground shadow-xs'
                                                : 'text-muted-foreground hover:text-foreground'
                                        }`}
                                    >
                                        <Eye className="size-3.5" />
                                        <span>Pratinjau PDF</span>
                                    </button>
                                </div>
                            </div>

                            {/* TAB 1: FORM EDITOR */}
                            {viewTab === 'form' && (
                                <div className="p-4 space-y-5">
                                    {/* TOOLBAR CYLINDER COUNT & QUICK BUTTONS */}
                                    <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-border bg-muted/30 p-3">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="text-xs font-semibold text-foreground">
                                                Jumlah Silinder:
                                            </span>
                                            {[6, 8, 12, 16].map((num) => (
                                                <Button
                                                    key={num}
                                                    type="button"
                                                    size="sm"
                                                    variant={cylindersCount === num ? 'default' : 'outline'}
                                                    onClick={() => handleCylinderCountChange(num)}
                                                    className="h-7 px-2.5 text-xs font-medium"
                                                >
                                                    {num} Cyl
                                                </Button>
                                            ))}
                                            <div className="flex items-center gap-1 ml-1">
                                                <span className="text-[11px] text-muted-foreground">Kustom:</span>
                                                <Input
                                                    type="number"
                                                    min={1}
                                                    max={32}
                                                    value={cylindersCount}
                                                    onChange={(e) => handleCylinderCountChange(Number(e.target.value))}
                                                    className="h-7 w-16 text-center text-xs font-mono"
                                                />
                                            </div>
                                        </div>

                                        <div className="flex items-center gap-2">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={handleFillSample}
                                                className="h-7 gap-1 text-xs text-primary border-primary/30 hover:bg-primary/5"
                                            >
                                                <Sparkles className="size-3.5" />
                                                <span>Isi Contoh Scan</span>
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={handleResetMeasurements}
                                                className="h-7 gap-1 text-xs text-muted-foreground hover:text-foreground"
                                            >
                                                <RotateCcw className="size-3.5" />
                                                <span>Kosongkan</span>
                                            </Button>
                                        </div>
                                    </div>

                                    {/* SKETSA POROS ENGKOL & LINGKARAN POSISI */}
                                    <div className="rounded-lg border border-border bg-card p-3 shadow-xs">
                                        <div className="flex items-center justify-between pb-2 border-b border-border/60">
                                            <span className="text-xs font-semibold text-foreground">
                                                Sketsa Posisi Pengukuran Defleksi Poros Engkol (Dial Meter)
                                            </span>
                                            <span className="text-[11px] font-medium text-muted-foreground">
                                                Note: Lihat Buku Petunjuk Pabrik Untuk Lebih Detail
                                            </span>
                                        </div>

                                        <div className="overflow-x-auto py-2">
                                            <svg
                                                width="540"
                                                height="86"
                                                viewBox="0 0 540 86"
                                                xmlns="http://www.w3.org/2000/svg"
                                                className="mx-auto block text-foreground"
                                            >
                                                {/* Label Contoh */}
                                                <text x="5" y="22" fontSize="10" fontWeight="bold" fill="currentColor">
                                                    contoh :
                                                </text>

                                                {/* Crankshaft throw schematic */}
                                                <g transform="translate(110, 5)">
                                                    {/* Left Main Journal */}
                                                    <path d="M 0,42 L 22,42 M 0,52 L 22,52" stroke="currentColor" strokeWidth="1.3" fill="none" />
                                                    <path d="M 0,42 C -3,44 -3,50 0,52" stroke="currentColor" strokeWidth="1.3" fill="none" />

                                                    {/* Left Crank Web */}
                                                    <path d="M 22,42 L 22,60 L 42,60 L 42,15 L 27,15 L 22,25 L 22,42" stroke="currentColor" strokeWidth="1.3" fill="none" />

                                                    {/* Crankpin */}
                                                    <path d="M 42,18 L 80,18 M 42,32 L 80,32" stroke="currentColor" strokeWidth="1.3" fill="none" />

                                                    {/* Right Crank Web */}
                                                    <path d="M 80,15 L 95,15 L 100,25 L 100,42 L 100,60 L 80,60 L 80,15" stroke="currentColor" strokeWidth="1.3" fill="none" />

                                                    {/* Right Main Journal */}
                                                    <path d="M 100,42 L 122,42 M 100,52 L 122,52" stroke="currentColor" strokeWidth="1.3" fill="none" />
                                                    <path d="M 122,42 C 125,44 125,50 122,52" stroke="currentColor" strokeWidth="1.3" fill="none" />

                                                    {/* Dial gauge indicator */}
                                                    <circle cx="61" cy="47" r="5" stroke="currentColor" strokeWidth="1.3" fill="#ffffff" />
                                                    <line x1="42" y1="47" x2="56" y2="47" stroke="currentColor" strokeWidth="1.2" />
                                                    <line x1="66" y1="47" x2="80" y2="47" stroke="currentColor" strokeWidth="1.2" />
                                                    <text x="61" y="62" fontSize="7" textAnchor="middle" fill="currentColor">
                                                        Defleksi dial meter
                                                    </text>

                                                    {/* Labels */}
                                                    <line x1="61" y1="18" x2="52" y2="5" stroke="currentColor" strokeWidth="0.8" />
                                                    <line x1="52" y1="5" x2="35" y2="5" stroke="currentColor" strokeWidth="0.8" />
                                                    <text x="32" y="4" fontSize="8" textAnchor="end" fill="currentColor">
                                                        Crankpin
                                                    </text>

                                                    <line x1="102" y1="47" x2="115" y2="30" stroke="currentColor" strokeWidth="0.8" />
                                                    <line x1="115" y1="30" x2="140" y2="30" stroke="currentColor" strokeWidth="0.8" />
                                                    <text x="142" y="32" fontSize="8" textAnchor="start" fill="currentColor">
                                                        Main-Journal
                                                    </text>
                                                </g>

                                                {/* Circular rotation position diagram */}
                                                <g transform="translate(360, 5)">
                                                    <circle cx="50" cy="40" r="28" stroke="currentColor" strokeWidth="1.3" fill="none" />
                                                    <line x1="50" y1="18" x2="50" y2="62" stroke="currentColor" strokeWidth="0.8" strokeDasharray="2,2" />
                                                    <line x1="28" y1="40" x2="72" y2="40" stroke="currentColor" strokeWidth="0.8" strokeDasharray="2,2" />
                                                    <path d="M 48,37 L 50,40 L 52,37" stroke="currentColor" strokeWidth="0.8" fill="none" />

                                                    {/* Clockwise curved arrow */}
                                                    <path d="M 80,40 A 32 32 0 0 1 60,71" stroke="currentColor" strokeWidth="1.3" fill="none" />
                                                    <polygon points="60,71 63,66 66,72" fill="currentColor" />

                                                    {/* Position Letters */}
                                                    <text x="73" y="18" fontSize="9.5" fontWeight="bold" fill="currentColor">A</text>
                                                    <text x="84" y="44" fontSize="9.5" fontWeight="bold" fill="currentColor">B</text>
                                                    <text x="50" y="79" fontSize="9.5" fontWeight="bold" textAnchor="middle" fill="currentColor">C</text>
                                                    <text x="14" y="44" fontSize="9.5" fontWeight="bold" fill="currentColor">D</text>
                                                    <text x="24" y="18" fontSize="9.5" fontWeight="bold" fill="currentColor">E</text>
                                                </g>
                                            </svg>
                                        </div>
                                    </div>

                                    {/* MATRIKS PENGUKURAN DEFLEKSI (POSISI A S.D. E PER SILINDER) */}
                                    <div className="space-y-2">
                                        <div className="flex items-center justify-between">
                                            <Label className="text-xs font-semibold text-foreground">
                                                Tabel Matriks Defleksi Crankshaft (Posisi A s.d. E)
                                            </Label>
                                            <span className="text-[11px] text-muted-foreground">
                                                Nilai dalam satuan milimeter (mm), toleransi standar Min {standardMin} s.d. Max {standardMax}
                                            </span>
                                        </div>

                                        <div className="overflow-x-auto rounded-lg border border-border">
                                            <table className="w-full border-collapse text-xs">
                                                <thead>
                                                    <tr className="bg-muted/40 border-b border-border">
                                                        <th className="p-2 text-left font-semibold text-foreground border-r border-border w-28">
                                                            Posisi / Cyl
                                                        </th>
                                                        {measurements.map((m) => (
                                                            <th
                                                                key={m.cylinder}
                                                                className="p-2 text-center font-semibold text-foreground border-r border-border min-w-[75px]"
                                                            >
                                                                Cyl {m.cylinder}
                                                            </th>
                                                        ))}
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {/* POSISI A */}
                                                    <tr className="border-b border-border/70 hover:bg-muted/10">
                                                        <td className="p-2 font-bold text-foreground border-r border-border bg-muted/20">
                                                            A <span className="font-normal text-[10px] text-muted-foreground">(TDC)</span>
                                                        </td>
                                                        {measurements.map((m) => (
                                                            <td key={`a-${m.cylinder}`} className="p-1 border-r border-border">
                                                                <Input
                                                                    value={m.pos_a}
                                                                    onChange={(e) =>
                                                                        handleMeasurementChange(m.cylinder, 'pos_a', e.target.value)
                                                                    }
                                                                    placeholder="0"
                                                                    className="h-7 text-xs text-center font-mono"
                                                                />
                                                            </td>
                                                        ))}
                                                    </tr>

                                                    {/* POSISI B */}
                                                    <tr className="border-b border-border/70 hover:bg-muted/10">
                                                        <td className="p-2 font-bold text-foreground border-r border-border bg-muted/20">
                                                            B <span className="font-normal text-[10px] text-muted-foreground">(90° Kanan)</span>
                                                        </td>
                                                        {measurements.map((m) => (
                                                            <td key={`b-${m.cylinder}`} className="p-1 border-r border-border">
                                                                <Input
                                                                    value={m.pos_b}
                                                                    onChange={(e) =>
                                                                        handleMeasurementChange(m.cylinder, 'pos_b', e.target.value)
                                                                    }
                                                                    placeholder="0"
                                                                    className="h-7 text-xs text-center font-mono"
                                                                />
                                                            </td>
                                                        ))}
                                                    </tr>

                                                    {/* POSISI C */}
                                                    <tr className="border-b border-border/70 hover:bg-muted/10">
                                                        <td className="p-2 font-bold text-foreground border-r border-border bg-muted/20">
                                                            C <span className="font-normal text-[10px] text-muted-foreground">(BDC Bawah)</span>
                                                        </td>
                                                        {measurements.map((m) => (
                                                            <td key={`c-${m.cylinder}`} className="p-1 border-r border-border">
                                                                <Input
                                                                    value={m.pos_c}
                                                                    onChange={(e) =>
                                                                        handleMeasurementChange(m.cylinder, 'pos_c', e.target.value)
                                                                    }
                                                                    placeholder="0"
                                                                    className="h-7 text-xs text-center font-mono"
                                                                />
                                                            </td>
                                                        ))}
                                                    </tr>

                                                    {/* POSISI D */}
                                                    <tr className="border-b border-border/70 hover:bg-muted/10">
                                                        <td className="p-2 font-bold text-foreground border-r border-border bg-muted/20">
                                                            D <span className="font-normal text-[10px] text-muted-foreground">(270° Kiri)</span>
                                                        </td>
                                                        {measurements.map((m) => (
                                                            <td key={`d-${m.cylinder}`} className="p-1 border-r border-border">
                                                                <Input
                                                                    value={m.pos_d}
                                                                    onChange={(e) =>
                                                                        handleMeasurementChange(m.cylinder, 'pos_d', e.target.value)
                                                                    }
                                                                    placeholder="0"
                                                                    className="h-7 text-xs text-center font-mono"
                                                                />
                                                            </td>
                                                        ))}
                                                    </tr>

                                                    {/* POSISI E */}
                                                    <tr className="border-b border-border/70 hover:bg-muted/10">
                                                        <td className="p-2 font-bold text-foreground border-r border-border bg-muted/20">
                                                            E <span className="font-normal text-[10px] text-muted-foreground">(Near TDC)</span>
                                                        </td>
                                                        {measurements.map((m) => (
                                                            <td key={`e-${m.cylinder}`} className="p-1 border-r border-border">
                                                                <Input
                                                                    value={m.pos_e}
                                                                    onChange={(e) =>
                                                                        handleMeasurementChange(m.cylinder, 'pos_e', e.target.value)
                                                                    }
                                                                    placeholder="0"
                                                                    className="h-7 text-xs text-center font-mono"
                                                                />
                                                            </td>
                                                        ))}
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>

                                    {/* SECTION: STANDAR YANG DIIZINKAN & KETERANGAN / CATATAN */}
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4 pt-1">
                                        <div className="space-y-2 rounded-md border border-border bg-card p-3.5 shadow-xs">
                                            <Label className="text-xs font-semibold text-foreground">
                                                Standar Yang di Izinkan :
                                            </Label>
                                            <div className="grid grid-cols-2 gap-3">
                                                <div className="space-y-1">
                                                    <Label className="text-[11px] text-muted-foreground">Min (mm)</Label>
                                                    <Input
                                                        value={standardMin}
                                                        onChange={(e) => setStandardMin(e.target.value)}
                                                        placeholder="-0.06"
                                                        className="h-7 text-xs font-mono"
                                                    />
                                                </div>
                                                <div className="space-y-1">
                                                    <Label className="text-[11px] text-muted-foreground">Max (mm)</Label>
                                                    <Input
                                                        value={standardMax}
                                                        onChange={(e) => setStandardMax(e.target.value)}
                                                        placeholder="+0.08"
                                                        className="h-7 text-xs font-mono"
                                                    />
                                                </div>
                                            </div>
                                            <p className="text-[11px] text-muted-foreground">
                                                Batas toleransi kelurusan web defleksi sesuai manual book mesin.
                                            </p>
                                        </div>

                                        <div className="space-y-1.5 rounded-md border border-border bg-card p-3.5 shadow-xs">
                                            <Label className="text-xs font-semibold text-foreground">
                                                Keterangan / Catatan Tambahan :
                                            </Label>
                                            <textarea
                                                rows={3}
                                                value={cylinderNotes}
                                                onChange={(e) => setCylinderNotes(e.target.value)}
                                                placeholder="Catatan hasil pengukuran defleksi, suhu mesin saat pengukuran, atau tindak lanjut..."
                                                className="w-full rounded-md border border-input bg-background p-2 text-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring resize-none"
                                            />
                                            <p className="text-[11px] text-muted-foreground">
                                                Ditampilkan pada kolom KETERANGAN di sisi kanan tabel cetak.
                                            </p>
                                        </div>
                                    </div>

                                    {/* SECTION: PEMERIKSAAN VISUAL */}
                                    <div className="space-y-1.5 rounded-md border border-border bg-card p-3.5 shadow-xs">
                                        <Label className="text-xs font-semibold text-foreground">
                                            Pemeriksaan visual :
                                        </Label>
                                        <textarea
                                            rows={2}
                                            value={visualInspection}
                                            onChange={(e) => setVisualInspection(e.target.value)}
                                            placeholder="Contoh: Kondisi permukaan crankpin dan web bersih tanpa keretakan atau goresan abnormal..."
                                            className="w-full rounded-md border border-input bg-background p-2 text-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring resize-none"
                                        />
                                        <p className="text-[11px] text-muted-foreground">
                                            Kondisi fisik permukaan poros engkol dan crankcase saat inspeksi.
                                        </p>
                                    </div>
                                </div>
                            )}

                            {/* TAB 2: EDITOR HTML (TINYMCE) */}
                            {viewTab === 'html' && (
                                <div className="p-4 space-y-3">
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <h4 className="font-semibold text-sm">Editor Teks &amp; Tata Letak Dokumen</h4>
                                            <p className="text-xs text-muted-foreground">
                                                Gunakan editor ini untuk penyesuaian teks manual sebelum diekspor.
                                            </p>
                                        </div>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={() => setContentHtml(rendered_html)}
                                            className="h-7 text-xs gap-1"
                                        >
                                            <RotateCcw className="size-3.5" />
                                            <span>Reset Template</span>
                                        </Button>
                                    </div>
                                    <RichTextEditor
                                        value={contentHtml}
                                        onChange={setContentHtml}
                                    />
                                </div>
                            )}

                            {/* TAB 3: PRATINJAU PDF (IFRAME) */}
                            {viewTab === 'pdf' && (
                                <div className="p-4 space-y-3">
                                    <div className="flex items-center justify-between text-xs text-muted-foreground">
                                        <span>
                                            Pratinjau ini identik dengan hasil cetak PDF A4 resmi PT PLN Nusantara Power.
                                        </span>
                                        <div className="flex items-center gap-2">
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() => setPreviewKey((k) => k + 1)}
                                                className="h-7 gap-1 text-xs"
                                            >
                                                <RotateCcw className="size-3.5" />
                                                <span>Segarkan Pratinjau</span>
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={handleDownloadPdf}
                                                className="h-7 gap-1 text-xs"
                                            >
                                                <Download className="size-3.5" />
                                                <span>Unduh PDF</span>
                                            </Button>
                                        </div>
                                    </div>

                                    <PdfPreviewFrame
                                        key={`${previewKey}-${cylindersCount}`}
                                        title="Pratinjau PDF Pengukuran Defleksi Crankshaft"
                                        src={`${previewPdfUrl}#view=FitH`}
                                        className="h-[750px] w-full rounded-md border border-border bg-white"
                                        style={{ height: '750px', minHeight: '750px', width: '100%' }}
                                    />
                                </div>
                            )}

                            {/* BOTTOM ACTION BAR */}
                            <div className="flex flex-wrap items-center justify-between gap-3 border-t border-border p-4 bg-card">
                                <div className="flex items-center gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => setShowHistory(true)}
                                        className="h-8 gap-1.5 text-xs"
                                    >
                                        <History className="size-3.5" />
                                        <span>Riwayat Formulir</span>
                                    </Button>

                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => router.get(harFormulir.index())}
                                        className="h-8 text-xs text-muted-foreground hover:text-foreground"
                                    >
                                        Batal
                                    </Button>
                                </div>

                                <div className="flex items-center gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={handleDownloadPdf}
                                        className="h-8 gap-1.5 text-xs"
                                    >
                                        <Printer className="size-3.5" />
                                        <span>Cetak / Unduh PDF</span>
                                    </Button>

                                    <Button
                                        size="sm"
                                        onClick={handleSave}
                                        disabled={isSaving || !can_write}
                                        className="h-8 gap-1.5 text-xs bg-primary text-primary-foreground hover:bg-primary/90"
                                    >
                                        <Save className="size-3.5" />
                                        <span>{isSaving ? 'Menyimpan...' : 'Simpan Perubahan'}</span>
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* History Modal */}
            <Dialog open={showHistory} onOpenChange={setShowHistory}>
                <DialogContent className="max-w-xl">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2 text-base">
                            <History className="size-4 text-primary" />
                            <span>Riwayat Pengukuran Defleksi Crankshaft</span>
                        </DialogTitle>
                        <DialogDescription className="text-xs">
                            Daftar formulir defleksi crankshaft yang tersimpan untuk unit {unit.name}.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="divide-y divide-border overflow-y-auto max-h-[350px]">
                        {history.length === 0 ? (
                            <div className="py-8 text-center text-xs text-muted-foreground">
                                Belum ada riwayat formulir tersimpan untuk mesin ini.
                            </div>
                        ) : (
                            history.map((h) => {
                                const hMachine = machines.find((m) => m.id === h.machine_id);
                                return (
                                    <div
                                        key={h.id}
                                        className="flex items-center justify-between py-2.5 text-xs hover:bg-muted/20 px-2 rounded-md"
                                    >
                                        <div className="space-y-0.5">
                                            <div className="font-semibold text-foreground">
                                                {hMachine?.name || `Mesin #${h.machine_id}`} — {h.test_date}
                                            </div>
                                            <div className="text-[11px] text-muted-foreground">
                                                {h.document_number} (Rev. {h.revision}) • Format: {h.format}
                                            </div>
                                        </div>

                                        <div className="flex items-center gap-1.5">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() => {
                                                    setShowHistory(false);
                                                    router.get(
                                                        crankshaftRoutes.index(),
                                                        {
                                                            unit_id: unit.id,
                                                            machine_id: h.machine_id,
                                                            test_date: h.test_date,
                                                            record_id: h.id,
                                                        },
                                                        { preserveState: false }
                                                    );
                                                }}
                                                className="h-7 text-xs"
                                            >
                                                Muat Data
                                            </Button>
                                        </div>
                                    </div>
                                );
                            })
                        )}
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}

CrankshaftDeflectionIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Formulir Pemeliharaan', href: harFormulir.index() },
        { title: 'Pengukuran Defleksi Crankshaft', href: crankshaftRoutes.index() },
    ],
};
