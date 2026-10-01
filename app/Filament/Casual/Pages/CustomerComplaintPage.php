<?php

namespace App\Filament\Casual\Pages;

use App\Actions\CustomerComplaint\CreateCustomerComplaintAction;
use App\Enums\CustomerComplaintCategory;
use App\Enums\CustomerComplaintSource;
use App\Models\Branch;
use App\Models\CustomerComplaint;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Validate;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * docs/customer-complaints-prd.md §8, §9. Optional, not a daily checklist item (§4): there is no
 * "belum diisi" indicator and nothing here feeds compliance scoring. Mutation goes through
 * CreateCustomerComplaintAction (not an inline `::create()`), authorization through
 * CustomerComplaintPolicy — this Page only owns UI state and upload handling (§17).
 */
class CustomerComplaintPage extends Page
{
    use WithFileUploads;

    protected static bool $shouldRegisterNavigation = false;

    protected static string $layout = 'filament.casual.layouts.bare';

    protected string $view = 'filament.casual.pages.customer-complaint-page';

    private const MAX_ATTACHMENTS = 5;

    public ?int $branchId = null;

    public string $occurredAt = '';

    public string $source = '';

    public string $category = '';

    public string $orderReference = '';

    public string $customerName = '';

    public string $customerContact = '';

    public string $description = '';

    /** @var array<int, TemporaryUploadedFile> */
    #[Validate(['attachments.*' => 'file|mimes:jpg,jpeg,png,webp,pdf|max:5120'])]
    public array $attachments = [];

    public bool $isSubmitting = false;

    public bool $submitted = false;

    public ?string $submittedNumber = null;

    public ?int $submittedComplaintId = null;

    public ?int $viewingComplaintId = null;

    public function mount(): void
    {
        $user = auth()->user();
        $this->branchId = $user->primaryBranchId();
        $this->occurredAt = now()->format('Y-m-d\TH:i');
    }

    public function getTitle(): string|Htmlable
    {
        return 'Form Komplain';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    /** @return Collection<int, Branch> */
    public function getAccessibleBranches(): Collection
    {
        return Branch::query()->whereIn('id', auth()->user()->accessibleBranchIds())->orderBy('name')->get();
    }

    /** @return list<CustomerComplaintSource> */
    public function getSources(): array
    {
        return CustomerComplaintSource::cases();
    }

    /** @return list<CustomerComplaintCategory> */
    public function getCategories(): array
    {
        return CustomerComplaintCategory::cases();
    }

    /**
     * Up to 5 of the user's own complaints (§9). The submitter can always see their own record
     * regardless of branch access (CustomerComplaintPolicy::view), so no branch filter is applied.
     *
     * @return Collection<int, CustomerComplaint>
     */
    public function getRecentComplaints(): Collection
    {
        return CustomerComplaint::query()
            ->where('submitted_by', auth()->id())
            ->with('branch:id,name')
            ->latest('occurred_at')
            ->limit(5)
            ->get();
    }

    public function getViewingComplaint(): ?CustomerComplaint
    {
        if (! $this->viewingComplaintId) {
            return null;
        }

        $complaint = CustomerComplaint::query()->with('branch:id,name')->find($this->viewingComplaintId);

        if (! $complaint || ! auth()->user()->can('view', $complaint)) {
            return null;
        }

        return $complaint;
    }

    public function viewComplaint(int $id): void
    {
        $complaint = CustomerComplaint::query()->findOrFail($id);
        $this->authorize('view', $complaint);
        $this->viewingComplaintId = $id;
    }

    public function closeDetail(): void
    {
        $this->viewingComplaintId = null;
    }

    public function removeAttachment(int $index): void
    {
        array_splice($this->attachments, $index, 1);
        $this->attachments = array_values($this->attachments);
    }

    public function submit(CreateCustomerComplaintAction $action): void
    {
        // Guards against a double click/tap firing two overlapping requests before the first
        // response re-renders the disabled button (§8.4 "Submit berulang harus dicegah").
        if ($this->isSubmitting) {
            return;
        }

        $this->isSubmitting = true;

        try {
            $this->authorize('create', CustomerComplaint::class);

            $validated = $this->validate([
                'branchId' => ['required', 'integer'],
                'occurredAt' => ['required', 'date', 'before_or_equal:now'],
                'source' => ['required', Rule::enum(CustomerComplaintSource::class)],
                'category' => ['required', Rule::enum(CustomerComplaintCategory::class)],
                'orderReference' => ['nullable', 'string', 'max:100'],
                'customerName' => ['nullable', 'string', 'max:150'],
                'customerContact' => ['nullable', 'string', 'max:100'],
                'description' => ['required', 'string', 'max:2000'],
                'attachments' => ['array', 'max:'.self::MAX_ATTACHMENTS],
                'attachments.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            ]);

            $paths = [];
            foreach ($this->attachments as $file) {
                $paths[] = $file->store('customer-complaints/'.$validated['branchId'], 'b2');
            }

            $complaint = $action->execute([
                'branch_id' => $validated['branchId'],
                'occurred_at' => $validated['occurredAt'],
                'source' => $validated['source'],
                'category' => $validated['category'],
                'order_reference' => $validated['orderReference'] ?: null,
                'customer_name' => $validated['customerName'] ?: null,
                'customer_contact' => $validated['customerContact'] ?: null,
                'description' => $validated['description'],
                'attachment_paths' => $paths ?: null,
            ], auth()->user());

            $this->submittedNumber = $complaint->complaint_number;
            $this->submittedComplaintId = $complaint->id;
            $this->submitted = true;

            $this->reset([
                'source', 'category', 'orderReference', 'customerName', 'customerContact',
                'description', 'attachments',
            ]);

            Notification::make()
                ->title('Komplain berhasil dikirim')
                ->body("Nomor komplain {$complaint->complaint_number} telah dibuat.")
                ->success()
                ->send();
        } finally {
            $this->isSubmitting = false;
        }
    }

    public function startNewComplaint(): void
    {
        $this->submitted = false;
        $this->submittedNumber = null;
        $this->submittedComplaintId = null;
        $this->occurredAt = now()->format('Y-m-d\TH:i');
    }
}
