import { Head, router } from '@inertiajs/react';
import {
    ArrowLeft,
    BatteryCharging,
    Check,
    Download,
    Eye,
    ExternalLink,
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
import batteryVoltageRoutes from '@/routes/har/formulir/battery-voltage';
import harFormulir from '@/routes/har/formulir';
import type { IdName } from '@/types';

type BatteryCell = {
    cell: number;
    voltage: string;
};

type BatterySummary = {
    max: string;
    min: string;
    total: string;
};

type ChargingCondition = {
    mode: 'FLOATING' | 'EQUILIZING' | 'BOOSTING';
    item: 'Rectifier' | 'Load' | 'Battery';
    cond_24v: string;
    cond_110v: string;
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
        cells_24v: BatteryCell[];
        summary_24v: BatterySummary;
        cells_110v: BatteryCell[];
        summary_110v: BatterySummary;
        charging_conditions: ChargingCondition[];
        notes: string;
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
    sample_scan_cells_24v: BatteryCell[];
    sample_scan_cells_110v: BatteryCell[];
    can_write: boolean;
};

export default function BatteryVoltageIndex({
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
    sample_scan_cells_24v,
    sample_scan_cells_110v,
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
        form_data.document_number || 'SMT-FM-KIT-02.07'
    );
    const [revision, setRevision] = useState<string>(
        form_data.revision || '01'
    );
    const [effectiveDate, setEffectiveDate] = useState<string>(
        form_data.effective_date || '13 Oktober 2021'
    );

    // Machine Technical Specs
    const [brand, setBrand] = useState<string>(form_data.brand || 'MAK');
    const [modelType, setModelType] = useState<string>(
        form_data.model_type || '8M 453 AK'
    );
    const [serialNumber, setSerialNumber] = useState<string>(
        form_data.serial_number || ''
    );
    const [machineNumber, setMachineNumber] = useState<string>(
        form_data.machine_number || '1,2,3'
    );
    const [installedPower, setInstalledPower] = useState<string>(
        form_data.installed_power || '2544'
    );
    const [capablePower, setCapablePower] = useState<string>(
        form_data.capable_power || ''
    );
    const [rpm, setRpm] = useState<string>(form_data.rpm || '600');

    // 24V Cells & Summary
    const [cells24v, setCells24v] = useState<BatteryCell[]>(() => {
        if (form_data.cells_24v && form_data.cells_24v.length === 12) {
            return form_data.cells_24v;
        }
        return Array.from({ length: 12 }, (_, i) => ({ cell: i + 1, voltage: '' }));
    });

    // 110V Cells & Summary
    const [cells110v, setCells110v] = useState<BatteryCell[]>(() => {
        if (form_data.cells_110v && form_data.cells_110v.length === 55) {
            return form_data.cells_110v;
        }
        return Array.from({ length: 55 }, (_, i) => ({ cell: i + 1, voltage: '' }));
    });

    // Charging Conditions (9 rows)
    const [chargingConditions, setChargingConditions] = useState<ChargingCondition[]>(
        form_data.charging_conditions || []
    );

    // General Notes
    const [generalNotes, setGeneralNotes] = useState<string>(form_data.notes || '');

    // Signatories
    const [managerUlId, setManagerUlId] = useState<string>(
        form_data.manager_ul_id ? String(form_data.manager_ul_id) : ''
    );
    const [managerUlName, setManagerUlName] = useState<string>(
        form_data.manager_ul_name || ''
    );
    const [managerUlTitle, setManagerUlTitle] = useState<string>(
        form_data.manager_ul_title || 'Plh.Manager Unit PLTD Wua-wua'
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

    // View tab ('form' | 'html' | 'pdf')
    const [viewTab, setViewTab] = useState<'form' | 'html' | 'pdf'>(
        record?.format === 'html' ? 'html' : 'form'
    );
    const [isSaving, setIsSaving] = useState<boolean>(false);
    const [showHistory, setShowHistory] = useState<boolean>(false);
    const [previewKey, setPreviewKey] = useState<number>(Date.now());

    // Compute automatic summaries
    const summary24v = useMemo<BatterySummary>(() => {
        const nums = cells24v
            .map((c) => parseFloat(c.voltage.replace(',', '.')))
            .filter((n) => !isNaN(n));
        if (nums.length === 0) return { max: '', min: '', total: '' };
        return {
            max: String(Math.max(...nums)),
            min: String(Math.min(...nums)),
            total: String(Math.round(nums.reduce((a, b) => a + b, 0) * 100) / 100),
        };
    }, [cells24v]);

    const summary110v = useMemo<BatterySummary>(() => {
        const nums = cells110v
            .map((c) => parseFloat(c.voltage.replace(',', '.')))
            .filter((n) => !isNaN(n));
        if (nums.length === 0) return { max: '', min: '', total: '' };
        return {
            max: String(Math.max(...nums)),
            min: String(Math.min(...nums)),
            total: String(Math.round(nums.reduce((a, b) => a + b, 0) * 100) / 100),
        };
    }, [cells110v]);

    // Update 24V cell voltage
    const updateCell24v = (cellNumber: number, voltage: string) => {
        setCells24v((prev) =>
            prev.map((c) => (c.cell === cellNumber ? { ...c, voltage } : c))
        );
    };

    // Update 110V cell voltage
    const updateCell110v = (cellNumber: number, voltage: string) => {
        setCells110v((prev) =>
            prev.map((c) => (c.cell === cellNumber ? { ...c, voltage } : c))
        );
    };

    // Update charging condition
    const updateChargingCondition = (
        index: number,
        field: keyof Omit<ChargingCondition, 'mode' | 'item'>,
        val: string
    ) => {
        setChargingConditions((prev) =>
            prev.map((row, i) => (i === index ? { ...row, [field]: val } : row))
        );
    };

    // Quick fill sample scan data
    const handleFillSampleScan = () => {
        setCells24v(sample_scan_cells_24v);
        setCells110v(sample_scan_cells_110v);
        setGeneralNotes(
            'Kondisi sel baterai 24V dan 110V dalam batas normal, elektrolit cukup, terminal bersih dan bebas korosi.'
        );
    };

    // Reset to blank standard template
    const handleResetToStandard = () => {
        setCells24v(Array.from({ length: 12 }, (_, i) => ({ cell: i + 1, voltage: '' })));
        setCells110v(Array.from({ length: 55 }, (_, i) => ({ cell: i + 1, voltage: '' })));
        setChargingConditions([
            { mode: 'FLOATING', item: 'Rectifier', cond_24v: '', cond_110v: '', notes: '' },
            { mode: 'FLOATING', item: 'Load', cond_24v: '', cond_110v: '', notes: '' },
            { mode: 'FLOATING', item: 'Battery', cond_24v: '', cond_110v: '', notes: '' },
            { mode: 'EQUILIZING', item: 'Rectifier', cond_24v: '', cond_110v: '', notes: '' },
            { mode: 'EQUILIZING', item: 'Load', cond_24v: '', cond_110v: '', notes: '' },
            { mode: 'EQUILIZING', item: 'Battery', cond_24v: '', cond_110v: '', notes: '' },
            { mode: 'BOOSTING', item: 'Rectifier', cond_24v: '', cond_110v: '', notes: '' },
            { mode: 'BOOSTING', item: 'Load', cond_24v: '', cond_110v: '', notes: '' },
            { mode: 'BOOSTING', item: 'Battery', cond_24v: '', cond_110v: '', notes: '' },
        ]);
        setGeneralNotes('');
    };

    // When unit changes in selector
    const handleUnitChange = (newUnitId: string) => {
        router.get(
            batteryVoltageRoutes.index().url,
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
                batteryVoltageRoutes.index().url,
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
            batteryVoltageRoutes.index().url,
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
                emp.position || `Plh.Manager UL ${unit.service_unit_name || unit.name}`
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
        params.set('brand', brand);
        params.set('model_type', modelType);
        params.set('serial_number', serialNumber);
        params.set('machine_number', machineNumber);
        params.set('installed_power', installedPower);
        params.set('capable_power', capablePower);
        params.set('rpm', rpm);
        params.set('notes', generalNotes);
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

        // 24V cells & summary
        cells24v.forEach((c, idx) => {
            params.set(`cells_24v[${idx}][cell]`, String(c.cell));
            params.set(`cells_24v[${idx}][voltage]`, c.voltage);
        });
        params.set('summary_24v[max]', summary24v.max);
        params.set('summary_24v[min]', summary24v.min);
        params.set('summary_24v[total]', summary24v.total);

        // 110V cells & summary
        cells110v.forEach((c, idx) => {
            params.set(`cells_110v[${idx}][cell]`, String(c.cell));
            params.set(`cells_110v[${idx}][voltage]`, c.voltage);
        });
        params.set('summary_110v[max]', summary110v.max);
        params.set('summary_110v[min]', summary110v.min);
        params.set('summary_110v[total]', summary110v.total);

        // Charging conditions
        chargingConditions.forEach((cc, idx) => {
            params.set(`charging_conditions[${idx}][mode]`, cc.mode);
            params.set(`charging_conditions[${idx}][item]`, cc.item);
            params.set(`charging_conditions[${idx}][cond_24v]`, cc.cond_24v || '');
            params.set(`charging_conditions[${idx}][cond_110v]`, cc.cond_110v || '');
            params.set(`charging_conditions[${idx}][notes]`, cc.notes || '');
        });

        return `${pdf_url}${separator}${params.toString()}`;
    }, [
        pdf_url,
        previewKey,
        brand,
        modelType,
        serialNumber,
        machineNumber,
        installedPower,
        capablePower,
        rpm,
        generalNotes,
        cells24v,
        summary24v,
        cells110v,
        summary110v,
        chargingConditions,
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
            cells_24v: cells24v,
            summary_24v: summary24v,
            cells_110v: cells110v,
            summary_110v: summary110v,
            charging_conditions: chargingConditions,
            notes: generalNotes,
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

        router.post(batteryVoltageRoutes.store().url, payload, {
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
            <Head title={`Formulir Pengukuran Tegangan Battery - ${unit.name}`} />

            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Formulir Pengukuran Tegangan Battery"
                    description="Pencatatan pengukuran tegangan baterai 24V dan 110V, kondisi rectifier charger floating/equilizing/boosting, dan cetak PDF resmi."
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
                                    Informasi dasar unit, mesin, dan tanggal pengujian baterai.
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

                                {/* Tanggal Pengukuran */}
                                <div className="space-y-1">
                                    <Label className="text-xs">Tanggal Pengukuran</Label>
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

                                {/* Spesifikasi Mesin */}
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
                                                placeholder="No. seri"
                                                className="h-7 text-xs"
                                            />
                                        </div>
                                        <div className="space-y-1">
                                            <Label className="text-[11px]">Mesin No</Label>
                                            <Input
                                                value={machineNumber}
                                                onChange={(e) => setMachineNumber(e.target.value)}
                                                placeholder="1,2,3"
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
                                </div>
                            </div>
                        </div>

                        {/* Card 2: Penandatangan Dokumen */}
                        <div className="rounded-lg border border-border bg-card p-4 space-y-4">
                            <div className="border-b border-border pb-2">
                                <h3 className="text-sm font-semibold text-foreground">
                                    Penandatangan Dokumen
                                </h3>
                                <p className="text-[12px] text-muted-foreground">
                                    3 Kolom Penandatangan: Mengetahui, Diperiksa, dan Dibuat.
                                </p>
                            </div>

                            <div className="space-y-4 text-xs">
                                {/* 1. Manager UL (Mengetahui - Kiri) */}
                                <div className="space-y-2 rounded-md border border-border/70 p-2.5 bg-muted/20">
                                    <div className="flex items-center justify-between">
                                        <Label className="font-semibold text-foreground">
                                            1. Mengetahui (Plh. Manager UL)
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
                                            placeholder="Nama Manager"
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

                                {/* 2. Team Leader Pemeliharaan (Diperiksa - Tengah) */}
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
                                            placeholder="Nama TL"
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

                        {/* Card 3: Page Settings */}
                        <div className="rounded-lg border border-border bg-card p-4 space-y-4">
                            <div className="border-b border-border pb-2">
                                <h3 className="text-sm font-semibold text-foreground">
                                    Page Settings
                                </h3>
                                <p className="text-[12px] text-muted-foreground">
                                    Pengaturan layout &amp; margin PDF cetak agar presisi 1 halaman.
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
                                            <SelectItem value="1.1">1,1 (Standar Baterai)</SelectItem>
                                            <SelectItem value="1.15">1,15 (ISO)</SelectItem>
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
                                        Pengukuran tegangan sel baterai 24V (12 sel), 110V (55 sel), kondisi charger, dan tanda tangan.
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
                                <div className="p-4 space-y-6">
                                    {/* Action toolbar */}
                                    <div className="flex flex-wrap items-center justify-between gap-3 bg-muted/40 p-3 rounded-lg border border-border">
                                        <div className="flex items-center gap-2">
                                            <BatteryCharging className="size-4 text-primary" />
                                            <span className="text-xs font-semibold text-foreground">
                                                Matriks Pengukuran Tegangan Sel Baterai
                                            </span>
                                        </div>

                                        <div className="flex flex-wrap items-center gap-2">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={handleResetToStandard}
                                                className="h-7 text-xs gap-1.5"
                                            >
                                                <RotateCcw className="size-3.5" />
                                                Reset Form
                                            </Button>

                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={handleFillSampleScan}
                                                className="h-7 text-xs gap-1.5 text-blue-600 dark:text-blue-400 border-blue-200 dark:border-blue-900 bg-blue-50/50 dark:bg-blue-950/30"
                                            >
                                                <Sparkles className="size-3.5" />
                                                Isi Contoh Scan
                                            </Button>
                                        </div>
                                    </div>

                                    {/* 1. BAGIAN 24 VOLT */}
                                    <div className="space-y-3 rounded-lg border border-border bg-card p-3.5">
                                        <div className="flex flex-wrap items-center justify-between gap-2 border-b border-border pb-2">
                                            <div className="flex items-center gap-2">
                                                <span className="px-2 py-0.5 rounded bg-primary text-primary-foreground font-bold text-xs">
                                                    24 VOLT
                                                </span>
                                                <span className="text-xs text-muted-foreground font-medium">
                                                    (12 Sel Tegangan Baterai Starter)
                                                </span>
                                            </div>
                                            <div className="flex items-center gap-3 text-xs">
                                                <span>
                                                    Tertinggi: <strong className="text-foreground">{summary24v.max || '—'} V</strong>
                                                </span>
                                                <span>•</span>
                                                <span>
                                                    Terendah: <strong className="text-foreground">{summary24v.min || '—'} V</strong>
                                                </span>
                                                <span>•</span>
                                                <span>
                                                    Total: <strong className="text-primary">{summary24v.total || '—'} V</strong>
                                                </span>
                                            </div>
                                        </div>

                                        <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-2 pt-1">
                                            {cells24v.map((c) => (
                                                <div
                                                    key={c.cell}
                                                    className="flex items-center rounded-md border border-border overflow-hidden bg-background"
                                                >
                                                    <span className="w-8 text-center bg-muted py-1.5 text-[11px] font-bold border-r border-border text-muted-foreground shrink-0">
                                                        {c.cell}
                                                    </span>
                                                    <Input
                                                        value={c.voltage}
                                                        onChange={(e) => updateCell24v(c.cell, e.target.value)}
                                                        placeholder="2.2"
                                                        className="h-8 border-0 rounded-none text-xs text-center font-semibold focus-visible:ring-0 shadow-none px-1"
                                                    />
                                                </div>
                                            ))}
                                        </div>
                                    </div>

                                    {/* 2. BAGIAN 110 VOLT */}
                                    <div className="space-y-3 rounded-lg border border-border bg-card p-3.5">
                                        <div className="flex flex-wrap items-center justify-between gap-2 border-b border-border pb-2">
                                            <div className="flex items-center gap-2">
                                                <span className="px-2 py-0.5 rounded bg-blue-600 text-white font-bold text-xs">
                                                    110 VOLT
                                                </span>
                                                <span className="text-xs text-muted-foreground font-medium">
                                                    (55 Sel Tegangan Baterai Kontrol DC)
                                                </span>
                                            </div>
                                            <div className="flex items-center gap-3 text-xs">
                                                <span>
                                                    Tertinggi: <strong className="text-foreground">{summary110v.max || '—'} V</strong>
                                                </span>
                                                <span>•</span>
                                                <span>
                                                    Terendah: <strong className="text-foreground">{summary110v.min || '—'} V</strong>
                                                </span>
                                                <span>•</span>
                                                <span>
                                                    Total: <strong className="text-blue-600 dark:text-blue-400">{summary110v.total || '—'} V</strong>
                                                </span>
                                            </div>
                                        </div>

                                        <div className="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-8 gap-2 pt-1">
                                            {cells110v.map((c) => (
                                                <div
                                                    key={c.cell}
                                                    className="flex items-center rounded-md border border-border overflow-hidden bg-background"
                                                >
                                                    <span className="w-7 text-center bg-muted py-1 text-[10px] font-bold border-r border-border text-muted-foreground shrink-0">
                                                        {c.cell}
                                                    </span>
                                                    <Input
                                                        value={c.voltage}
                                                        onChange={(e) => updateCell110v(c.cell, e.target.value)}
                                                        placeholder="2.1"
                                                        className="h-7 border-0 rounded-none text-xs text-center font-semibold focus-visible:ring-0 shadow-none px-1"
                                                    />
                                                </div>
                                            ))}
                                        </div>
                                    </div>

                                    {/* 3. TABEL KONDISI CHARGING / RECTIFIER */}
                                    <div className="space-y-3 rounded-lg border border-border bg-card p-3.5">
                                        <div className="border-b border-border pb-2">
                                            <h4 className="text-xs font-semibold text-foreground">
                                                Kondisi Rectifier &amp; Charger (Floating, Equilizing, Boosting)
                                            </h4>
                                            <p className="text-[11px] text-muted-foreground">
                                                Input parameter kondisi tegangan/arus untuk sistem 24V dan 110V.
                                            </p>
                                        </div>

                                        <div className="overflow-x-auto rounded-md border border-border">
                                            <table className="w-full text-xs text-left">
                                                <thead className="bg-muted/80 text-muted-foreground uppercase text-[10px] tracking-wider border-b border-border">
                                                    <tr>
                                                        <th className="p-2 w-28 border-r border-border">Mode</th>
                                                        <th className="p-2 w-24 border-r border-border">Item</th>
                                                        <th className="p-2 w-32 text-center border-r border-border">24 VOLT</th>
                                                        <th className="p-2 w-32 text-center border-r border-border">110 VOLT</th>
                                                        <th className="p-2">Keterangan</th>
                                                    </tr>
                                                </thead>
                                                <tbody className="divide-y divide-border">
                                                    {chargingConditions.map((row, idx) => (
                                                        <tr key={idx} className="hover:bg-muted/30 transition-colors">
                                                            <td className="p-2 font-bold border-r border-border text-muted-foreground">
                                                                {row.mode}
                                                            </td>
                                                            <td className="p-2 font-semibold border-r border-border">
                                                                {row.item}
                                                            </td>
                                                            <td className="p-1.5 border-r border-border">
                                                                <Input
                                                                    value={row.cond_24v}
                                                                    onChange={(e) => updateChargingCondition(idx, 'cond_24v', e.target.value)}
                                                                    placeholder="Kondisi 24V"
                                                                    className="h-7 text-xs text-center"
                                                                />
                                                            </td>
                                                            <td className="p-1.5 border-r border-border">
                                                                <Input
                                                                    value={row.cond_110v}
                                                                    onChange={(e) => updateChargingCondition(idx, 'cond_110v', e.target.value)}
                                                                    placeholder="Kondisi 110V"
                                                                    className="h-7 text-xs text-center"
                                                                />
                                                            </td>
                                                            <td className="p-1.5">
                                                                <Input
                                                                    value={row.notes}
                                                                    onChange={(e) => updateChargingCondition(idx, 'notes', e.target.value)}
                                                                    placeholder="Catatan / keterangan..."
                                                                    className="h-7 text-xs"
                                                                />
                                                            </td>
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>

                                    {/* 4. GENERAL NOTES */}
                                    <div className="space-y-2 rounded-lg border border-border bg-card p-4">
                                        <div className="flex items-center justify-between">
                                            <Label className="text-xs font-semibold text-foreground">
                                                Catatan Tambahan (Bawah Tabel)
                                            </Label>
                                            <span className="text-[11px] text-muted-foreground">
                                                Muncul pada kotak Catatan formulir cetak PDF
                                            </span>
                                        </div>
                                        <textarea
                                            value={generalNotes}
                                            onChange={(e) => setGeneralNotes(e.target.value)}
                                            placeholder="Tuliskan catatan teknis kondisi baterai dan sistem DC charger..."
                                            rows={2}
                                            className="w-full rounded-md border border-input bg-background p-2.5 text-xs focus:outline-none focus:ring-1 focus:ring-ring"
                                        />
                                    </div>
                                </div>
                            )}

                            {/* TAB 2: RICH TEXT HTML EDITOR */}
                            {viewTab === 'html' && (
                                <div className="p-4 space-y-3">
                                    <div className="rounded-md border border-amber-500/30 bg-amber-500/10 p-3 text-xs text-amber-800 dark:text-amber-300">
                                        <Info className="size-4 inline mr-1 text-amber-600 dark:text-amber-400" />
                                        <strong>Mode Editor Teks Bebas:</strong> Anda dapat mengedit langsung kode HTML formulir baterai ini.
                                    </div>
                                    <RichTextEditor
                                        value={htmlContent}
                                        onChange={setHtmlContent}
                                    />
                                </div>
                            )}

                            {/* TAB 3: PDF PREVIEW IFRAME */}
                            {viewTab === 'pdf' && (
                                <div className="p-4 flex flex-col gap-3">
                                    <div className="flex items-center justify-between bg-muted/40 p-2.5 rounded-lg border border-border">
                                        <div className="text-xs text-muted-foreground">
                                            Menampilkan pratinjau dokumen PDF secara real-time.
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={() => window.open(previewPdfUrl, '_blank')}
                                                className="h-7 text-xs gap-1.5"
                                            >
                                                <ExternalLink className="size-3.5" />
                                                Buka Layar Penuh
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={() => setPreviewKey(Date.now())}
                                                className="h-7 text-xs gap-1.5"
                                            >
                                                <RotateCcw className="size-3.5" />
                                                Segarkan Pratinjau
                                            </Button>
                                        </div>
                                    </div>
                                    <div
                                        className="w-full rounded-lg border border-border overflow-hidden bg-white shadow-sm"
                                        style={{ height: '850px', minHeight: '850px' }}
                                    >
                                        <iframe
                                            key={previewKey}
                                            src={`${previewPdfUrl}#toolbar=0&navpanes=0`}
                                            style={{ width: '100%', height: '100%', minHeight: '850px', border: 'none' }}
                                            title="PDF Preview"
                                        />
                                    </div>
                                </div>
                            )}

                            {/* Bottom Card Bar */}
                            <div className="flex flex-wrap items-center justify-between gap-3 border-t border-border p-4 bg-muted/20 rounded-b-lg">
                                <div className="text-xs text-muted-foreground flex items-center gap-1.5">
                                    <BatteryCharging className="size-3.5 text-primary" />
                                    Data disimpan per Unit, Mesin, dan Tanggal Pengukuran.
                                </div>
                                <div className="flex flex-wrap items-center gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={handleResetToStandard}
                                        className="gap-1.5 text-xs"
                                    >
                                        <RotateCcw className="size-3.5" />
                                        Reset Form
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={handleFillSampleScan}
                                        className="gap-1.5 text-xs text-blue-600 dark:text-blue-400"
                                    >
                                        <Sparkles className="size-3.5" />
                                        Isi Contoh Data Scan
                                    </Button>
                                    {can_write && (
                                        <Button
                                            type="button"
                                            size="sm"
                                            onClick={() => handleSave()}
                                            disabled={isSaving}
                                            className="gap-1.5 text-xs"
                                        >
                                            <Save className="size-3.5" />
                                            {isSaving ? 'Menyimpan…' : 'Simpan Perubahan'}
                                        </Button>
                                    )}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Dialog Riwayat Pengukuran */}
                <Dialog open={showHistory} onOpenChange={setShowHistory}>
                    <DialogContent className="max-w-2xl">
                        <DialogHeader>
                            <DialogTitle className="flex items-center gap-2">
                                <History className="size-5 text-primary" />
                                Riwayat Data Pengukuran Tegangan Battery
                            </DialogTitle>
                            <DialogDescription>
                                Daftar arsip pengujian tegangan baterai untuk unit {unit.name}.
                            </DialogDescription>
                        </DialogHeader>

                        <div className="max-h-[380px] overflow-y-auto divide-y divide-border border rounded-md">
                            {history.length > 0 ? (
                                history.map((item) => (
                                    <div
                                        key={item.id}
                                        className="flex items-center justify-between p-3 hover:bg-muted/40 transition-colors"
                                    >
                                        <div className="space-y-1">
                                            <div className="font-semibold text-xs text-foreground flex items-center gap-2">
                                                <span>Tanggal: {item.test_date}</span>
                                                <Badge variant="outline" className="text-[10px] py-0">
                                                    {item.format === 'html' ? 'HTML Editor' : 'Form Terstruktur'}
                                                </Badge>
                                            </div>
                                            <div className="text-[11px] text-muted-foreground">
                                                No. Dok: {item.document_number} (Rev. {item.revision}) • Diperbarui: {item.updated_at}
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={() => {
                                                    setShowHistory(false);
                                                    router.get(
                                                        batteryVoltageRoutes.index().url,
                                                        {
                                                            unit_id: unit.id,
                                                            machine_id: item.machine_id,
                                                            record_id: item.id,
                                                            test_date: item.test_date,
                                                        },
                                                        { preserveState: false }
                                                    );
                                                }}
                                                className="h-7 text-xs gap-1"
                                            >
                                                <Eye className="size-3.5" />
                                                Buka
                                            </Button>

                                            {can_write && (
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => {
                                                        if (confirm('Apakah Anda yakin ingin menghapus arsip formulir ini?')) {
                                                            router.delete(
                                                                batteryVoltageRoutes.destroy({ batteryVoltage: item.id }).url,
                                                                {
                                                                    preserveScroll: true,
                                                                    onSuccess: () => setShowHistory(false),
                                                                }
                                                            );
                                                        }
                                                    }}
                                                    className="h-7 w-7 p-0 text-muted-foreground hover:text-destructive"
                                                >
                                                    <Trash2 className="size-3.5" />
                                                </Button>
                                            )}
                                        </div>
                                    </div>
                                ))
                            ) : (
                                <div className="p-6 text-center text-xs text-muted-foreground">
                                    Belum ada arsip riwayat formulir untuk mesin dan unit ini.
                                </div>
                            )}
                        </div>
                    </DialogContent>
                </Dialog>
            </div>
        </>
    );
}
