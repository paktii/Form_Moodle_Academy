<?php

namespace App\Services;

use Illuminate\Support\Collection;

class DepartmentDirectory
{
    private ?Collection $departments = null;

    /**
     * Temporary department snapshot used until Server 199 integration is enabled.
     *
     * @return Collection<int, string>
     */
    public function options(): Collection
    {
        return $this->departments ??= collect(config('departments', []))
            ->mapWithKeys(fn ($name, $id) => [(int) $id => trim((string) $name)]);
    }

    public function name(int $departmentId): string
    {
        return $this->options()->get($departmentId, "หน่วยงาน #$departmentId");
    }
}
