<?php

namespace App\Http\Controllers\Logistik;

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
            $user->hasPermissionTo(PermissionName::LogistikInputView) ||
            $user->hasPermissionTo(PermissionName::LogistikInputWrite),
            403
        );

        return Inertia::render('logistik/input/index');
    }
}
