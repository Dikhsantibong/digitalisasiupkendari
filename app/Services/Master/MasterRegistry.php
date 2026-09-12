<?php

namespace App\Services\Master;

/**
 * A catalogue of config-driven master resources for one module. The field
 * schema of each resource drives both the validation and the generated form, so
 * a new master needs no new controller or page — only a registry entry.
 */
interface MasterRegistry
{
    /**
     * Every resource, keyed by slug.
     *
     * @return array<string, array<string, mixed>>
     */
    public function resources(): array;

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $slug): ?array;

    /**
     * A lightweight description of every resource for the tab bar.
     *
     * @return list<array{slug: string, label: string, unit_scoped: bool}>
     */
    public function summaries(): array;
}
