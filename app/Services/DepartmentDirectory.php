<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class DepartmentDirectory
{
    private ?Collection $departments = null;

    private ?Collection $subdepartments = null;

    private ?Collection $subdepartmentParents = null;

    public function __construct(private readonly SwuMisApiClient $api) {}

    /** @return Collection<int, string> */
    public function options(): Collection
    {
        $this->load();

        return $this->departments;
    }

    public function name(int $departmentId): string
    {
        return $this->options()->get($departmentId, "หน่วยงาน #$departmentId");
    }

    /** @return Collection<int, string> */
    public function subdepartmentOptions(): Collection
    {
        $this->loadSubdepartments();

        return $this->subdepartments;
    }

    /** @return Collection<int, int> */
    public function subdepartmentParents(): Collection
    {
        $this->loadSubdepartments();

        return $this->subdepartmentParents;
    }

    /** @return Collection<int, string> */
    public function subdepartmentsFor(int $departmentId): Collection
    {
        return $this->subdepartmentOptions()->filter(
            fn (string $name, int $id) => $this->subdepartmentParents()->get($id) === $departmentId,
        );
    }

    public function subdepartmentName(int $subdepartmentId): string
    {
        return $this->subdepartmentOptions()->get($subdepartmentId, "หน่วยงานย่อย #$subdepartmentId");
    }

    private function loadSubdepartments(): void
    {
        $this->load();
    }

    private function load(): void
    {
        if ($this->departments !== null && $this->subdepartments !== null && $this->subdepartmentParents !== null) {
            return;
        }

        $this->departments = collect();
        $this->subdepartments = collect();
        $this->subdepartmentParents = collect();

        try {
            $rows = $this->api->all((string) config('swu_mis_api.endpoints.departments', 'Department'));

            foreach ($rows as $department) {
                if (strtoupper(trim((string) ($department['activeFlag'] ?? ''))) !== 'Y') {
                    continue;
                }

                $id = (int) ($department['deptCd'] ?? 0);
                $parentId = (int) ($department['indeptCd'] ?? 0);
                $name = trim((string) ($department['deptLnameTh'] ?? $department['deptSnameTh'] ?? ''));

                if ($id <= 0 || $name === '') {
                    continue;
                }

                if ($parentId <= 0 || $id === $parentId) {
                    $this->departments->put($id, $name);
                } else {
                    $this->subdepartments->put($id, $name);
                    $this->subdepartmentParents->put($id, $parentId);
                }
            }

            $this->departments = $this->departments->sort();
            $this->subdepartments = $this->subdepartments->sort();

            if ($this->departments->isNotEmpty()) {
                return;
            }
        } catch (Throwable $exception) {
            Log::warning('SWU MIS department API is unavailable; using the local snapshot.', [
                'exception' => $exception::class,
            ]);
        }

        $this->departments = collect(config('departments', []))
            ->mapWithKeys(fn ($name, $id) => [(int) $id => trim((string) $name)])
            ->filter(fn (string $name, int $id) => $id > 0 && $name !== '')
            ->sort();
    }
}
