import { Head, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Download,
    Eye,
    FileCode2,
    Flame,
    History,
    Info,
    Printer,
    RotateCcw,
    Save,
    SlidersHorizontal,
    Sparkles,
} from 'lucide-react';
import { useMemo, useState } from 'react';
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
import { Textarea } from '@/components/ui/textarea';
import { useCompactLayout } from '@/hooks/use-mobile-module';
import { usePermissions } from '@/hooks/use-permissions';
import { dashboard } from '@/routes';
import harFormulir from '@/routes/har/formulir';
import combustionRoutes from '@/routes/har/formulir/combustion-pressure';

type CombustionMeasurement = {
    cylinder: number;
    combustion_pressure: string;
    exhaust_temp: string;
    rack_position: string;
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
        standard_allowed: string;
        visual_inspection: string;
        cylinder_notes: string;
        measurements: CombustionMeasurement[];
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

export default function CombustionPressureIndex({
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
        form_data.document_number || 'FMKD-314-10.3.3.a-B4'
    );
    const [revision, setRevision] = useState<string>(form_data.revision || '03');
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
        form_data.capable_power || '1300'
    );
    const [rpm, setRpm] = useState<string>(form_data.rpm || '600');

    // Cylinder measurements
    const [cylindersCount, setCylindersCount] = useState<number>(
        form_data.cylinders_count || 8
    );
    const [measurements, setMeasurements] = useState<CombustionMeasurement[]>(
        () => {
            const initial = form_data.measurements || [];

            if (initial.length === 0) {
                return Array.from({ length: 8 }, (_, i) => ({
                    cylinder: i + 1,
                    combustion_pressure: '',
                    exhaust_temp: '',
                    rack_position: '',
                }));
            }

            return initial;
        }
    );
    const [cylinderNotes, setCylinderNotes] = useState<string>(
        form_data.cylinder_notes || ''
    );
    const [standardAllowed, setStandardAllowed] = useState<string>(
        form_data.standard_allowed || 'Sesuai petunjuk pabrik / buku manual'
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
        form_data.tl_har_title || 'Team Leader Pemeliharaan PLTD Wua-wua'
    );

    const [staffHarId, setStaffHarId] = useState<string>(
        form_data.staff_har_id ? String(form_data.staff_har_id) : ''
    );
    const [staffHarName, setStaffHarName] = useState<string>(
        form_data.staff_har_name || ''
    );
    const [staffHarTitle, setStaffHarTitle] = useState<string>(
        form_data.staff_har_title || 'Staf Pemeliharaan'
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

    // Page layout settings
    const [marginTop, setMarginTop] = useState<number>(
        form_data.page_margin_top || 12
    );
    const [marginBottom, setMarginBottom] = useState<number>(
        form_data.page_margin_bottom || 12
    );
    const [marginLeft, setMarginLeft] = useState<number>(
        form_data.page_margin_left || 15
    );
    const [marginRight, setMarginRight] = useState<number>(
        form_data.page_margin_right || 15
    );
    const [lineSpacing, setLineSpacing] = useState<string>(
        form_data.line_spacing || '1.15'
    );

    // HTML Content for TinyMCE mode
    const [contentHtml, setContentHtml] = useState<string>(rendered_html);

    // Dynamic reactive PDF URL
    const previewPdfUrl = useMemo(() => {
        const params = new URLSearchParams({
            unit_id: String(unit.id),
            machine_id: String(machineId),
            test_date: testDate,
            cylinders_count: String(cylindersCount),
            page_margin_top: String(marginTop),
            page_margin_bottom: String(marginBottom),
            page_margin_left: String(marginLeft),
            page_margin_right: String(marginRight),
            line_spacing: lineSpacing,
            _k: String(previewKey),
        });

        if (record?.id) {
            params.set('record_id', String(record.id));
        }

        return `${combustionRoutes.pdf.url()}?${params.toString()}`;
    }, [
        unit.id,
        machineId,
        testDate,
        cylindersCount,
        marginTop,
        marginBottom,
        marginLeft,
        marginRight,
        lineSpacing,
        previewKey,
        record?.id,
    ]);

    // Handle cylinders count change
    const handleCylindersCountChange = (newCount: number) => {
        const validCount = Math.max(1, Math.min(32, newCount));
        setCylindersCount(validCount);
        setMeasurements((prev) => {
            const map = new Map(prev.map((it) => [it.cylinder, it]));

            return Array.from({ length: validCount }, (_, i) => {
                const cyl = i + 1;
                const existing = map.get(cyl);

                return {
                    cylinder: cyl,
                    combustion_pressure: existing?.combustion_pressure ?? '',
                    exhaust_temp: existing?.exhaust_temp ?? '',
                    rack_position: existing?.rack_position ?? '',
                };
            });
        });
        setPreviewKey((k) => k + 1);
    };

    // Update single cell in measurement matrix
    const handleMeasurementChange = (
        cylinder: number,
        field: 'combustion_pressure' | 'exhaust_temp' | 'rack_position',
        value: string
    ) => {
        setMeasurements((prev) =>
            prev.map((m) => (m.cylinder === cylinder ? { ...m, [field]: value } : m))
        );
    };

    // Fill sample values from scan
    const handleFillSample = () => {
        const samples: Record<number, { p: string; t: string; r: string }> = {
            1: { p: '80', t: '280', r: '30' },
            2: { p: '90', t: '370', r: '30' },
            3: { p: '85', t: '355', r: '25' },
            4: { p: '85', t: '360', r: '28' },
            5: { p: '85', t: '345', r: '27' },
            6: { p: '80', t: '320', r: '28' },
            7: { p: '85', t: '370', r: '29' },
            8: { p: '80', t: '340', r: '28' },
        };

        setMeasurements((prev) =>
            prev.map((m) => {
                const s = samples[m.cylinder];

                if (s) {
                    return {
                        cylinder: m.cylinder,
                        combustion_pressure: s.p,
                        exhaust_temp: s.t,
                        rack_position: s.r,
                    };
                }

                return m;
            })
        );
        setCylinderNotes('Pemeriksaan berkala 6000 Jam Operasi.');
        setStandardAllowed('Tekanan pembakaran max 90 kg/cm2, delta temp max 50°C');
        setVisualInspection('Kondisi fisik cylinder head bersih, tidak ada rembesan kompresi atau oli.');
    };

    // Clear all measurements
    const handleClearMeasurements = () => {
        setMeasurements((prev) =>
            prev.map((m) => ({
                cylinder: m.cylinder,
                combustion_pressure: '',
                exhaust_temp: '',
                rack_position: '',
            }))
        );
    };

    // Switch Machine handler
    const handleMachineChange = (idStr: string) => {
        const id = parseInt(idStr, 10);
        setMachineId(id);
        const sel = machines.find((m) => m.id === id);

        if (sel) {
            setModelType(sel.type || '8M 453 AK');
            setSerialNumber(sel.serial_number || '');
            setMachineNumber(sel.name.replace(/[^0-9]/g, '') || '1');
            setInstalledPower(sel.capacity_kw || '2544');
        }

        router.get(
            combustionRoutes.index.url(),
            {
                unit_id: unit.id,
                machine_id: id,
                test_date: testDate,
            },
            { preserveState: false, preserveScroll: true }
        );
    };

    // Switch Unit handler
    const handleUnitChange = (idStr: string) => {
        const id = parseInt(idStr, 10);
        router.get(
            combustionRoutes.index.url(),
            { unit_id: id },
            { preserveState: false }
        );
    };

    // Switch Test Date handler
    const handleDateChange = (newDate: string) => {
        setTestDate(newDate);
        router.get(
            combustionRoutes.index.url(),
            {
                unit_id: unit.id,
                machine_id: machineId,
                test_date: newDate,
            },
            { preserveState: false, preserveScroll: true }
        );
    };

    // Signatory select handlers
    const handleManagerSelect = (empIdStr: string) => {
        setManagerUlId(empIdStr);
        const emp = manager_options.find((e) => String(e.id) === empIdStr);

        if (emp) {
            setManagerUlName(emp.name);
            setManagerUlTitle(emp.position || `Manager UL ${unit.name}`);
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
            setStaffHarTitle(emp.position || 'Staf Pemeliharaan');
        }
    };

    // Save action
    const handleSave = () => {
        if (!can_write) {
return;
}

        setIsSaving(true);

        router.post(
            combustionRoutes.store.url(),
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
        const params = new URLSearchParams({
            unit_id: String(unit.id),
            machine_id: String(machineId),
            test_date: testDate,
            cylinders_count: String(cylindersCount),
            page_margin_top: String(marginTop),
            page_margin_bottom: String(marginBottom),
            page_margin_left: String(marginLeft),
            page_margin_right: String(marginRight),
            line_spacing: lineSpacing,
            download: '1',
        });

        if (record?.id) {
            params.set('record_id', String(record.id));
        }

        window.open(
            `${combustionRoutes.pdf.url()}?${params.toString()}`,
            '_blank'
        );
    };

    return (
        <>
            <Head title={`Pengukuran Tekanan Pembakaran - ${unit.name}`} />

            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                {/* STANDARDIZED PAGE HEADER */}
                <PageHeader
                    title="Formulir Pengukuran Tekanan Pembakaran"
                    description={`Pencatatan tekanan kompresi, temperatur gas buang, dan rack position tiap silinder (${unit.name}).`}
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

                                {/* Machine Selector */}
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

                                {/* Tanggal Pengukuran (TGL) */}
                                <div className="space-y-1">
                                    <Label className="text-xs">Tanggal Pengukuran (TGL)</Label>
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
                                        <Label className="text-[11px]">No. Dokumen</Label>
                                        <Input
                                            value={docNumber}
                                            onChange={(e) => setDocNumber(e.target.value)}
                                            className="h-8 text-xs font-mono"
                                        />
                                    </div>
                                    <div className="space-y-1">
                                        <Label className="text-[11px]">Revisi</Label>
                                        <Input
                                            value={revision}
                                            onChange={(e) => setRevision(e.target.value)}
                                            className="h-8 text-xs font-mono"
                                        />
                                    </div>
                                </div>

                                <div className="space-y-1">
                                    <Label className="text-[11px]">Tanggal Berlaku Dokumen</Label>
                                    <Input
                                        value={effectiveDate}
                                        onChange={(e) => setEffectiveDate(e.target.value)}
                                        className="h-8 text-xs"
                                    />
                                </div>

                                {/* Data Spesifikasi Teknis Mesin (Inside Card 1) */}
                                <div className="border-t border-border pt-3 space-y-2">
                                    <span className="text-[11px] font-semibold text-muted-foreground uppercase tracking-wider">
                                        Spesifikasi Mesin
                                    </span>
                                    <div className="grid grid-cols-2 gap-2">
                                        <div className="space-y-1">
                                            <Label className="text-[11px]">Merek Mesin</Label>
                                            <Input
                                                value={brand}
                                                onChange={(e) => setBrand(e.target.value)}
                                                placeholder="contoh: MAK"
                                                className="h-7 text-xs"
                                            />
                                        </div>
                                        <div className="space-y-1">
                                            <Label className="text-[11px]">Tipe Mesin</Label>
                                            <Input
                                                value={modelType}
                                                onChange={(e) => setModelType(e.target.value)}
                                                placeholder="contoh: 8M 453 AK"
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
                                                placeholder="contoh: DL 62870368"
                                                className="h-7 text-xs font-mono"
                                            />
                                        </div>
                                        <div className="space-y-1">
                                            <Label className="text-[11px]">Mesin No.</Label>
                                            <Input
                                                value={machineNumber}
                                                onChange={(e) => setMachineNumber(e.target.value)}
                                                placeholder="contoh: 1"
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
                                                placeholder="kW"
                                                className="h-7 text-xs"
                                            />
                                        </div>
                                        <div className="space-y-1">
                                            <Label className="text-[11px]">Daya Mampu</Label>
                                            <Input
                                                value={capablePower}
                                                onChange={(e) => setCapablePower(e.target.value)}
                                                placeholder="kW"
                                                className="h-7 text-xs"
                                            />
                                        </div>
                                        <div className="space-y-1">
                                            <Label className="text-[11px]">RPM Mesin</Label>
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
                                        onValueChange={handleManagerSelect}
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

                                {/* 3. Staff Pemeliharaan (Pelaksana - Kanan) */}
                                <div className="space-y-2 rounded-md border border-border/70 p-2.5 bg-muted/20">
                                    <div className="flex items-center justify-between">
                                        <Label className="font-semibold text-foreground">
                                            3. Pelaksana (Staf Pemeliharaan)
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
                                        onClick={() => {
                                            setViewTab('pdf');
                                            setPreviewKey((k) => k + 1);
                                        }}
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

                            {/* TAB 1: FORMULIR INPUT */}
                            {viewTab === 'form' && (
                                <div className="p-4 space-y-4">
                                    {/* CYLINDER SELECTION TOOLBAR */}
                                    <div className="flex flex-wrap items-center justify-between gap-3 rounded-md border border-border bg-muted/20 p-2.5 text-xs">
                                        <div className="flex items-center gap-2">
                                            <Label className="text-xs font-semibold text-foreground">
                                                Jumlah Silinder:
                                            </Label>
                                            <div className="flex items-center gap-1">
                                                {[6, 8, 12, 16].map((cnt) => (
                                                    <Button
                                                        key={cnt}
                                                        type="button"
                                                        variant={cylindersCount === cnt ? 'default' : 'outline'}
                                                        size="sm"
                                                        onClick={() => handleCylindersCountChange(cnt)}
                                                        className="h-7 px-2.5 text-xs font-mono"
                                                    >
                                                        {cnt}
                                                    </Button>
                                                ))}
                                                <div className="flex items-center gap-1 ml-1.5">
                                                    <Input
                                                        type="number"
                                                        min={1}
                                                        max={32}
                                                        value={cylindersCount}
                                                        onChange={(e) =>
                                                            handleCylindersCountChange(
                                                                parseInt(e.target.value, 10) || 1
                                                            )
                                                        }
                                                        className="h-7 w-16 text-center text-xs font-mono"
                                                    />
                                                    <span className="text-[11px] text-muted-foreground">Cyl</span>
                                                </div>
                                            </div>
                                        </div>

                                        <div className="flex items-center gap-1.5">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={handleFillSample}
                                                className="h-7 gap-1 text-[11px] text-primary hover:text-primary hover:bg-primary/10"
                                            >
                                                <Sparkles className="size-3" />
                                                <span>Isi Contoh Scan</span>
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                onClick={handleClearMeasurements}
                                                className="h-7 text-[11px] text-muted-foreground hover:text-destructive"
                                            >
                                                Kosongkan
                                            </Button>
                                        </div>
                                    </div>

                                    {/* SCAN INSTRUCTION NOTICE */}
                                    <div className="flex items-start justify-between text-xs text-muted-foreground px-1">
                                        <div className="flex items-center gap-1.5 font-medium text-foreground">
                                            <Flame className="size-4 text-orange-500" />
                                            <span>Matriks Pengukuran Tekanan Pembakaran &amp; Suhu Gas Buang</span>
                                        </div>
                                        <span className="italic text-[11px]">
                                            Note: Lihat Buku Petunjuk Pabrik Untuk Lebih Detail
                                        </span>
                                    </div>

                                    {/* HORIZONTAL MATRIX TABLE MATCHING PHYSICAL SCAN */}
                                    {compact ? (
<div className="flex flex-col gap-3">
    <MobileRowEditor
        rows={measurements}
        canWrite
        rowKey={(m) => m.cylinder}
        title={(m) => `Silinder ${m.cylinder}`}
        subtitle={(m) => `${m.combustion_pressure || '–'} kg/cm² · ${m.exhaust_temp || '–'} °C · rack ${m.rack_position || '–'}`}
        onChange={(index, key, value) => handleMeasurementChange(measurements[index].cylinder, key as 'combustion_pressure', String(value ?? ''))}
        fields={[
            { key: 'combustion_pressure', label: 'Tekanan pembakaran (kg/cm²)', placeholder: '80', parse: (value) => String(value) },
            { key: 'exhaust_temp', label: 'Temperatur gas buang (°C)', placeholder: '350', parse: (value) => String(value) },
            { key: 'rack_position', label: 'Rack injection pump', placeholder: '28', parse: (value) => String(value) },
        ]}
    />
    <label className="flex flex-col gap-1.5 text-[12px] text-muted-foreground">
        Keterangan
        <Textarea value={cylinderNotes} onChange={(e) => setCylinderNotes(e.target.value)} rows={3} placeholder="Catatan umum kondisi silinder, kelainan pembakaran, atau rekomendasi tindakan…" />
    </label>
</div>
                                    ) : (
                                    <div className="overflow-x-auto rounded-md border border-border bg-card shadow-sm">
                                        <table className="w-full text-xs border-collapse">
                                            <thead>
                                                <tr className="border-b border-border bg-muted/50 font-bold">
                                                    <th className="p-2.5 text-left border-r border-border min-w-[190px] w-[25%] text-foreground">
                                                        Cylinder
                                                    </th>
                                                    {measurements.map((m) => (
                                                        <th
                                                            key={m.cylinder}
                                                            className="p-2 text-center border-r border-border min-w-[60px] font-mono text-foreground bg-muted/30"
                                                        >
                                                            {m.cylinder}
                                                        </th>
                                                    ))}
                                                    <th className="p-2.5 text-center min-w-[180px] w-[25%] text-foreground">
                                                        KETERANGAN
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {/* BARIS 1: TEKANAN PEMBAKARAN (kg/cm²) */}
                                                <tr className="border-b border-border hover:bg-muted/10">
                                                    <td className="p-2.5 border-r border-border font-semibold text-foreground bg-muted/10">
                                                        <div className="flex flex-col">
                                                            <span>Tekanan pembakaran</span>
                                                            <span className="text-[10px] text-muted-foreground font-normal">
                                                                (kg / cm²)
                                                            </span>
                                                        </div>
                                                    </td>
                                                    {measurements.map((m) => (
                                                        <td key={m.cylinder} className="p-1 border-r border-border text-center">
                                                            <Input
                                                                type="text"
                                                                value={m.combustion_pressure}
                                                                onChange={(e) =>
                                                                    handleMeasurementChange(
                                                                        m.cylinder,
                                                                        'combustion_pressure',
                                                                        e.target.value
                                                                    )
                                                                }
                                                                placeholder="80"
                                                                className="h-8 w-full text-center font-mono text-xs focus-visible:ring-1"
                                                            />
                                                        </td>
                                                    ))}
                                                    {/* KETERANGAN SPANS ACROSS 3 ROWS */}
                                                    <td rowSpan={3} className="p-2 align-top bg-muted/5">
                                                        <textarea
                                                            value={cylinderNotes}
                                                            onChange={(e) => setCylinderNotes(e.target.value)}
                                                            placeholder="Catatan umum kondisi silinder, kelainan pembakaran, atau rekomendasi tindakan..."
                                                            className="h-full min-h-[110px] w-full rounded-md border border-input bg-background p-2 text-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring resize-y"
                                                        />
                                                    </td>
                                                </tr>

                                                {/* BARIS 2: TEMPRATURE GAS BUANG (°C) */}
                                                <tr className="border-b border-border hover:bg-muted/10">
                                                    <td className="p-2.5 border-r border-border font-semibold text-foreground bg-muted/10">
                                                        <div className="flex flex-col">
                                                            <span>Temprature gas buang</span>
                                                            <span className="text-[10px] text-muted-foreground font-normal">
                                                                (°C)
                                                            </span>
                                                        </div>
                                                    </td>
                                                    {measurements.map((m) => (
                                                        <td key={m.cylinder} className="p-1 border-r border-border text-center">
                                                            <Input
                                                                type="text"
                                                                value={m.exhaust_temp}
                                                                onChange={(e) =>
                                                                    handleMeasurementChange(
                                                                        m.cylinder,
                                                                        'exhaust_temp',
                                                                        e.target.value
                                                                    )
                                                                }
                                                                placeholder="350"
                                                                className="h-8 w-full text-center font-mono text-xs focus-visible:ring-1"
                                                            />
                                                        </td>
                                                    ))}
                                                </tr>

                                                {/* BARIS 3: RACK INJECTION PUMP */}
                                                <tr className="border-b border-border hover:bg-muted/10">
                                                    <td className="p-2.5 border-r border-border font-semibold text-foreground bg-muted/10">
                                                        <div className="flex flex-col">
                                                            <span>Rack injection pump</span>
                                                            <span className="text-[10px] text-muted-foreground font-normal">
                                                                (mm / pos)
                                                            </span>
                                                        </div>
                                                    </td>
                                                    {measurements.map((m) => (
                                                        <td key={m.cylinder} className="p-1 border-r border-border text-center">
                                                            <Input
                                                                type="text"
                                                                value={m.rack_position}
                                                                onChange={(e) =>
                                                                    handleMeasurementChange(
                                                                        m.cylinder,
                                                                        'rack_position',
                                                                        e.target.value
                                                                    )
                                                                }
                                                                placeholder="28"
                                                                className="h-8 w-full text-center font-mono text-xs focus-visible:ring-1"
                                                            />
                                                        </td>
                                                    ))}
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    )}

                                    {/* SECTION: STANDAR YANG DIIZINKAN & PEMERIKSAAN VISUAL */}
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4 pt-1">
                                        <div className="space-y-1.5 rounded-md border border-border bg-card p-3.5 shadow-sm">
                                            <Label className="text-xs font-semibold text-foreground">
                                                Standar Yang di Izinkan :
                                            </Label>
                                            <Input
                                                value={standardAllowed}
                                                onChange={(e) => setStandardAllowed(e.target.value)}
                                                placeholder="Contoh: Sesuai petunjuk pabrik / buku manual (Pmax: 95 bar, delta T < 50°C)"
                                                className="h-8 text-xs"
                                            />
                                            <p className="text-[11px] text-muted-foreground">
                                                Nilai toleransi tekanan pembakaran dan deviasi temperatur gas buang.
                                            </p>
                                        </div>

                                        <div className="space-y-1.5 rounded-md border border-border bg-card p-3.5 shadow-sm">
                                            <Label className="text-xs font-semibold text-foreground">
                                                Pemeriksaan visual :
                                            </Label>
                                            <textarea
                                                rows={3}
                                                value={visualInspection}
                                                onChange={(e) => setVisualInspection(e.target.value)}
                                                placeholder="Contoh: Kondisi fisik normal, tidak ada kebocoran kompresi atau oli pada cover cylinder head..."
                                                className="w-full rounded-md border border-input bg-background p-2 text-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring resize-none"
                                            />
                                            <p className="text-[11px] text-muted-foreground">
                                                Kondisi fisik cylinder head, indikator jelaga, atau rembesan oli.
                                            </p>
                                        </div>
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
                                        title="Pratinjau PDF Pengukuran Tekanan Pembakaran"
                                        src={`${previewPdfUrl}#view=FitH`}
                                        className="h-[750px] w-full rounded-md border border-border bg-white"
                                        style={{ height: '750px', minHeight: '750px', width: '100%' }}
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
                                    {can('har.input.view') && (
                                        <Button
                                            variant="secondary"
                                            onClick={() => router.get(harFormulir.index().url, { unit_id: unit.id })}
                                        >
                                            Batal
                                        </Button>
                                    )}
                                    <Button
                                        variant="outline"
                                        onClick={() => {
                                            if (can_write) {
                                                handleSave();
                                            }

                                            setViewTab('pdf');
                                            setPreviewKey((k) => k + 1);
                                        }}
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
                                            onClick={handleSave}
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

            {/* RIWAYAT PENGISIAN MODAL */}
            <Dialog open={showHistory} onOpenChange={setShowHistory}>
                <DialogContent className="max-w-2xl">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2 text-base font-semibold">
                            <History className="size-5 text-primary" />
                            <span>Riwayat Pengukuran Tekanan Pembakaran</span>
                        </DialogTitle>
                        <DialogDescription className="text-xs text-muted-foreground">
                            Daftar formulir yang telah disimpan untuk unit {unit.name}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="max-h-[380px] overflow-y-auto space-y-2 pr-1">
                        {history.length === 0 ? (
                            <div className="py-8 text-center text-xs text-muted-foreground">
                                Belum ada riwayat pengisian formulir untuk mesin ini.
                            </div>
                        ) : (
                            history.map((h) => {
                                const mach = machines.find((m) => m.id === h.machine_id);

                                return (
                                    <div
                                        key={h.id}
                                        className="flex items-center justify-between rounded-lg border p-3 hover:bg-muted/30 transition-colors"
                                    >
                                        <div className="space-y-1">
                                            <div className="flex items-center gap-2">
                                                <span className="font-semibold text-xs text-foreground">
                                                    {h.test_date}
                                                </span>
                                                <Badge variant="outline" className="text-[10px]">
                                                    {mach?.name || 'Mesin'}
                                                </Badge>
                                                <span className="text-[10px] text-muted-foreground font-mono">
                                                    {h.document_number} (Rev {h.revision})
                                                </span>
                                            </div>
                                            <div className="text-[11px] text-muted-foreground">
                                                Format: {h.format.toUpperCase()} • Diperbarui:{' '}
                                                {new Date(h.updated_at).toLocaleString('id-ID')}
                                            </div>
                                        </div>

                                        <div className="flex items-center gap-1.5">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() => {
                                                    setShowHistory(false);
                                                    router.get(
                                                        combustionRoutes.index.url(),
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

CombustionPressureIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Formulir Pemeliharaan', href: harFormulir.index() },
        { title: 'Pengukuran Tekanan Pembakaran', href: combustionRoutes.index() },
    ],
};
