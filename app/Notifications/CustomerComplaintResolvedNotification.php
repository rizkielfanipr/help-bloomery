<?php

namespace App\Notifications;

use App\Models\CustomerComplaint;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/** docs/customer-complaints-prd.md §16: the submitter is notified once their complaint reaches Resolved or Closed. */
class CustomerComplaintResolvedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly CustomerComplaint $complaint) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'customer_complaint_resolved',
            'customer_complaint_id' => $this->complaint->id,
            'complaint_number' => $this->complaint->complaint_number,
            'status' => $this->complaint->status->getLabel(),
            'resolution' => $this->complaint->resolution,
            'message' => "Komplain {$this->complaint->complaint_number} berstatus {$this->complaint->status->getLabel()}.",
        ];
    }
}
