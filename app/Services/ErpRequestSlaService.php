<?php

namespace App\Services;

use App\Enums\ItRequestStatus;
use App\Models\ErpRepairRequest;
use Illuminate\Database\Eloquent\Builder;

class ErpRequestSlaService
{
    public const ERP_PROCESSING_LIMIT_SECONDS = 32400;

    public const ERP_PROCESSING_TARGET_PERCENT = 98;

    public const LIGHT_TICKET_AVERAGE_LIMIT_SECONDS = 14400;

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

    /** @return array{total: int, on_time: int, on_time_percent: ?float, processing_target_met: ?bool, completion_average: ?float, completion_count: int, completion_target_met: ?bool, pending: int, missing_completed_duration: int} */
    public function ticketingKpis(Builder $query): array
    {
        $summary = (clone $query)->whereHas('requestType', fn (Builder $types): Builder => $types->where('name', 'Ticketing'))
            ->reorder()->toBase()->selectRaw(
                'COUNT(*) AS total,
                COUNT(CASE WHEN status = ? AND resolution_business_seconds <= ? THEN 1 END) AS on_time,
                AVG(CASE WHEN status = ? THEN resolution_business_seconds END) AS completion_average,
                COUNT(CASE WHEN status = ? THEN resolution_business_seconds END) AS completion_count,
                COUNT(CASE WHEN status NOT IN (?, ?) THEN 1 END) AS pending,
                COUNT(CASE WHEN status = ? AND resolution_business_seconds IS NULL THEN 1 END) AS missing_completed_duration',
                [ItRequestStatus::Completed->value, self::ERP_PROCESSING_LIMIT_SECONDS,
                    ItRequestStatus::Completed->value, ItRequestStatus::Completed->value,
                    ItRequestStatus::Completed->value, ItRequestStatus::Rejected->value, ItRequestStatus::Completed->value],
            )->first();

        $total = (int) $summary->total;
        $onTime = (int) $summary->on_time;
        $average = $summary->completion_average === null ? null : (float) $summary->completion_average;

        return [
            'total' => $total,
            'on_time' => $onTime,
            'on_time_percent' => $total > 0 ? $onTime / $total * 100 : null,
            'processing_target_met' => $total > 0 ? $onTime * 100 >= $total * self::ERP_PROCESSING_TARGET_PERCENT : null,
            'completion_average' => $average,
            'completion_count' => (int) $summary->completion_count,
            'completion_target_met' => $average === null ? null : $average < self::LIGHT_TICKET_AVERAGE_LIMIT_SECONDS,
            'pending' => (int) $summary->pending,
            'missing_completed_duration' => (int) $summary->missing_completed_duration,
        ];
    }
}
