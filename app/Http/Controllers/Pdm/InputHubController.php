<?php

namespace App\Http\Controllers\Pdm;

use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InputHubController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless(
            $user->hasPermissionTo(PermissionName::PdmInputView) ||
            $user->hasPermissionTo(PermissionName::PdmInputWrite),
            403
        );

        return Inertia::render('pdm/input/index');
    }
}
