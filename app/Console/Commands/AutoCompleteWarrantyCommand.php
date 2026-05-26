<?php

namespace App\Console\Commands;

use App\Models\ServiceRequest;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('service-requests:complete-warranty')]
#[Description('Auto-complete service requests whose 30-day warranty period has expired')]
class AutoCompleteWarrantyCommand extends Command
{
    public function handle(): void
    {
        $count = ServiceRequest::query()
            ->where('status', 'warranty')
            ->where('warranty_expires_at', '<=', now())
            ->update(['status' => 'completed']);

        $this->info("Completed {$count} service request(s) whose warranty has expired.");
    }
}
