<?php

namespace App\Http\Requests\WorkerAttendance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GetWorkerAttendanceByWorkerRequest extends FormRequest
{
    public function rules(): array
    {
        $companyId = $this->user()->company_id;

        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'construction_site_id' => ['sometimes', 'required', 'integer', Rule::exists('construction_sites', 'id')->where('company_id', $companyId)],
            'site_manager_id' => ['sometimes', 'required', 'integer', Rule::exists('workers', 'id')->where('company_id', $companyId)],
        ];
    }
}
