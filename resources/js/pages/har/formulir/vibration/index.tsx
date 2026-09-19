import { Head, router } from '@inertiajs/react';
import {
    Activity,
    ArrowLeft,
    Check,
    Download,
    ExternalLink,
    Eye,
    FileCode2,
    FileSpreadsheet,
    History,
    Info,
    Maximize2,
    Printer,
    RefreshCw,
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
import vibrationRoutes from '@/routes/har/formulir/vibration';
import harFormulir from '@/routes/har/formulir';
import type { IdName } from '@/types';

export type VibrationMeasurement = {
    no: number;
    point: string;
    code: string;
    v_max: string;
    v_min: string;
    v_avg: string;
    h_max: string;
    h_min: string;
    h_avg: string;
    note: string;
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
        test_date_formatted: string;
        unit_name: string;
        brand: string;
        model_type: string;
        installed_power: string;
        capable_power: string;
        serial_number: string;
        machine_number: string;
        rpm: string;
        measurements: VibrationMeasurement[];
        standard_text: string;
        max_text: string;
        conclusion_text: string;
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
        diagram_image?: string | null;
    };
    rendered_html: string;
    manager_options: EmployeeOption[];
    tl_options: EmployeeOption[];
    staff_options: EmployeeOption[];
    history: HistoryItem[];
    pdf_url: string;
    sample_scan_measurements: VibrationMeasurement[];
    default_measurements: VibrationMeasurement[];
    can_write: boolean;
};

// Helper for computing (max + min) / 2
const calculateAvg = (val1: string, val2: string): string => {
    if (!val1 && !val2) return '';
    const n1 = parseFloat(val1.replace(',', '.'));
    const n2 = parseFloat(val2.replace(',', '.'));
    if (!isNaN(n1) && !isNaN(n2)) {
        const avg = (n1 + n2) / 2;
        return avg.toFixed(2);
    }
    if (!isNaN(n1)) return n1.toFixed(2);
    if (!isNaN(n2)) return n2.toFixed(2);
    return '';
};

export default function VibrationIndex({
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
    sample_scan_measurements,
    default_measurements,
    can_write,
}: Props) {
    // Current Machine & Date
    const [machineId, setMachineId] = useState<number>(
        selected_machine_id ?? (machines[0]?.id || 0)
    );
    const [testDate, setTestDate] = useState<string>(
        selected_test_date || form_data.test_date_raw
    );

    // Document Metadata
    const [docNumber, setDocNumber] = useState<string>(
        form_data.document_number || 'FMKD-314-10.3.3.a-B10'
    );
    const [revision, setRevision] = useState<string>(
        form_data.revision || '03'
    );
    const [effectiveDate, setEffectiveDate] = useState<string>(
        form_data.effective_date || '31 Juli 2024'
    );

    // Machine Specs
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
        form_data.capable_power || '1500 kW'
    );
    const [rpm, setRpm] = useState<string>(form_data.rpm || '600');

    // Measurements Table
    const [measurements, setMeasurements] = useState<VibrationMeasurement[]>(
        form_data.measurements && form_data.measurements.length > 0
            ? form_data.measurements
            : sample_scan_measurements
    );

    // Notes and Conclusions
    const [standardText, setStandardText] = useState<string>(
        form_data.standard_text || ''
    );
    const [maxText, setMaxText] = useState<string>(
        form_data.max_text || ''
    );
    const [conclusionText, setConclusionText] = useState<string>(
        form_data.conclusion_text || ''
    );

    // Signatories
    const [managerUlId, setManagerUlId] = useState<string>(
        form_data.manager_ul_id ? String(form_data.manager_ul_id) : 'custom'
    );
    const [managerUlName, setManagerUlName] = useState<string>(
        form_data.manager_ul_name || ''
    );
    const [managerUlTitle, setManagerUlTitle] = useState<string>(
        form_data.manager_ul_title ||
            `Plh. Manager Unit Layanan ${unit.service_unit_name || unit.name}`
    );

    const [tlHarId, setTlHarId] = useState<string>(
        form_data.tl_har_id ? String(form_data.tl_har_id) : 'custom'
    );
    const [tlHarName, setTlHarName] = useState<string>(
        form_data.tl_har_name || ''
    );
    const [tlHarTitle, setTlHarTitle] = useState<string>(
        form_data.tl_har_title || 'Team Leader Pemeliharaan'
    );

    const [staffHarId, setStaffHarId] = useState<string>(
        form_data.staff_har_id ? String(form_data.staff_har_id) : 'custom'
    );
    const [staffHarName, setStaffHarName] = useState<string>(
        form_data.staff_har_name || ''
    );
    const [staffHarTitle, setStaffHarTitle] = useState<string>(
        form_data.staff_har_title || 'Staff Pemeliharaan'
    );

    // Margins & Layout
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
        form_data.line_spacing ?? '1.1'
    );

    // UI View Tab
    const [viewTab, setViewTab] = useState<'form' | 'html' | 'preview'>(
        record?.format === 'html' ? 'html' : 'form'
    );
    const [htmlContent, setHtmlContent] = useState<string>(rendered_html);
    const [isSaving, setIsSaving] = useState(false);
    const [isHistoryOpen, setIsHistoryOpen] = useState(false);
    const [previewKey, setPreviewKey] = useState(0);
    const [showDiagram, setShowDiagram] = useState(true);

    // Sync state when props change
    useEffect(() => {
        if (record) {
            setDocNumber(record.document_number || 'FMKD-314-10.3.3.a-B10');
            setRevision(record.revision || '03');
            setEffectiveDate(record.effective_date || '31 Juli 2024');
            setBrand(record.brand || 'MAK');
            setModelType(record.model_type || '8M 453 C');
            setInstalledPower(record.installed_power || '2800');
            setCapablePower(record.capable_power || '1500 kW');
            setSerialNumber(record.serial_number || '');
            setMachineNumber(record.machine_number || '4');
            setRpm(record.rpm || '600');
            setMeasurements(record.measurements || sample_scan_measurements);
            setStandardText(record.standard_text || '');
            setMaxText(record.max_text || '');
            setConclusionText(record.conclusion_text || '');
            setManagerUlId(record.manager_ul_id ? String(record.manager_ul_id) : 'custom');
            setManagerUlName(record.manager_ul_name || '');
            setManagerUlTitle(record.manager_ul_title || '');
            setTlHarId(record.tl_har_id ? String(record.tl_har_id) : 'custom');
            setTlHarName(record.tl_har_name || '');
            setTlHarTitle(record.tl_har_title || '');
            setStaffHarId(record.staff_har_id ? String(record.staff_har_id) : 'custom');
            setStaffHarName(record.staff_har_name || '');
            setStaffHarTitle(record.staff_har_title || '');
            setMarginTop(record.page_margin_top ?? 8);
            setMarginBottom(record.page_margin_bottom ?? 8);
            setMarginLeft(record.page_margin_left ?? 10);
            setMarginRight(record.page_margin_right ?? 10);
            setLineSpacing(record.line_spacing ?? '1.1');
            if (record.format === 'html' && record.content_html) {
                setHtmlContent(record.content_html);
                setViewTab('html');
            }
        }
        setPreviewKey((k) => k + 1);
    }, [record]);

    // Handle measurement field edits
    const handleMeasurementChange = (
        index: number,
        field: keyof VibrationMeasurement,
        val: string
    ) => {
        setMeasurements((prev) => {
            const next = [...prev];
            const row = { ...next[index], [field]: val };

            // Auto calculate averages
            if (field === 'v_max' || field === 'v_min') {
                const vMax = field === 'v_max' ? val : row.v_max;
                const vMin = field === 'v_min' ? val : row.v_min;
                row.v_avg = calculateAvg(vMax, vMin);
            }
            if (field === 'h_max' || field === 'h_min') {
                const hMax = field === 'h_max' ? val : row.h_max;
                const hMin = field === 'h_min' ? val : row.h_min;
                row.h_avg = calculateAvg(hMax, hMin);
            }

            next[index] = row;
            return next;
        });
    };

    // Quick fill sample scan data
    const handleFillSampleScan = () => {
        setMeasurements(sample_scan_measurements);
        setStandardText('');
        setMaxText('');
        setConclusionText('');
    };

    // Reset to blank standard template
    const handleResetToBlank = () => {
        setMeasurements(default_measurements);
        setStandardText('');
        setMaxText('');
        setConclusionText('');
    };

    // When unit changes in selector
    const handleUnitChange = (newUnitId: string) => {
        router.get(
            vibrationRoutes.index().url,
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
                m.name
                    .replace(/MIRRLEES\s*#/i, '')
                    .replace(/MESIN\s*#/i, '')
                    .replace(/UNIT\s*#/i, '')
                    .replace(/#/, '')
                    .trim() || '4'
            );
            router.get(
                vibrationRoutes.index().url,
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
            vibrationRoutes.index().url,
            {
                unit_id: unit.id,
                machine_id: machineId,
                test_date: newDate,
            },
            { preserveState: true }
        );
    };

    // Live computed PDF URL for preview with current parameters
    const previewPdfUrl = useMemo(() => {
        const separator = pdf_url.includes('?') ? '&' : '?';
        const params = new URLSearchParams();
        params.set('t', String(previewKey));
        params.set('brand', brand);
        params.set('model_type', modelType);
        params.set('installed_power', installedPower);
        params.set('capable_power', capablePower);
        params.set('serial_number', serialNumber);
        params.set('machine_number', machineNumber);
        params.set('rpm', rpm);
        params.set('document_number', docNumber);
        params.set('revision', revision);
        params.set('effective_date', effectiveDate);
        params.set('standard_text', standardText);
        params.set('max_text', maxText);
        params.set('conclusion_text', conclusionText);
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
            params.set(`measurements[${idx}][no]`, String(m.no));
            params.set(`measurements[${idx}][point]`, m.point);
            params.set(`measurements[${idx}][code]`, m.code);
            params.set(`measurements[${idx}][v_max]`, m.v_max || '');
            params.set(`measurements[${idx}][v_min]`, m.v_min || '');
            params.set(`measurements[${idx}][v_avg]`, m.v_avg || '');
            params.set(`measurements[${idx}][h_max]`, m.h_max || '');
            params.set(`measurements[${idx}][h_min]`, m.h_min || '');
            params.set(`measurements[${idx}][h_avg]`, m.h_avg || '');
            params.set(`measurements[${idx}][note]`, m.note || '');
        });

        return `${pdf_url}${separator}${params.toString()}`;
    }, [
        pdf_url,
        previewKey,
        brand,
        modelType,
        installedPower,
        capablePower,
        serialNumber,
        machineNumber,
        rpm,
        docNumber,
        revision,
        effectiveDate,
        standardText,
        maxText,
        conclusionText,
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
        measurements,
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
            installed_power: installedPower,
            capable_power: capablePower,
            serial_number: serialNumber,
            machine_number: machineNumber,
            rpm: rpm,
            measurements: measurements,
            standard_text: standardText,
            max_text: maxText,
            conclusion_text: conclusionText,
            manager_ul_id:
                managerUlId && managerUlId !== 'custom' ? Number(managerUlId) : null,
            manager_ul_name: managerUlName,
            manager_ul_title: managerUlTitle,
            tl_har_id: tlHarId && tlHarId !== 'custom' ? Number(tlHarId) : null,
            tl_har_name: tlHarName,
            tl_har_title: tlHarTitle,
            staff_har_id:
                staffHarId && staffHarId !== 'custom' ? Number(staffHarId) : null,
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

        router.post(vibrationRoutes.store().url, payload, {
            preserveScroll: true,
            onSuccess: () => {
                setPreviewKey((k) => k + 1);
                if (onSuccessCallback) {
                    onSuccessCallback();
                }
            },
            onFinish: () => {
                setIsSaving(false);
            },
        });
    };

    // Reset database record handler
    const handleDatabaseReset = () => {
        if (
            !confirm(
                'Apakah Anda yakin ingin mereset data tersimpan untuk mesin dan tanggal ini? Data akan dikembalikan ke nilai awal.'
            )
        ) {
            return;
        }

        router.post(
            vibrationRoutes.reset().url,
            {
                unit_id: unit.id,
                machine_id: machineId,
                test_date: testDate,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    handleResetToBlank();
                    setPreviewKey((k) => k + 1);
                },
            }
        );
    };

    return (
        <div className="flex flex-col min-h-screen bg-background">
            <Head title="Formulir Pengukuran Tekanan Vibrasi" />

            {/* Top Bar Header */}
            <div className="sticky top-0 z-30 flex items-center justify-between border-b border-border bg-background/95 px-6 py-3 backdrop-blur supports-[backdrop-filter]:bg-background/60">
                <div className="flex items-center gap-3">
                    <Button
                        variant="ghost"
                        size="icon"
                        onClick={() => router.get(harFormulir.index().url)}
                        title="Kembali ke Hub Formulir"
                    >
                        <ArrowLeft className="h-5 w-5" />
                    </Button>
                    <div>
                        <div className="flex items-center gap-2">
                            <h1 className="text-base font-bold tracking-tight text-foreground flex items-center gap-2">
                                <Activity className="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
                                Formulir Pengukuran Tekanan Vibrasi
                            </h1>
                            <StatusBadge tone="info">FMKD-314-10.3.3.a-B10</StatusBadge>
                            {record && (
                                <Badge variant="outline" className="text-xs bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300 border-emerald-200">
                                    Tersimpan ({record.format === 'html' ? 'HTML' : 'Form'})
                                </Badge>
                            )}
                        </div>
                        <p className="text-xs text-muted-foreground">
                            {unit.name} • {machines.find((m) => m.id === machineId)?.name || 'Mesin'} • Tanggal: {testDate}
                        </p>
                    </div>
                </div>

                <div className="flex items-center gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => setIsHistoryOpen(true)}
                        className="text-xs"
                    >
                        <History className="h-3.5 w-3.5 mr-1.5" />
                        Riwayat ({history.length})
                    </Button>

                    <Button
                        variant="outline"
                        size="sm"
                        onClick={handleDatabaseReset}
                        className="text-xs text-destructive hover:bg-destructive/10"
                        title="Reset formulir ke bawaan"
                    >
                        <RotateCcw className="h-3.5 w-3.5 mr-1.5" />
                        Reset
                    </Button>

                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => window.open(`${previewPdfUrl}&download=1`, '_blank')}
                        className="text-xs"
                    >
                        <Download className="h-3.5 w-3.5 mr-1.5" />
                        Unduh PDF
                    </Button>

                    {can_write && (
                        <Button
                            size="sm"
                            onClick={() => handleSave()}
                            disabled={isSaving}
                            className="text-xs bg-emerald-600 hover:bg-emerald-700 text-white"
                        >
                            <Save className="h-3.5 w-3.5 mr-1.5" />
                            {isSaving ? 'Menyimpan...' : 'Simpan Data'}
                        </Button>
                    )}
                </div>
            </div>

            {/* Main Content Layout (2 Columns) */}
            <div className="flex-1 p-6">
                <div className="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                    {/* Left Column: Metadata & Controls (4 Cols) */}
                    <div className="lg:col-span-4 space-y-6">
                        {/* Card 1: Filter Unit & Mesin */}
                        <div className="rounded-lg border border-border bg-card p-4 space-y-4">
                            <div className="border-b border-border pb-2">
                                <h3 className="text-sm font-semibold text-foreground">
                                    Parameter Pengujian
                                </h3>
                                <p className="text-[12px] text-muted-foreground">
                                    Pilih sentral pembangkit, unit mesin, dan tanggal pengujian.
                                </p>
                            </div>

                            <div className="space-y-3 text-xs">
                                <div className="space-y-1">
                                    <Label className="text-xs">Unit Layanan / Sentral</Label>
                                    <Select
                                        value={String(unit.id)}
                                        onValueChange={handleUnitChange}
                                    >
                                        <SelectTrigger className="h-8 text-xs">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {units.map((u) => (
                                                <SelectItem
                                                    key={u.id}
                                                    value={String(u.id)}
                                                    className="text-xs"
                                                >
                                                    {u.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="space-y-1">
                                    <Label className="text-xs">Mesin</Label>
                                    <Select
                                        value={String(machineId)}
                                        onValueChange={handleMachineChange}
                                    >
                                        <SelectTrigger className="h-8 text-xs">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {machines.map((m) => (
                                                <SelectItem
                                                    key={m.id}
                                                    value={String(m.id)}
                                                    className="text-xs"
                                                >
                                                    {m.name} {m.type ? `(${m.type})` : ''}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="space-y-1">
                                    <Label className="text-xs">Tanggal Pengujian</Label>
                                    <Input
                                        type="date"
                                        value={testDate}
                                        onChange={(e) => handleDateChange(e.target.value)}
                                        className="h-8 text-xs"
                                    />
                                </div>
                            </div>
                        </div>

                        {/* Card 2: Identitas Dokumen & Spesifikasi */}
                        <div className="rounded-lg border border-border bg-card p-4 space-y-4">
                            <div className="border-b border-border pb-2">
                                <h3 className="text-sm font-semibold text-foreground">
                                    Header & Spesifikasi Mesin
                                </h3>
                                <p className="text-[12px] text-muted-foreground">
                                    Informasi dokumen mutu ISO dan data spesifikasi mesin.
                                </p>
                            </div>

                            <div className="space-y-3 text-xs">
                                <div className="grid grid-cols-3 gap-2">
                                    <div className="col-span-2 space-y-1">
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
                                            className="h-8 text-xs text-center"
                                        />
                                    </div>
                                </div>

                                <div className="space-y-1">
                                    <Label className="text-xs">Tanggal Terbit Dokumen</Label>
                                    <Input
                                        value={effectiveDate}
                                        onChange={(e) => setEffectiveDate(e.target.value)}
                                        placeholder="31 Juli 2024"
                                        className="h-8 text-xs"
                                    />
                                </div>

                                <div className="grid grid-cols-2 gap-2">
                                    <div className="space-y-1">
                                        <Label className="text-xs">Brand Mesin</Label>
                                        <Input
                                            value={brand}
                                            onChange={(e) => setBrand(e.target.value)}
                                            placeholder="MAK"
                                            className="h-8 text-xs"
                                        />
                                    </div>
                                    <div className="space-y-1">
                                        <Label className="text-xs">Type / Model</Label>
                                        <Input
                                            value={modelType}
                                            onChange={(e) => setModelType(e.target.value)}
                                            placeholder="8M 453 C"
                                            className="h-8 text-xs"
                                        />
                                    </div>
                                </div>

                                <div className="grid grid-cols-3 gap-2">
                                    <div className="space-y-1">
                                        <Label className="text-xs">Mesin No.</Label>
                                        <Input
                                            value={machineNumber}
                                            onChange={(e) => setMachineNumber(e.target.value)}
                                            placeholder="4"
                                            className="h-8 text-xs text-center font-semibold"
                                        />
                                    </div>
                                    <div className="col-span-2 space-y-1">
                                        <Label className="text-xs">No. Seri (Serial No)</Label>
                                        <Input
                                            value={serialNumber}
                                            onChange={(e) => setSerialNumber(e.target.value)}
                                            placeholder="-"
                                            className="h-8 text-xs"
                                        />
                                    </div>
                                </div>

                                <div className="grid grid-cols-3 gap-2">
                                    <div className="space-y-1">
                                        <Label className="text-xs">Daya Pasang</Label>
                                        <Input
                                            value={installedPower}
                                            onChange={(e) => setInstalledPower(e.target.value)}
                                            placeholder="2800"
                                            className="h-8 text-xs"
                                        />
                                    </div>
                                    <div className="space-y-1">
                                        <Label className="text-xs">Daya Mampu</Label>
                                        <Input
                                            value={capablePower}
                                            onChange={(e) => setCapablePower(e.target.value)}
                                            placeholder="1500 kW"
                                            className="h-8 text-xs"
                                        />
                                    </div>
                                    <div className="space-y-1">
                                        <Label className="text-xs">Putaran (RPM)</Label>
                                        <Input
                                            value={rpm}
                                            onChange={(e) => setRpm(e.target.value)}
                                            placeholder="600"
                                            className="h-8 text-xs text-center"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Card 3: Penandatangan Dokumen (3 Kolom) */}
                        <div className="rounded-lg border border-border bg-card p-4 space-y-4">
                            <div className="border-b border-border pb-2">
                                <h3 className="text-sm font-semibold text-foreground">
                                    Pengesahan Dokumen (3 Kolom)
                                </h3>
                                <p className="text-[12px] text-muted-foreground">
                                    Pejabat penanggung jawab pengujian getaran / vibrasi.
                                </p>
                            </div>

                            <div className="space-y-3 text-xs">
                                {/* 1. Mengetahui: Manager UL */}
                                <div className="rounded-md border border-border/70 p-2.5 space-y-2 bg-muted/20">
                                    <div className="font-semibold text-foreground text-xs">
                                        1. Mengetahui: Manager Unit Layanan
                                    </div>
                                    <Select
                                        value={managerUlId}
                                        onValueChange={(val) => {
                                            setManagerUlId(val);
                                            if (val !== 'custom') {
                                                const emp = manager_options.find(
                                                    (e) => String(e.id) === val
                                                );
                                                if (emp) setManagerUlName(emp.name);
                                            }
                                        }}
                                    >
                                        <SelectTrigger className="h-7 text-xs">
                                            <SelectValue placeholder="Pilih Pejabat" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="custom" className="text-xs">
                                                — Input Manual —
                                            </SelectItem>
                                            {manager_options.map((e) => (
                                                <SelectItem
                                                    key={e.id}
                                                    value={String(e.id)}
                                                    className="text-xs"
                                                >
                                                    {e.name} ({e.position || 'Manager'})
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <Input
                                        placeholder="Judul Jabatan (misal: Plh. Manager Unit Layanan...)"
                                        value={managerUlTitle}
                                        onChange={(e) => setManagerUlTitle(e.target.value)}
                                        className="h-7 text-xs"
                                    />
                                    <Input
                                        placeholder="Nama Lengkap"
                                        value={managerUlName}
                                        onChange={(e) => setManagerUlName(e.target.value)}
                                        className="h-7 text-xs font-medium"
                                    />
                                </div>

                                {/* 2. Diperiksa: TL Pemeliharaan */}
                                <div className="rounded-md border border-border/70 p-2.5 space-y-2 bg-muted/20">
                                    <div className="font-semibold text-foreground text-xs">
                                        2. Diperiksa: Team Leader Pemeliharaan
                                    </div>
                                    <Select
                                        value={tlHarId}
                                        onValueChange={(val) => {
                                            setTlHarId(val);
                                            if (val !== 'custom') {
                                                const emp = tl_options.find(
                                                    (e) => String(e.id) === val
                                                );
                                                if (emp) setTlHarName(emp.name);
                                            }
                                        }}
                                    >
                                        <SelectTrigger className="h-7 text-xs">
                                            <SelectValue placeholder="Pilih TL Har" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="custom" className="text-xs">
                                                — Input Manual —
                                            </SelectItem>
                                            {tl_options.map((e) => (
                                                <SelectItem
                                                    key={e.id}
                                                    value={String(e.id)}
                                                    className="text-xs"
                                                >
                                                    {e.name} ({e.position || 'TL Har'})
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <Input
                                        placeholder="Judul Jabatan"
                                        value={tlHarTitle}
                                        onChange={(e) => setTlHarTitle(e.target.value)}
                                        className="h-7 text-xs"
                                    />
                                    <Input
                                        placeholder="Nama Lengkap"
                                        value={tlHarName}
                                        onChange={(e) => setTlHarName(e.target.value)}
                                        className="h-7 text-xs font-medium"
                                    />
                                </div>

                                {/* 3. Dibuat: Staff Pemeliharaan */}
                                <div className="rounded-md border border-border/70 p-2.5 space-y-2 bg-muted/20">
                                    <div className="font-semibold text-foreground text-xs">
                                        3. Dibuat: Pelaksana / Staff Pemeliharaan
                                    </div>
                                    <Select
                                        value={staffHarId}
                                        onValueChange={(val) => {
                                            setStaffHarId(val);
                                            if (val !== 'custom') {
                                                const emp = staff_options.find(
                                                    (e) => String(e.id) === val
                                                );
                                                if (emp) setStaffHarName(emp.name);
                                            }
                                        }}
                                    >
                                        <SelectTrigger className="h-7 text-xs">
                                            <SelectValue placeholder="Pilih Staff Har" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="custom" className="text-xs">
                                                — Input Manual —
                                            </SelectItem>
                                            {staff_options.map((e) => (
                                                <SelectItem
                                                    key={e.id}
                                                    value={String(e.id)}
                                                    className="text-xs"
                                                >
                                                    {e.name} ({e.position || 'Staff Har'})
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <Input
                                        placeholder="Judul Jabatan"
                                        value={staffHarTitle}
                                        onChange={(e) => setStaffHarTitle(e.target.value)}
                                        className="h-7 text-xs"
                                    />
                                    <Input
                                        placeholder="Nama Lengkap"
                                        value={staffHarName}
                                        onChange={(e) => setStaffHarName(e.target.value)}
                                        className="h-7 text-xs font-medium"
                                    />
                                </div>
                            </div>
                        </div>

                        {/* Card 4: Pengaturan Margin Cetak PDF */}
                        <div className="rounded-lg border border-border bg-card p-4 space-y-4">
                            <div className="border-b border-border pb-2 flex items-center justify-between">
                                <div>
                                    <h3 className="text-sm font-semibold text-foreground flex items-center gap-1.5">
                                        <SlidersHorizontal className="h-3.5 w-3.5" />
                                        Margin & Presisi PDF (mm)
                                    </h3>
                                    <p className="text-[12px] text-muted-foreground">
                                        Atur batas tepi agar cetakan pas 1 lembar A4 portrait.
                                    </p>
                                </div>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => {
                                        setMarginTop(8);
                                        setMarginBottom(8);
                                        setMarginLeft(10);
                                        setMarginRight(10);
                                        setLineSpacing('1.1');
                                    }}
                                    className="h-6 text-[10px] px-2"
                                >
                                    Default
                                </Button>
                            </div>

                            <div className="grid grid-cols-2 gap-2 text-xs">
                                <div className="space-y-1">
                                    <Label className="text-xs">Atas (mm)</Label>
                                    <Input
                                        type="number"
                                        value={marginTop}
                                        onChange={(e) => setMarginTop(Number(e.target.value))}
                                        className="h-7 text-xs"
                                        min={0}
                                        max={40}
                                    />
                                </div>
                                <div className="space-y-1">
                                    <Label className="text-xs">Bawah (mm)</Label>
                                    <Input
                                        type="number"
                                        value={marginBottom}
                                        onChange={(e) => setMarginBottom(Number(e.target.value))}
                                        className="h-7 text-xs"
                                        min={0}
                                        max={40}
                                    />
                                </div>
                                <div className="space-y-1">
                                    <Label className="text-xs">Kiri (mm)</Label>
                                    <Input
                                        type="number"
                                        value={marginLeft}
                                        onChange={(e) => setMarginLeft(Number(e.target.value))}
                                        className="h-7 text-xs"
                                        min={0}
                                        max={40}
                                    />
                                </div>
                                <div className="space-y-1">
                                    <Label className="text-xs">Kanan (mm)</Label>
                                    <Input
                                        type="number"
                                        value={marginRight}
                                        onChange={(e) => setMarginRight(Number(e.target.value))}
                                        className="h-7 text-xs"
                                        min={0}
                                        max={40}
                                    />
                                </div>
                            </div>

                            <div className="space-y-1 text-xs">
                                <Label className="text-xs">Kerapatan Baris</Label>
                                <Select
                                    value={lineSpacing}
                                    onValueChange={setLineSpacing}
                                >
                                    <SelectTrigger className="h-7 text-xs">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="1.0" className="text-xs">Padat (1.0)</SelectItem>
                                        <SelectItem value="1.1" className="text-xs">Standar (1.1)</SelectItem>
                                        <SelectItem value="1.2" className="text-xs">Renggang (1.2)</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                    </div>

                    {/* Right Column: Tab View (8 Cols) */}
                    <div className="lg:col-span-8 space-y-4">
                        {/* Tab Headers */}
                        <div className="flex items-center justify-between border-b border-border pb-2">
                            <div className="flex items-center gap-2">
                                <Button
                                    variant={viewTab === 'form' ? 'default' : 'outline'}
                                    size="sm"
                                    onClick={() => setViewTab('form')}
                                    className="text-xs"
                                >
                                    <FileSpreadsheet className="h-3.5 w-3.5 mr-1.5" />
                                    Form Pengukuran
                                </Button>
                                <Button
                                    variant={viewTab === 'html' ? 'default' : 'outline'}
                                    size="sm"
                                    onClick={() => setViewTab('html')}
                                    className="text-xs"
                                >
                                    <FileCode2 className="h-3.5 w-3.5 mr-1.5" />
                                    Teks / Template HTML
                                </Button>
                                <Button
                                    variant={viewTab === 'preview' ? 'default' : 'outline'}
                                    size="sm"
                                    onClick={() => setViewTab('preview')}
                                    className="text-xs"
                                >
                                    <Eye className="h-3.5 w-3.5 mr-1.5" />
                                    Pratinjau Dokumen
                                </Button>
                            </div>

                            {viewTab === 'form' && (
                                <div className="flex items-center gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => setShowDiagram(!showDiagram)}
                                        className="h-7 text-xs"
                                    >
                                        {showDiagram ? 'Sembunyikan Diagram' : 'Tampilkan Diagram'}
                                    </Button>
                                    <Button
                                        variant="secondary"
                                        size="sm"
                                        onClick={handleFillSampleScan}
                                        className="h-7 text-xs"
                                        title="Isi data tabel sesuai dokumen scan resmi MAK #4"
                                    >
                                        <Sparkles className="h-3.5 w-3.5 mr-1.5 text-amber-500" />
                                        Isi Data Scan
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={handleResetToBlank}
                                        className="h-7 text-xs text-muted-foreground"
                                        title="Kosongkan nilai pengukuran tabel"
                                    >
                                        Bersihkan Nilai
                                    </Button>
                                </div>
                            )}
                        </div>

                        {/* TAB 1: FORM PENGUKURAN */}
                        {viewTab === 'form' && (
                            <div className="space-y-4">
                                {/* Skema Diagram Titik Ukur */}
                                {showDiagram && (
                                    <div className="rounded-lg border border-border bg-card p-4 space-y-2">
                                        <div className="flex items-center justify-between">
                                            <span className="text-xs font-semibold text-foreground flex items-center gap-1.5">
                                                <Activity className="h-3.5 w-3.5 text-emerald-600" />
                                                Skema Posisi Titik Ukur Vibrasi Generator & Mesin (A1 s/d G3)
                                            </span>
                                            <span className="text-[11px] text-muted-foreground">
                                                Generator: A1-C2 | Mesin: D1-G3
                                            </span>
                                        </div>
                                        <div className="rounded-md border border-border/80 bg-white dark:bg-zinc-950 p-2 flex justify-center items-center overflow-hidden">
                                            <img
                                                src="/images/har/vibration-diagram.png"
                                                alt="Skema Titik Pengukuran Vibrasi"
                                                className="max-h-[140px] w-auto object-contain"
                                            />
                                        </div>
                                    </div>
                                )}

                                {/* Tabel Pengukuran Interaktif */}
                                <div className="rounded-lg border border-border bg-card overflow-hidden shadow-sm">
                                    <div className="bg-muted/40 px-4 py-2.5 border-b border-border flex items-center justify-between">
                                        <div>
                                            <h3 className="text-xs font-bold text-foreground">
                                                TABEL PENGUKURAN GETARAN / VIBRASI
                                            </h3>
                                            <p className="text-[11px] text-muted-foreground">
                                                Nilai rata-rata (Avg) dihitung otomatis secara real-time: (Max + Min) / 2
                                            </p>
                                        </div>
                                        <Badge variant="outline" className="text-[11px]">
                                            16 Titik Pengukuran
                                        </Badge>
                                    </div>

                                    <div className="overflow-x-auto">
                                        <table className="w-full text-xs text-left border-collapse">
                                            <thead>
                                                <tr className="bg-muted/70 text-foreground border-b border-border text-[11px] font-semibold">
                                                    <th className="py-2 px-2 text-center border-r border-border w-10">
                                                        NO
                                                    </th>
                                                    <th className="py-2 px-3 border-r border-border min-w-[200px]">
                                                        TITIK PENGUKURAN
                                                    </th>
                                                    <th
                                                        colSpan={3}
                                                        className="py-1.5 px-1 text-center border-r border-border bg-emerald-50/50 dark:bg-emerald-950/20"
                                                    >
                                                        VERTIKAL
                                                    </th>
                                                    <th
                                                        colSpan={3}
                                                        className="py-1.5 px-1 text-center border-r border-border bg-blue-50/50 dark:bg-blue-950/20"
                                                    >
                                                        HORIZONTAL
                                                    </th>
                                                    <th className="py-2 px-2 text-center w-24">
                                                        KET
                                                    </th>
                                                </tr>
                                                <tr className="bg-muted/40 text-muted-foreground border-b border-border text-[10px]">
                                                    <th className="border-r border-border"></th>
                                                    <th className="border-r border-border"></th>
                                                    <th className="py-1 px-1 text-center border-r border-border w-16 bg-emerald-50/30 dark:bg-emerald-950/10">
                                                        Max
                                                    </th>
                                                    <th className="py-1 px-1 text-center border-r border-border w-16 bg-emerald-50/30 dark:bg-emerald-950/10">
                                                        Min
                                                    </th>
                                                    <th className="py-1 px-1 text-center border-r border-border w-16 font-bold text-foreground bg-emerald-100/50 dark:bg-emerald-900/30">
                                                        Avg
                                                    </th>
                                                    <th className="py-1 px-1 text-center border-r border-border w-16 bg-blue-50/30 dark:bg-blue-950/10">
                                                        Max
                                                    </th>
                                                    <th className="py-1 px-1 text-center border-r border-border w-16 bg-blue-50/30 dark:bg-blue-950/10">
                                                        Min
                                                    </th>
                                                    <th className="py-1 px-1 text-center border-r border-border w-16 font-bold text-foreground bg-blue-100/50 dark:bg-blue-900/30">
                                                        Avg
                                                    </th>
                                                    <th></th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-border">
                                                {measurements.map((row, idx) => {
                                                    const isVertDisabled = row.no >= 13;
                                                    const isHorizDisabled = row.no === 16;

                                                    return (
                                                        <tr
                                                            key={row.no}
                                                            className={`hover:bg-muted/30 transition-colors ${
                                                                idx % 2 === 1 ? 'bg-muted/10' : ''
                                                            }`}
                                                        >
                                                            {/* No */}
                                                            <td className="py-1.5 px-2 text-center font-semibold border-r border-border">
                                                                {row.no}
                                                            </td>

                                                            {/* Point & Code */}
                                                            <td className="py-1.5 px-3 border-r border-border">
                                                                <div className="font-medium text-foreground">
                                                                    {row.point}
                                                                </div>
                                                                {row.code && (
                                                                    <div className="text-[10px] text-muted-foreground font-mono">
                                                                        Titik: {row.code}
                                                                    </div>
                                                                )}
                                                            </td>

                                                            {/* Vertikal Max */}
                                                            <td className="p-1 border-r border-border">
                                                                {isVertDisabled ? (
                                                                    <div className="text-center text-muted-foreground font-semibold py-1">
                                                                        /
                                                                    </div>
                                                                ) : (
                                                                    <Input
                                                                        value={row.v_max}
                                                                        onChange={(e) =>
                                                                            handleMeasurementChange(
                                                                                idx,
                                                                                'v_max',
                                                                                e.target.value
                                                                            )
                                                                        }
                                                                        className="h-7 text-xs text-center px-1"
                                                                        placeholder="0.00"
                                                                    />
                                                                )}
                                                            </td>

                                                            {/* Vertikal Min */}
                                                            <td className="p-1 border-r border-border">
                                                                {isVertDisabled ? (
                                                                    <div className="text-center text-muted-foreground font-semibold py-1">
                                                                        /
                                                                    </div>
                                                                ) : (
                                                                    <Input
                                                                        value={row.v_min}
                                                                        onChange={(e) =>
                                                                            handleMeasurementChange(
                                                                                idx,
                                                                                'v_min',
                                                                                e.target.value
                                                                            )
                                                                        }
                                                                        className="h-7 text-xs text-center px-1"
                                                                        placeholder="0.00"
                                                                    />
                                                                )}
                                                            </td>

                                                            {/* Vertikal Avg */}
                                                            <td className="p-1 border-r border-border bg-emerald-50/40 dark:bg-emerald-950/20">
                                                                <div className="text-center font-semibold text-emerald-800 dark:text-emerald-300 py-1">
                                                                    {isVertDisabled ? '/' : row.v_avg || '-'}
                                                                </div>
                                                            </td>

                                                            {/* Horizontal Max */}
                                                            <td className="p-1 border-r border-border">
                                                                {isHorizDisabled ? (
                                                                    <div className="text-center text-muted-foreground font-semibold py-1">
                                                                        /
                                                                    </div>
                                                                ) : (
                                                                    <Input
                                                                        value={row.h_max}
                                                                        onChange={(e) =>
                                                                            handleMeasurementChange(
                                                                                idx,
                                                                                'h_max',
                                                                                e.target.value
                                                                            )
                                                                        }
                                                                        className="h-7 text-xs text-center px-1"
                                                                        placeholder="0.00"
                                                                    />
                                                                )}
                                                            </td>

                                                            {/* Horizontal Min */}
                                                            <td className="p-1 border-r border-border">
                                                                {isHorizDisabled ? (
                                                                    <div className="text-center text-muted-foreground font-semibold py-1">
                                                                        /
                                                                    </div>
                                                                ) : (
                                                                    <Input
                                                                        value={row.h_min}
                                                                        onChange={(e) =>
                                                                            handleMeasurementChange(
                                                                                idx,
                                                                                'h_min',
                                                                                e.target.value
                                                                            )
                                                                        }
                                                                        className="h-7 text-xs text-center px-1"
                                                                        placeholder="0.00"
                                                                    />
                                                                )}
                                                            </td>

                                                            {/* Horizontal Avg */}
                                                            <td className="p-1 border-r border-border bg-blue-50/40 dark:bg-blue-950/20">
                                                                <div className="text-center font-semibold text-blue-800 dark:text-blue-300 py-1">
                                                                    {isHorizDisabled ? '/' : row.h_avg || '-'}
                                                                </div>
                                                            </td>

                                                            {/* Keterangan */}
                                                            <td className="p-1">
                                                                <Input
                                                                    value={row.note}
                                                                    onChange={(e) =>
                                                                        handleMeasurementChange(
                                                                            idx,
                                                                            'note',
                                                                            e.target.value
                                                                        )
                                                                    }
                                                                    className="h-7 text-xs px-1 text-center"
                                                                    placeholder="-"
                                                                />
                                                            </td>
                                                        </tr>
                                                    );
                                                })}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                {/* Standar & Kesimpulan Bawah */}
                                <div className="rounded-lg border border-border bg-card p-4 space-y-3">
                                    <h3 className="text-xs font-bold text-foreground">
                                        STANDAR, MAKSIMUM, DAN KESIMPULAN
                                    </h3>
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs">
                                        <div className="space-y-1">
                                            <Label className="text-xs">Standar Getaran / Vibrasi</Label>
                                            <textarea
                                                value={standardText}
                                                onChange={(e) => setStandardText(e.target.value)}
                                                placeholder="Standar ISO 10816-6 atau rekomendasi pabrikan MAK..."
                                                rows={2}
                                                className="w-full rounded-md border border-input bg-background p-2 text-xs focus:outline-none focus:ring-1 focus:ring-ring resize-none"
                                            />
                                        </div>
                                        <div className="space-y-1">
                                            <Label className="text-xs">Maksimum Yang Diizinkan</Label>
                                            <textarea
                                                value={maxText}
                                                onChange={(e) => setMaxText(e.target.value)}
                                                placeholder="Batas getaran maksimum yang diizinkan..."
                                                rows={2}
                                                className="w-full rounded-md border border-input bg-background p-2 text-xs focus:outline-none focus:ring-1 focus:ring-ring resize-none"
                                            />
                                        </div>
                                    </div>
                                    <div className="space-y-1 text-xs">
                                        <Label className="text-xs">Kesimpulan / Rekomendasi Hasil Pengukuran</Label>
                                        <textarea
                                            value={conclusionText}
                                            onChange={(e) => setConclusionText(e.target.value)}
                                            placeholder="Catatan kondisi vibrasi, evaluasi getaran bearing, atau rekomendasi perbaikan..."
                                            rows={3}
                                            className="w-full rounded-md border border-input bg-background p-2 text-xs focus:outline-none focus:ring-1 focus:ring-ring resize-none"
                                        />
                                    </div>
                                </div>
                            </div>
                        )}

                        {/* TAB 2: TEKS / TEMPLATE HTML */}
                        {viewTab === 'html' && (
                            <div className="rounded-lg border border-border bg-card p-4 space-y-4">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <h3 className="text-xs font-bold text-foreground">
                                            EDITOR TEMPLATE HTML DOKUMEN
                                        </h3>
                                        <p className="text-[11px] text-muted-foreground">
                                            Mengedit template HTML secara langsung akan menyimpan dokumen dalam format kustom HTML.
                                        </p>
                                    </div>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => setHtmlContent(rendered_html)}
                                        className="h-7 text-xs"
                                    >
                                        Muat Ulang Template Standar
                                    </Button>
                                </div>

                                <div className="rounded-lg border border-border overflow-hidden">
                                    <RichTextEditor
                                        value={htmlContent}
                                        onChange={setHtmlContent}
                                    />
                                </div>
                            </div>
                        )}

                        {/* TAB 3: PRATINJAU DOKUMEN (PDF PREVIEW) */}
                        {viewTab === 'preview' && (
                            <div className="rounded-lg border border-border bg-card overflow-hidden shadow-sm">
                                <div className="bg-muted/40 px-4 py-2.5 border-b border-border flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                        <Eye className="h-4 w-4 text-emerald-600" />
                                        <span className="text-xs font-bold text-foreground">
                                            Pratinjau Hasil Cetak PDF (A4 Portrait)
                                        </span>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => setPreviewKey((k) => k + 1)}
                                            className="h-7 text-xs"
                                        >
                                            <RefreshCw className="h-3.5 w-3.5 mr-1" />
                                            Segarkan Pratinjau
                                        </Button>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => window.open(previewPdfUrl, '_blank')}
                                            className="h-7 text-xs"
                                        >
                                            <ExternalLink className="h-3.5 w-3.5 mr-1" />
                                            Buka Layar Penuh
                                        </Button>
                                        <Button
                                            variant="default"
                                            size="sm"
                                            onClick={() => window.open(`${previewPdfUrl}&download=1`, '_blank')}
                                            className="h-7 text-xs bg-emerald-600 hover:bg-emerald-700 text-white"
                                        >
                                            <Download className="h-3.5 w-3.5 mr-1" />
                                            Unduh Dokumen
                                        </Button>
                                    </div>
                                </div>

                                <div className="w-full bg-zinc-100 dark:bg-zinc-900 p-2">
                                    <iframe
                                        key={previewKey}
                                        src={previewPdfUrl}
                                        className="w-full rounded border border-border bg-white shadow-inner"
                                        style={{ height: '850px', minHeight: '850px' }}
                                        title="Pratinjau PDF Pengukuran Vibrasi"
                                    />
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            </div>

            {/* Riwayat Modal / Dialog */}
            <Dialog open={isHistoryOpen} onOpenChange={setIsHistoryOpen}>
                <DialogContent className="max-w-3xl">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2 text-base">
                            <History className="h-4 w-4 text-emerald-600" />
                            Riwayat Pengukuran Vibrasi — {unit.name}
                        </DialogTitle>
                        <DialogDescription className="text-xs">
                            Daftar formulir vibrasi yang telah tersimpan untuk unit ini.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="max-h-[400px] overflow-y-auto">
                        {history.length === 0 ? (
                            <div className="py-8 text-center text-xs text-muted-foreground">
                                Belum ada riwayat pengukuran vibrasi tersimpan untuk unit ini.
                            </div>
                        ) : (
                            <table className="w-full text-xs text-left">
                                <thead>
                                    <tr className="border-b border-border bg-muted/40 text-[11px] font-semibold">
                                        <th className="py-2 px-3">Tanggal Uji</th>
                                        <th className="py-2 px-3">Mesin</th>
                                        <th className="py-2 px-3">No. Dokumen</th>
                                        <th className="py-2 px-3">Format</th>
                                        <th className="py-2 px-3">Terakhir Diperbarui</th>
                                        <th className="py-2 px-3 text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {history.map((h) => {
                                        const mach = machines.find((m) => m.id === h.machine_id);
                                        return (
                                            <tr key={h.id} className="hover:bg-muted/20">
                                                <td className="py-2 px-3 font-semibold">
                                                    {h.test_date}
                                                </td>
                                                <td className="py-2 px-3">
                                                    {mach?.name || `Mesin #${h.machine_id}`}
                                                </td>
                                                <td className="py-2 px-3 font-mono text-[11px]">
                                                    {h.document_number} (Rev: {h.revision})
                                                </td>
                                                <td className="py-2 px-3">
                                                    <Badge variant="outline" className="text-[10px]">
                                                        {h.format.toUpperCase()}
                                                    </Badge>
                                                </td>
                                                <td className="py-2 px-3 text-muted-foreground text-[11px]">
                                                    {h.updated_at}
                                                </td>
                                                <td className="py-2 px-3 text-right">
                                                    <div className="flex items-center justify-end gap-1.5">
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            className="h-6 text-[11px] px-2"
                                                            onClick={() => {
                                                                setIsHistoryOpen(false);
                                                                router.get(
                                                                    vibrationRoutes.index().url,
                                                                    {
                                                                        unit_id: unit.id,
                                                                        machine_id: h.machine_id,
                                                                        test_date: h.test_date,
                                                                        record_id: h.id,
                                                                    },
                                                                    { preserveState: false }
                                                                );
                                                            }}
                                                        >
                                                            Buka
                                                        </Button>
                                                        {can_write && (
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                className="h-6 w-6 text-destructive hover:bg-destructive/10"
                                                                onClick={() => {
                                                                    if (
                                                                        confirm(
                                                                            `Hapus dokumen tanggal ${h.test_date}?`
                                                                        )
                                                                    ) {
                                                                        router.delete(
                                                                            vibrationRoutes.destroy({
                                                                                vibration: h.id,
                                                                            }).url,
                                                                            {
                                                                                preserveScroll: true,
                                                                            }
                                                                        );
                                                                    }
                                                                }}
                                                            >
                                                                <Trash2 className="h-3.5 w-3.5" />
                                                            </Button>
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        )}
                    </div>
                </DialogContent>
            </Dialog>
        </div>
    );
}
