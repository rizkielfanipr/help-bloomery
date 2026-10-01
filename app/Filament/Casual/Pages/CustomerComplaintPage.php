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
use Illuminate\Support\Facades\Storage;
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

    public function mount(): void
    {
        $user = auth()->user();
        $this->branchId = $user->primaryBranchId();
        $this->occurredAt = now()->format('Y-m-d');
    }

    public function getTitle(): string|Htmlable
    {
        return 'Complain';
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
                'occurredAt' => ['required', 'date', 'before_or_equal:today'],
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

            try {
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
            } catch (\Throwable $exception) {
                // §15 "Hapus file jika upload sudah tersimpan tetapi transaction database gagal":
                // the files above are already on the private disk by this point, so a failure in
                // the Action (branch re-validation, a DB error) must not leave them orphaned.
                Storage::disk('b2')->delete($paths);
                throw $exception;
            }

            $this->submittedNumber = $complaint->complaint_number;
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
        $this->occurredAt = now()->format('Y-m-d');
    }
}
