<?php

namespace Tests\Unit;

use App\DTO\ExcavatorLog\UpdateExcavatorLogData;
use App\DTO\TruckLog\UpdateTruckLogData;
use App\Http\Requests\ExcavatorLog\UpdateExcavatorLogRequest;
use App\Http\Requests\TruckLog\UpdateTruckLogRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MachineLogUpdateDataTest extends TestCase
{
    #[DataProvider('numericInputs')]
    public function test_each_numeric_parsing_rule_preserves_presence_and_coercion(
        array $input,
        ?float $hasFloat,
        ?float $nonNullInput,
        ?float $filledInput,
    ): void {
        $fields = ['fuel_added', 'fuel_remaining', 'advance_payment', 'work_hours',
            'start_work_hours', 'finish_work_hours', 'start_mileage', 'end_mileage'];
        $payload = array_fill_keys($fields, $input[0] ?? null);
        if ($input === []) {
            $payload = [];
        }

        $excavator = UpdateExcavatorLogData::fromRequest(UpdateExcavatorLogRequest::create('/', 'PATCH', $payload));
        $truck = UpdateTruckLogData::fromRequest(UpdateTruckLogRequest::create('/', 'PATCH', $payload));

        self::assertSame($hasFloat, $excavator->fuelAdded);
        self::assertSame($hasFloat, $excavator->fuelRemaining);
        self::assertSame($hasFloat, $excavator->advancePayment);
        self::assertSame($hasFloat, $excavator->workHours);
        self::assertSame($nonNullInput, $excavator->startWorkHours);
        self::assertSame($nonNullInput, $excavator->finishWorkHours);
        self::assertSame($filledInput, $truck->fuelAdded);
        self::assertSame($filledInput, $truck->fuelRemaining);
        self::assertSame($filledInput, $truck->startMileage);
        self::assertSame($filledInput, $truck->endMileage);
        self::assertSame($hasFloat, $truck->advancePayment);
        self::assertSame(array_keys($payload), $excavator->providedFields);
        self::assertSame(array_keys($payload), $truck->providedFields);
    }

    public static function numericInputs(): array
    {
        return [
            'omitted' => [[], null, null, null],
            'null' => [[null], 0.0, null, null],
            'zero integer' => [[0], 0.0, 0.0, 0.0],
            'zero float' => [[0.0], 0.0, 0.0, 0.0],
            'false before HTTP validation' => [[false], 0.0, 0.0, 0.0],
            'empty before normalization' => [[''], 0.0, 0.0, null],
            'numeric string' => [['12.25'], 12.25, 12.25, 12.25],
            'integer' => [[12], 12.0, 12.0, 12.0],
            'float' => [[12.25], 12.25, 12.25, 12.25],
        ];
    }

    public function test_dates_flags_notes_and_raw_field_keys(): void
    {
        $payload = [
            'site_manager_started_at' => '2026-02-01T08:00:00+01:00',
            'site_manager_finished_at' => null,
            'operator_started_at' => '',
            'operator_finished_at' => '2026-02-01T16:00:00+01:00',
            'started' => false,
            'finished' => '1',
            'note_site_manager' => '',
            'note_operator' => null,
            'reason' => 'Contract',
        ];

        foreach ([
            UpdateExcavatorLogData::fromRequest(UpdateExcavatorLogRequest::create('/', 'PATCH', $payload)),
            UpdateTruckLogData::fromRequest(UpdateTruckLogRequest::create('/', 'PATCH', $payload)),
        ] as $data) {
            self::assertSame($payload['site_manager_started_at'], $data->siteManagerStartedAt->toIso8601String());
            self::assertNull($data->siteManagerFinishedAt);
            self::assertNull($data->operatorStartedAt);
            self::assertSame($payload['operator_finished_at'], $data->operatorFinishedAt->toIso8601String());
            self::assertFalse($data->started);
            self::assertTrue($data->finished);
            self::assertSame('', $data->noteSiteManager);
            self::assertNull($data->noteOperator);
            self::assertSame(array_keys($payload), $data->providedFields);
        }
    }
}
