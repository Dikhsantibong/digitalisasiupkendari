import { Head, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Check,
    Download,
    Eye,
    FileCode2,
    History,
    Info,
    Printer,
    RotateCcw,
    Save,
    SlidersHorizontal,
    X,
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
import timingRoutes from '@/routes/har/formulir/timing-injection-pump';

type TimingChecklistItem = {
    cylinder: number;
    timing_before: string;
    timing_after: string;
    notes?: string;
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
        checklist_items: TimingChecklistItem[];
        notes: string;
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

export default function TimingInjectionPumpIndex({
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
        form_data.document_number || 'FMKD-314-10.3.3.a-B5'
    );
    const [revision, setRevision] = useState<string>(form_data.revision || '02');
    const [effectiveDate, setEffectiveDate] = useState<string>(
        form_data.effective_date || '31 Juli 2024'
    );

    // Technical Specs
    const [brand, setBrand] = useState<string>(form_data.brand || 'Mak');
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

    // Cylinder checklist
    const [cylindersCount, setCylindersCount] = useState<number>(
        form_data.cylinders_count || 8
    );
    const [standardAllowed, setStandardAllowed] = useState<string>(
        form_data.standard_allowed || 'Sesuai petunjuk pabrik / buku manual'
    );
    const [checklistItems, setChecklistItems] = useState<
        TimingChecklistItem[]
    >(() => {
        const initial = form_data.checklist_items || [];
        if (initial.length === 0) {
            return Array.from({ length: 8 }, (_, i) => ({
                cylinder: i + 1,
                timing_before: 'v',
                timing_after: 'v',
                notes: '',
            }));
        }
        return initial;
    });
    const [notes, setNotes] = useState<string>(form_data.notes || '');

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

    // HTML Content for rich-text editor
    const [htmlContent, setHtmlContent] = useState<string>(rendered_html);

    // Update cylinder count
    const handleCylinderCountChange = (count: number) => {
        const safeCount = Math.max(1, Math.min(32, count));
        setCylindersCount(safeCount);
        setChecklistItems((prev) => {
            const next: TimingChecklistItem[] = [];
            for (let i = 1; i <= safeCount; i++) {
                const existing = prev.find((p) => p.cylinder === i);
                if (existing) {
                    next.push(existing);
                } else {
                    next.push({
                        cylinder: i,
                        timing_before: 'v',
                        timing_after: 'v',
                        notes: '',
                    });
                }
            }
            return next;
        });
        setPreviewKey((k) => k + 1);
    };

    // Quick set all timing status to 'v' or 'X' or ''
    const setAllStatus = (status: 'v' | 'X' | '') => {
        setChecklistItems((prev) =>
            prev.map((item) => ({
                ...item,
                timing_before: status,
                timing_after: status,
            }))
        );
    };

    // Update single item field
    const updateChecklistItem = (
        cylinder: number,
        field: keyof TimingChecklistItem,
        val: string
    ) => {
        setChecklistItems((prev) =>
            prev.map((item) =>
                item.cylinder === cylinder ? { ...item, [field]: val } : item
            )
        );
    };

    // When unit changes in selector
    const handleUnitChange = (newUnitId: string) => {
        router.get(
            timingRoutes.index().url,
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
            setMachineNumber(m.name.replace(/MIRRLEES\s*#/i, '').trim());
            router.get(
                timingRoutes.index().url,
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
            timingRoutes.index().url,
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
            standard_allowed: standardAllowed,
            checklist_items: checklistItems,
            notes: notes,
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

        router.post(timingRoutes.store().url, payload, {
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

    const selectedManagerUl = manager_options.find(
        (e) => String(e.id) === managerUlId
    );
    const selectedTl = tl_options.find((e) => String(e.id) === tlHarId);
    const selectedStaff = staff_options.find((e) => String(e.id) === staffHarId);

    return (
        <>
            <Head title={`Formulir Checklist Timing Injection Pump - ${unit.name}`} />

            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                {/* Standard Page Header */}
                <PageHeader
                    title="Formulir Checklist Timing Injection Pump"
                    description="Input hasil pemeriksaan sudut penyemprotan bahan bakar (timing injection) pompa injeksi, atur penandatangan & margin layout, dan cetak PDF resmi."
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
                                    className="gap-2 bg-primary text-primary-foreground hover:bg-primary/90"
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
                                    Informasi dasar unit, mesin, dan pengujian timing pump.
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

                                {/* Tanggal Pelaksanaan Timing */}
                                <div className="space-y-1">
                                    <Label className="text-xs">Tanggal Pelaksanaan Timing</Label>
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
                                                placeholder="contoh: Mak"
                                                className="h-7 text-xs"
                                            />
                                        </div>
                                        <div className="space-y-1">
                                            <Label className="text-[11px]">Tipe Mesin</Label>
                                            <Input
                                                value={modelType}
                                                onChange={(e) => setModelType(e.target.value)}
                                                placeholder="contoh: 8M 453 C"
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
                                                placeholder="contoh: 4"
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

                            {/* TAB 1: FORM CHECKLIST INPUT */}
                            {viewTab === 'form' && (
                                <div className="p-4 space-y-4">
                                    {/* Toolbar Silinder & Quick Actions */}
                                    <div className="flex flex-wrap items-center justify-between gap-3 rounded-md border border-border bg-muted/20 p-2.5 text-xs">
                                        <div className="flex items-center gap-2">
                                            <Label className="text-xs font-semibold text-foreground">Jumlah Silinder:</Label>
                                            <div className="flex items-center gap-1">
                                                {[6, 8, 12, 16].map((num) => (
                                                    <Button
                                                        key={num}
                                                        type="button"
                                                        size="sm"
                                                        variant={cylindersCount === num ? 'default' : 'outline'}
                                                        onClick={() => handleCylinderCountChange(num)}
                                                        className="h-7 px-2.5 text-xs font-medium"
                                                    >
                                                        {num}
                                                    </Button>
                                                ))}
                                                <span className="text-[11px] text-muted-foreground ml-1.5 mr-0.5">Kustom:</span>
                                                <Input
                                                    type="number"
                                                    min={1}
                                                    max={32}
                                                    value={cylindersCount}
                                                    onChange={(e) => handleCylinderCountChange(Number(e.target.value))}
                                                    className="h-7 w-14 text-xs text-center font-mono font-medium"
                                                />
                                            </div>
                                        </div>

                                        <div className="flex items-center gap-1.5">
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                onClick={() => setAllStatus('v')}
                                                className="h-7 gap-1 text-[11px] text-emerald-700 border-emerald-300 hover:bg-emerald-50"
                                            >
                                                <Check className="size-3.5" />
                                                Set Semua (v) Normal
                                            </Button>
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                onClick={() => setAllStatus('')}
                                                className="h-7 gap-1 text-[11px]"
                                            >
                                                <RotateCcw className="size-3.5" />
                                                Reset
                                            </Button>
                                        </div>
                                    </div>

                                    {/* Standar Yang di Izinkan */}
                                    <div className="flex flex-wrap items-center gap-2 rounded-md border border-border p-2.5 text-xs bg-card">
                                        <Label className="font-semibold text-foreground">
                                            Standar Yang di Izinkan :
                                        </Label>
                                        <Input
                                            value={standardAllowed}
                                            onChange={(e) => setStandardAllowed(e.target.value)}
                                            placeholder="Sesuai petunjuk pabrik / buku manual"
                                            className="h-7 text-xs flex-1 max-w-sm"
                                        />
                                        <span className="text-[11px] text-muted-foreground italic">
                                            (Note: Lihat Buku Petunjuk Pabrik Untuk Lebih Detail)
                                        </span>
                                    </div>

                                    {/* Table Checklist Input */}
                                    <div className="overflow-x-auto rounded-md border border-border bg-card">
                                        <table className="w-full border-collapse text-xs min-w-[700px]">
                                            <thead>
                                                <tr className="border-b border-border bg-muted/40 font-semibold text-center">
                                                    <th rowSpan={2} className="border-r border-border p-2 w-14">
                                                        CYL.
                                                    </th>
                                                    <th colSpan={2} className="border-r border-border p-2 tracking-wider">
                                                        TIMING PEMBAKARAN
                                                    </th>
                                                    <th rowSpan={2} className="p-2 text-left">
                                                        KETERANGAN SILINDER
                                                    </th>
                                                </tr>
                                                <tr className="border-b border-border bg-muted/20 font-semibold text-center text-[11px]">
                                                    <th className="border-r border-border p-1.5 w-32">SEBELUM</th>
                                                    <th className="border-r border-border p-1.5 w-32">SESUDAH</th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-border">
                                                {checklistItems.map((item) => (
                                                    <tr
                                                        key={item.cylinder}
                                                        className="hover:bg-muted/15 transition-colors"
                                                    >
                                                        <td className="p-2 text-center font-bold text-sm border-r border-border bg-muted/5">
                                                            {item.cylinder}
                                                        </td>
                                                        <td className="p-1.5 border-r border-border text-center">
                                                            <div className="flex items-center justify-center gap-1">
                                                                <button
                                                                    type="button"
                                                                    onClick={() =>
                                                                        updateChecklistItem(
                                                                            item.cylinder,
                                                                            'timing_before',
                                                                            item.timing_before === 'v' ? '' : 'v'
                                                                        )
                                                                    }
                                                                    className={`size-6 rounded flex items-center justify-center font-bold text-xs transition-colors ${
                                                                        item.timing_before === 'v'
                                                                            ? 'bg-emerald-600 text-white shadow-sm'
                                                                            : 'bg-muted/70 text-muted-foreground hover:bg-muted'
                                                                    }`}
                                                                    title="v = Normal"
                                                                >
                                                                    ✓
                                                                </button>
                                                                <button
                                                                    type="button"
                                                                    onClick={() =>
                                                                        updateChecklistItem(
                                                                            item.cylinder,
                                                                            'timing_before',
                                                                            item.timing_before === 'X' ? '' : 'X'
                                                                        )
                                                                    }
                                                                    className={`size-6 rounded flex items-center justify-center font-bold text-xs transition-colors ${
                                                                        item.timing_before === 'X'
                                                                            ? 'bg-red-600 text-white shadow-sm'
                                                                            : 'bg-muted/70 text-muted-foreground hover:bg-muted'
                                                                    }`}
                                                                    title="X = Tidak Normal"
                                                                >
                                                                    ✕
                                                                </button>
                                                            </div>
                                                        </td>
                                                        <td className="p-1.5 border-r border-border text-center">
                                                            <div className="flex items-center justify-center gap-1">
                                                                <button
                                                                    type="button"
                                                                    onClick={() =>
                                                                        updateChecklistItem(
                                                                            item.cylinder,
                                                                            'timing_after',
                                                                            item.timing_after === 'v' ? '' : 'v'
                                                                        )
                                                                    }
                                                                    className={`size-6 rounded flex items-center justify-center font-bold text-xs transition-colors ${
                                                                        item.timing_after === 'v'
                                                                            ? 'bg-emerald-600 text-white shadow-sm'
                                                                            : 'bg-muted/70 text-muted-foreground hover:bg-muted'
                                                                    }`}
                                                                    title="v = Normal"
                                                                >
                                                                    ✓
                                                                </button>
                                                                <button
                                                                    type="button"
                                                                    onClick={() =>
                                                                        updateChecklistItem(
                                                                            item.cylinder,
                                                                            'timing_after',
                                                                            item.timing_after === 'X' ? '' : 'X'
                                                                        )
                                                                    }
                                                                    className={`size-6 rounded flex items-center justify-center font-bold text-xs transition-colors ${
                                                                        item.timing_after === 'X'
                                                                            ? 'bg-red-600 text-white shadow-sm'
                                                                            : 'bg-muted/70 text-muted-foreground hover:bg-muted'
                                                                    }`}
                                                                    title="X = Tidak Normal"
                                                                >
                                                                    ✕
                                                                </button>
                                                            </div>
                                                        </td>
                                                        <td className="p-1.5">
                                                            <Input
                                                                value={item.notes || ''}
                                                                onChange={(e) =>
                                                                    updateChecklistItem(item.cylinder, 'notes', e.target.value)
                                                                }
                                                                placeholder={`Catatan silinder ${item.cylinder}...`}
                                                                className="h-7 text-xs bg-background"
                                                            />
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>

                                    {/* Keterangan & Catatan Box */}
                                    <div className="grid gap-3 md:grid-cols-2">
                                        <div className="rounded-md border border-border bg-muted/20 p-3 space-y-1.5 text-xs">
                                            <span className="font-semibold text-foreground">
                                                Legenda Kode Checklist:
                                            </span>
                                            <div className="space-y-1 text-muted-foreground">
                                                <div className="flex items-center gap-2">
                                                    <span className="size-5 rounded bg-emerald-600 text-white font-bold flex items-center justify-center text-[10px]">
                                                        ✓
                                                    </span>
                                                    <span><strong>v</strong> = Normal (Sesuai petunjuk pabrik / buku manual)</span>
                                                </div>
                                                <div className="flex items-center gap-2">
                                                    <span className="size-5 rounded bg-red-600 text-white font-bold flex items-center justify-center text-[10px]">
                                                        ✕
                                                    </span>
                                                    <span><strong>X</strong> = Tidak Normal (Menyimpang / perlu penyetelan)</span>
                                                </div>
                                            </div>
                                        </div>

                                        <div className="space-y-1.5 text-xs">
                                            <Label className="font-semibold">Catatan Umum / Rekomendasi:</Label>
                                            <textarea
                                                rows={3}
                                                value={notes}
                                                onChange={(e) => setNotes(e.target.value)}
                                                placeholder="Tuliskan catatan teknis atau rekomendasi pemeliharaan bila diperlukan..."
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
                                    <PdfPreviewFrame
                                        key={`${previewKey}-${cylindersCount}`}
                                        title="Pratinjau PDF Timing Injection Pump"
                                        src={previewPdfUrl}
                                        className="h-[750px] w-full rounded-md border border-border bg-white"
                                    />
                                </div>
                            )}

                            {/* Card Bottom Actions (Matching Screenshot media_1789358712172.png) */}
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
                                            {isSaving ? 'Menyimpan…' : 'Simpan Formulir'}
                                        </Button>
                                    )}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Riwayat Dialog */}
            <Dialog open={showHistory} onOpenChange={setShowHistory}>
                <DialogContent className="max-w-xl">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <History className="size-5 text-primary" />
                            Riwayat Formulir Timing Injection Pump
                        </DialogTitle>
                        <DialogDescription>
                            Daftar formulir yang pernah disimpan untuk unit {unit.name}.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="max-h-96 overflow-y-auto space-y-2 mt-2">
                        {history.length === 0 ? (
                            <div className="p-4 text-center text-xs text-muted-foreground border border-dashed rounded-md">
                                Belum ada riwayat formulir tersimpan untuk unit ini.
                            </div>
                        ) : (
                            history.map((h) => {
                                const m = machines.find((mac) => mac.id === h.machine_id);
                                return (
                                    <div
                                        key={h.id}
                                        className="flex items-center justify-between rounded-md border border-border p-3 text-xs hover:bg-muted/30 transition-colors"
                                    >
                                        <div>
                                            <div className="font-semibold text-foreground">
                                                {m?.name || `Mesin #${h.machine_id}`} • {h.test_date}
                                            </div>
                                            <div className="text-[11px] text-muted-foreground">
                                                No: {h.document_number} (Rev. {h.revision}) • Format: {h.format.toUpperCase()}
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-1.5">
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                className="h-7 text-xs gap-1"
                                                onClick={() => {
                                                    setShowHistory(false);
                                                    router.get(timingRoutes.index().url, {
                                                        unit_id: unit.id,
                                                        machine_id: h.machine_id,
                                                        test_date: h.test_date,
                                                        record_id: h.id,
                                                    });
                                                }}
                                            >
                                                <Eye className="size-3" />
                                                Buka
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

TimingInjectionPumpIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Formulir Pemeliharaan', href: harFormulir.index() },
        { title: 'Checklist Timing Injection Pump', href: timingRoutes.index() },
    ],
};
