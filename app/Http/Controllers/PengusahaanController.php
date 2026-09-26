<?php

namespace App\Http\Controllers;

use App\Enums\ReportModule;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Akses 2 — Pengusahaan (Team Leader & Staf) of Operasi, Pemeliharaan and K3:
 * one hub page per menu section (Jadwal, Input, Formulir) listing
 * the pages registered for it in resources/js/lib/pengusahaan-menus.ts. Each
 * module route file registers `pengusahaan/{section}` with a `module` default.
 * The Laporan Pengusahaan itself is opened only from the module's Laporan
 * page, next to the Laporan Pembangkit.
 */
class PengusahaanController extends Controller
{
    /** @var array<string, string> section key => label, in menu order */
    public const SECTIONS = [
        'jadwal' => 'Jadwal',
        'input' => 'Input',
        'formulir' => 'Formulir',
    ];

    /** @var array<string, string> module key => title */
    private const TITLES = [
        'operasi' => 'Operasi',
        'har' => 'Pemeliharaan',
        'k3' => 'K3 & Keamanan',
    ];

    public function index(Request $request, string $section): Response
    {
        $module = (string) $request->route('module');
        $permission = ReportModule::from($module)->pengusahaanPermission();
        abort_unless($permission !== null && $request->user()->hasPermissionTo($permission), 403);

        return Inertia::render('pengusahaan/index', [
            'module' => ['key' => $module, 'title' => self::TITLES[$module]],
            'section' => ['key' => $section, 'title' => self::SECTIONS[$section]],
        ]);
    }
}
