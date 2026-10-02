<?php

namespace App\Services;

use App\Models\SalaDepartment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class DepartmentDirectory
{
    private ?Collection $departments = null;

    private ?Collection $subdepartments = null;

    private ?Collection $subdepartmentParents = null;

    /** @return Collection<int, string> */
    public function options(): Collection
    {
        if ($this->departments !== null) {
            return $this->departments;
        }

        try {
            $departments = SalaDepartment::query()
                ->where('active_flag', 'Y')
                ->whereColumn('dept_cd', 'in_dept_cd')
                ->whereNotNull('dept_lname_th')
                ->orderBy('dept_lname_th')
                ->get(['dept_cd', 'dept_lname_th'])
                ->mapWithKeys(fn (SalaDepartment $department) => [
                    (int) $department->dept_cd => trim((string) $department->dept_lname_th),
                ])
                ->filter(fn (string $name, int $id) => $id > 0 && $name !== '');

            if ($departments->isNotEmpty()) {
                return $this->departments = $departments;
            }
        } catch (Throwable $exception) {
            Log::warning('DB199 department directory is unavailable; using the local snapshot.', [
                'exception' => $exception::class,
            ]);
        }

        return $this->departments = collect(config('departments', []))
            ->mapWithKeys(fn ($name, $id) => [(int) $id => trim((string) $name)]);
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
        if ($this->subdepartments !== null && $this->subdepartmentParents !== null) {
            return;
        }

        $this->subdepartments = collect();
        $this->subdepartmentParents = collect();

        try {
            $rows = SalaDepartment::query()
                ->where('active_flag', 'Y')
                ->whereColumn('dept_cd', '<>', 'in_dept_cd')
                ->whereNotNull('dept_lname_th')
                ->orderBy('dept_lname_th')
                ->get(['dept_cd', 'in_dept_cd', 'dept_lname_th']);

            foreach ($rows as $department) {
                $id = (int) $department->dept_cd;
                $parentId = (int) $department->in_dept_cd;
                $name = trim((string) $department->dept_lname_th);

                if ($id <= 0 || $parentId <= 0 || $name === '') {
                    continue;
                }

                $this->subdepartments->put($id, $name);
                $this->subdepartmentParents->put($id, $parentId);
            }
        } catch (Throwable $exception) {
            Log::warning('DB199 subdepartment directory is unavailable.', [
                'exception' => $exception::class,
            ]);
        }
    }
}
