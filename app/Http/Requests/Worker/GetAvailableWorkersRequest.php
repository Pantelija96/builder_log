<?php

namespace App\Http\Requests\Worker;

use App\Enums\WorkerRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GetAvailableWorkersRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'role' => ['sometimes', Rule::enum(WorkerRole::class)->only([WorkerRole::WORKER, WorkerRole::OPERATOR, WorkerRole::DRIVER])],
            // Point in time only. Without any time parameters, the server checks now.
            'at' => ['sometimes', 'required', 'date', 'prohibits:started_at,finished_at'],
            // Selects interval mode. Without finished_at, the interval ends at local midnight.
            'started_at' => ['required_with:finished_at', 'filled', 'date'],
            'finished_at' => ['sometimes', 'required', 'date', 'after:started_at'],
        ];
    }
}
