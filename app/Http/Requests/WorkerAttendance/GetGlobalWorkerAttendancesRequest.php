<?php

namespace App\Http\Requests\WorkerAttendance;

use App\QueryFilters\AttendancePeriod;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;

class GetGlobalWorkerAttendancesRequest extends GetWorkerAttendancesRequest
{
    protected function prepareForValidation(): void
    {
        match ($this->input('live')) {
            'true' => $this->merge(['live' => true]),
            'false' => $this->merge(['live' => false]),
            default => null,
        };
    }

    public function rules(): array
    {
        $pointInTime = Rule::prohibitedIf(fn () => $this->boolean('live') || $this->filled('at'));
        $rules = parent::rules();
        foreach (['date', 'date_from', 'date_to'] as $field) {
            $rules[$field][] = $pointInTime;
        }

        return $rules + [
            // Admin only. Return attendances active at the current server instant.
            'live' => ['sometimes', 'required', 'boolean'],
            // Admin only. Return attendances active at the specified instant. Omit live when using at.
            'at' => ['sometimes', 'required', 'date', 'prohibits:live'],
        ];
    }

    public function attendanceInstant(): ?CarbonImmutable
    {
        $at = $this->validated('at');

        if ($at !== null) {
            return AttendancePeriod::local(CarbonImmutable::parse($at));
        }

        return $this->boolean('live') ? AttendancePeriod::local(now()) : null;
    }
}
