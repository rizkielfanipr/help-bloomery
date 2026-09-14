<?php

namespace App\Services;

use App\Enums\ItRequestStatus;
use App\Models\ErpRepairRequest;
use Illuminate\Database\Eloquent\Builder;

class ErpRequestSlaService
{
    public function __construct(public ItBusinessHoursService $businessHours) {}

    public function calculate(ErpRepairRequest $request): void
    {
        $request->response_business_seconds = $this->businessHours->secondsBetween($request->submitted_at, $request->first_responded_at);
        $request->resolution_business_seconds = $request->status === ItRequestStatus::Completed
            ? $this->businessHours->secondsBetween($request->submitted_at, $request->resolved_at)
            : null;
    }

    /** @return array{response_average: ?float, response_count: int, resolution_average: ?float, resolution_count: int, awaiting_response: int, unfinished: int} */
    public function summary(Builder $query): array
    {
        $summary = (clone $query)->reorder()->toBase()->selectRaw(
            'AVG(response_business_seconds) AS response_average, COUNT(response_business_seconds) AS response_count,
            AVG(CASE WHEN status = ? THEN resolution_business_seconds END) AS resolution_average,
            COUNT(CASE WHEN status = ? THEN resolution_business_seconds END) AS resolution_count,
            COUNT(CASE WHEN status = ? AND first_responded_at IS NULL THEN 1 END) AS awaiting_response,
            COUNT(CASE WHEN status NOT IN (?, ?) THEN 1 END) AS unfinished',
            [ItRequestStatus::Completed->value, ItRequestStatus::Completed->value, ItRequestStatus::Submitted->value,
                ItRequestStatus::Completed->value, ItRequestStatus::Rejected->value],
        )->first();

        return [
            'response_average' => $summary->response_average === null ? null : (float) $summary->response_average,
            'response_count' => (int) $summary->response_count,
            'resolution_average' => $summary->resolution_average === null ? null : (float) $summary->resolution_average,
            'resolution_count' => (int) $summary->resolution_count,
            'awaiting_response' => (int) $summary->awaiting_response,
            'unfinished' => (int) $summary->unfinished,
        ];
    }
}
