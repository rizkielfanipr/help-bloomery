<?php

namespace App\Actions\CustomerComplaint;

use App\Models\CustomerComplaint;

/**
 * docs/customer-complaints-prd.md §15: soft delete only. Attachments stay on disk so a later
 * restore (or an investigation into why the complaint was removed) is still possible; permanent
 * cleanup is a separate process once a retention policy is decided.
 */
class DeleteCustomerComplaintAction
{
    public function execute(CustomerComplaint $complaint): void
    {
        $complaint->delete();
    }
}
