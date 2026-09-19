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
    Trash2,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { PageHeader } from '@/components/page-header';
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
import harFormulir from '@/routes/har/formulir';
import injectorPressureRoutes from '@/routes/har/formulir/injector-pressure';
import type { IdName } from '@/types';

type InjectorMeasurement = {
    cylinder: number;
    pressure_before: string;
    pressure_after: string;
    nozzle_holes: string;
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
        standard_allowed: string;
        visual_inspection: string;
        measurements: InjectorMeasurement[];
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

export default function InjectorPressureIndex({
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
        form_data.document_number || 'FMKD-314-10.3.3.a-B1'
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
        form_data.serial_number || '26881'
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

    // Standards & Inspection
    const [standardAllowed, setStandardAllowed] = useState<string>(
        form_data.standard_allowed || '270 kg/cm²'
    );
    const [visualInspection, setVisualInspection] = useState<string>(
        form_data.visual_inspection || 'Kondisi fisik injektor bersih, semprotan mengabut sempurna, tidak ada tetesan (dribbling).'
    );

    // Cylinder measurements
    const [cylindersCount, setCylindersCount] = useState<number>(
        form_data.cylinders_count || 8
    );
    const [measurements, setMeasurements] = useState<InjectorMeasurement[]>(() => {
        const initial = form_data.measurements || [];
        if (initial.length === 0) {
            return Array.from({ length: 8 }, (_, i) => ({
                cylinder: i + 1,
                pressure_before: '',
                pressure_after: '',
                nozzle_holes: '',
                notes: '',
            }));
        }
        return initial;
    });

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
        form_data.page_margin_top ?? 8
    );
    const [marginBottom, setMarginBottom] = useState<number>(
        form_data.page_margin_bottom ?? 8
    );
    const [marginLeft, setMarginLeft] = useState<number>(
        form_data.page_margin_left ?? 10
    );
    const [marginRight, setMarginRight] = useState<number>(
        form_data.page_margin_right ?? 10
    );
    const [lineSpacing, setLineSpacing] = useState<string>(
        form_data.line_spacing || '1.1'
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
            const next: InjectorMeasurement[] = [];
            for (let i = 1; i <= safeCount; i++) {
                const existing = prev.find((p) => p.cylinder === i);
                if (existing) {
                    next.push(existing);
                } else {
                    next.push({
                        cylinder: i,
                        pressure_before: '',
                        pressure_after: '',
                        nozzle_holes: '',
                        notes: '',
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
        field: keyof Omit<InjectorMeasurement, 'cylinder'>,
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
                pressure_before: '',
                pressure_after: '',
                nozzle_holes: '',
                notes: '',
            }))
        );
    };

    // Fill sample values matching scan document
    const handleFillScanExample = () => {
        const scanValues: Record<number, Partial<InjectorMeasurement>> = {
            1: { pressure_before: '270', pressure_after: '270', nozzle_holes: '9', notes: '' },
            2: { pressure_before: '260', pressure_after: '270', nozzle_holes: '9', notes: 'Penggantian spring nozzle (bekas layak pakai)' },
            3: { pressure_before: '270', pressure_after: '270', nozzle_holes: '9', notes: '' },
            4: { pressure_before: '270', pressure_after: '270', nozzle_holes: '9', notes: '' },
            5: { pressure_before: '250', pressure_after: '270', nozzle_holes: '9', notes: '' },
            6: { pressure_before: '260', pressure_after: '270', nozzle_holes: '9', notes: '' },
            7: { pressure_before: '270', pressure_after: '270', nozzle_holes: '9', notes: '' },
            8: { pressure_before: '270', pressure_after: '270', nozzle_holes: '9', notes: '' },
        };

        setMeasurements((prev) =>
            prev.map((row) => ({
                ...row,
                ...(scanValues[row.cylinder] || {
                    pressure_before: '270',
                    pressure_after: '270',
                    nozzle_holes: '9',
                    notes: '',
                }),
            }))
        );
        setStandardAllowed('270 kg/cm²');
        setVisualInspection('Kondisi fisik injektor bersih, semprotan mengabut sempurna, tidak ada tetesan (dribbling).');
    };

    // When unit changes in selector
    const handleUnitChange = (newUnitId: string) => {
        router.get(
            injectorPressureRoutes.index().url,
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
            router.get(
                injectorPressureRoutes.index().url,
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
            injectorPressureRoutes.index().url,
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
        params.set('t', String(previewKey));
        params.set('cylinders_count', String(cylindersCount));
        params.set('brand', brand);
        params.set('model_type', modelType);
        params.set('serial_number', serialNumber);
        params.set('machine_number', machineNumber);
        params.set('installed_power', installedPower);
        params.set('capable_power', capablePower);
        params.set('rpm', rpm);
        params.set('standard_allowed', standardAllowed);
        params.set('visual_inspection', visualInspection);
        params.set('manager_ul_id', managerUlId);
        params.set('manager_ul_name', managerUlName);
        params.set('manager_ul_title', managerUlTitle);
        params.set('tl_har_id', tlHarId);
        params.set('tl_har_name', tlHarName);
        params.set('tl_har_title', tlHarTitle);
        params.set('staff_har_id', staffHarId);
        params.set('staff_har_name', staffHarName);
        params.set('staff_har_title', staffHarTitle);
        params.set('page_margin_top', String(marginTop));
        params.set('page_margin_bottom', String(marginBottom));
        params.set('page_margin_left', String(marginLeft));
        params.set('page_margin_right', String(marginRight));
        params.set('line_spacing', lineSpacing);

        measurements.forEach((m, idx) => {
            params.set(`measurements[${idx}][cylinder]`, String(m.cylinder));
            params.set(`measurements[${idx}][pressure_before]`, m.pressure_before);
            params.set(`measurements[${idx}][pressure_after]`, m.pressure_after);
            params.set(`measurements[${idx}][nozzle_holes]`, m.nozzle_holes);
            params.set(`measurements[${idx}][notes]`, m.notes);
        });

        return `${pdf_url}${separator}${params.toString()}`;
    }, [
        pdf_url,
        previewKey,
        cylindersCount,
        brand,
        modelType,
        serialNumber,
        machineNumber,
        installedPower,
        capablePower,
        rpm,
        standardAllowed,
        visualInspection,
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
    ]);

    // Save handler
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
            standard_allowed: standardAllowed,
            visual_inspection: visualInspection,
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

        router.post(injectorPressureRoutes.store().url, payload, {
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
            <Head title={`Formulir Pengukuran Tekanan Pengabutan Injektor - ${unit.name}`} />

            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Formulir Pengukuran Tekanan Pengabutan Injektor"
                    description="Input tekanan bukaan injektor sebelum & sesudah per silinder, atur penandatangan & margin layout, dan cetak PDF resmi."
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
                                                placeholder="No. seri mesin"
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
                                                placeholder="Daya mampu"
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
                                            <SelectItem value="1.05">1,05 (Ideal 1 Halaman)</SelectItem>
                                            <SelectItem value="1.1">1,1 (Standar Scan)</SelectItem>
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
                                        Kop surat, tanggal, spesifikasi, diagram injektor, dan tanda tangan otomatis disesuaikan pada PDF.
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

                            {/* TAB 1: FORM INPUT */}
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
                                                className="h-7 gap-1 text-[11px] text-amber-700 dark:text-amber-400 border-amber-300 hover:bg-amber-50 dark:hover:bg-amber-950/30"
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
                                                Reset
                                            </Button>
                                        </div>
                                    </div>

                                    {/* Measurement Table */}
                                    <div className="overflow-x-auto rounded-md border border-border">
                                        <table className="w-full border-collapse text-xs min-w-[700px]">
                                            <thead>
                                                <tr className="border-b border-border bg-muted/40 font-semibold text-center">
                                                    <th className="border-r border-border p-2 w-14">CYL.</th>
                                                    <th className="border-r border-border p-2">
                                                        TEK. PENGABUTAN SEBELUM<br />
                                                        <span className="text-[10px] font-normal text-muted-foreground">(kg/cm²)</span>
                                                    </th>
                                                    <th className="border-r border-border p-2">
                                                        TEK. PENGABUTAN SESUDAH<br />
                                                        <span className="text-[10px] font-normal text-muted-foreground">(kg/cm²)</span>
                                                    </th>
                                                    <th className="border-r border-border p-2 w-36">
                                                        LUBANG NOZZLE<br />
                                                        <span className="text-[10px] font-normal text-muted-foreground">(BH)</span>
                                                    </th>
                                                    <th className="p-2 text-left">KETERANGAN</th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-border">
                                                {measurements.map((row) => (
                                                    <tr key={row.cylinder} className="hover:bg-muted/20 transition-colors">
                                                        <td className="border-r border-border p-2 text-center font-bold bg-muted/10">
                                                            {row.cylinder}
                                                        </td>
                                                        <td className="border-r border-border p-1.5">
                                                            <Input
                                                                value={row.pressure_before}
                                                                onChange={(e) =>
                                                                    updateMeasurementCell(row.cylinder, 'pressure_before', e.target.value)
                                                                }
                                                                placeholder="270"
                                                                className="h-7 text-xs text-center font-mono"
                                                            />
                                                        </td>
                                                        <td className="border-r border-border p-1.5">
                                                            <Input
                                                                value={row.pressure_after}
                                                                onChange={(e) =>
                                                                    updateMeasurementCell(row.cylinder, 'pressure_after', e.target.value)
                                                                }
                                                                placeholder="270"
                                                                className="h-7 text-xs text-center font-mono"
                                                            />
                                                        </td>
                                                        <td className="border-r border-border p-1.5">
                                                            <Input
                                                                value={row.nozzle_holes}
                                                                onChange={(e) =>
                                                                    updateMeasurementCell(row.cylinder, 'nozzle_holes', e.target.value)
                                                                }
                                                                placeholder="9"
                                                                className="h-7 text-xs text-center font-mono"
                                                            />
                                                        </td>
                                                        <td className="p-1.5">
                                                            <Input
                                                                value={row.notes}
                                                                onChange={(e) =>
                                                                    updateMeasurementCell(row.cylinder, 'notes', e.target.value)
                                                                }
                                                                placeholder="Misal: Penggantian spring nozzle..."
                                                                className="h-7 text-xs"
                                                            />
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>

                                    {/* Standar & Pemeriksaan Visual Box */}
                                    <div className="grid gap-3 md:grid-cols-2">
                                        <div className="rounded-md border border-border bg-muted/20 p-3 space-y-1.5 text-xs">
                                            <Label className="font-semibold text-foreground">
                                                Standar Yang di Izinkan :
                                            </Label>
                                            <Input
                                                value={standardAllowed}
                                                onChange={(e) => setStandardAllowed(e.target.value)}
                                                placeholder="Contoh: 270 kg/cm²"
                                                className="h-8 text-xs font-mono bg-background"
                                            />
                                            <p className="text-[11px] text-muted-foreground">
                                                Nilai standar tekanan bukaan injektor rekomendasi pabrik/manual book.
                                            </p>
                                        </div>

                                        <div className="space-y-1.5 text-xs">
                                            <Label className="font-semibold">Pemeriksaan Visual :</Label>
                                            <textarea
                                                rows={3}
                                                value={visualInspection}
                                                onChange={(e) => setVisualInspection(e.target.value)}
                                                placeholder="Kondisi fisik injektor bersih, semprotan mengabut sempurna, tidak ada tetesan..."
                                                className="w-full rounded-md border border-input bg-background p-2 text-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                            />
                                        </div>
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
                                    <iframe
                                        key={`${previewKey}-${cylindersCount}`}
                                        title="Pratinjau PDF Tekanan Pengabutan Injektor"
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
                                    {record && can_write && (
                                        <Button
                                            type="button"
                                            variant="destructive"
                                            size="sm"
                                            onClick={() => {
                                                if (confirm('Yakin ingin menghapus formulir yang tersimpan ini?')) {
                                                    router.delete(
                                                        injectorPressureRoutes.destroy({ injectorPressure: record.id }).url
                                                    );
                                                }
                                            }}
                                            className="gap-1.5 text-xs"
                                        >
                                            <Trash2 className="size-3.5" />
                                            Hapus
                                        </Button>
                                    )}
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
                        <DialogTitle>Riwayat Formulir Tekanan Pengabutan Injektor - {unit.name}</DialogTitle>
                        <DialogDescription>
                            Daftar catatan uji tekanan pengabutan injektor yang pernah disimpan untuk unit dan mesin ini.
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
                                            Tanggal Uji: {item.test_date}
                                        </div>
                                        <div className="text-[11px] text-muted-foreground font-mono">
                                            {item.document_number} (Rev. {item.revision}) • Format: {item.format}
                                        </div>
                                    </div>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() => {
                                            setShowHistory(false);
                                            router.get(
                                                injectorPressureRoutes.index().url,
                                                {
                                                    unit_id: unit.id,
                                                    machine_id: item.machine_id,
                                                    record_id: item.id,
                                                    test_date: item.test_date,
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
                        )}
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}
