<?php

namespace App\Http\Controllers\K3;

use App\Enums\PermissionName;
use App\Http\Controllers\Concerns\ManagesMasterResources;
use App\Http\Controllers\Controller;
use App\Services\ActivityLogger;
use App\Services\K3\Master\K3MasterRegistry;
use App\Services\Master\MasterRegistry;

/**
 * The generic CRUD screen for K3 & security master data. Shares the engine in
 * {@see ManagesMasterResources}; only names the registry, permissions, and page.
 */
class MasterController extends Controller
{
    use ManagesMasterResources;

    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly K3MasterRegistry $registry,
    ) {}

    protected function masterRegistry(): MasterRegistry
    {
        return $this->registry;
    }

    protected function masterViewPermission(): PermissionName
    {
        return PermissionName::K3MasterViewAny;
    }

    protected function masterManagePermission(): PermissionName
    {
        return PermissionName::K3MasterManage;
    }

    protected function masterPage(): string
    {
        return 'k3/master/index';
    }
}
