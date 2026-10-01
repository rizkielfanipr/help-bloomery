<?php

namespace App\Notifications;

use App\Models\CustomerComplaint;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/** docs/customer-complaints-prd.md §16: a new complaint notifies the relevant Operational role for its branch. */
class CustomerComplaintSubmittedNotification extends Notification implements ShouldQueue
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
            'type' => 'customer_complaint_submitted',
            'customer_complaint_id' => $this->complaint->id,
            'complaint_number' => $this->complaint->complaint_number,
            'branch_name' => $this->complaint->branch->name,
            'category' => $this->complaint->category->getLabel(),
            'message' => "Komplain baru {$this->complaint->complaint_number} dari {$this->complaint->branch->name}.",
        ];
    }
}
