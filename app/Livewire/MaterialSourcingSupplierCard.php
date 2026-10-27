<?php

namespace App\Livewire;

use App\Actions\Rnd\Material\UpdateMaterialSupplierCandidateAction;
use App\Models\MaterialSourcing;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Throwable;

class MaterialSourcingSupplierCard extends Component
{
    use WithFileUploads;

    public MaterialSourcing $sourcing;

    public bool $editing = false;

    public string $supplierName = '';

    public string $brand = '';

    public string $price = '';

    public string $moq = '';

    public string $leadTimeDays = '';

    public string $contactName = '';

    public string $contactPhone = '';

    public string $notes = '';

    public ?TemporaryUploadedFile $newAttachment = null;

    public function mount(MaterialSourcing $sourcing): void
    {
        $this->sourcing = $sourcing;
        $this->fillFromModel();
    }

    public function toggleEdit(): void
    {
        abort_unless($this->canEdit(), 403);
        $this->editing = ! $this->editing;

        if ($this->editing) {
            $this->fillFromModel();
            $this->resetValidation();
        }
    }

    public function save(): void
    {
        abort_unless(auth()->user()?->can('submit material sourcing'), 403);
        abort_unless($this->canEdit(), 403);

        $validated = $this->validate([
            'supplierName' => ['required', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'gt:0'],
            'moq' => ['nullable', 'string', 'max:255'],
            'leadTimeDays' => ['nullable', 'integer', 'min:0'],
            'contactName' => ['nullable', 'string', 'max:255'],
            'contactPhone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'newAttachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ]);

        $newPath = $this->newAttachment?->store('material-sourcing', 'b2');

        try {
            $result = app(UpdateMaterialSupplierCandidateAction::class)->execute($this->sourcing, auth()->user(), [
                'supplier_name' => trim($validated['supplierName']),
                'brand' => trim($validated['brand'] ?? '') ?: null,
                'price' => $validated['price'],
                'moq' => trim($validated['moq'] ?? '') ?: null,
                'lead_time_days' => $validated['leadTimeDays'] !== '' ? $validated['leadTimeDays'] : null,
                'contact_name' => trim($validated['contactName'] ?? '') ?: null,
                'contact_phone' => trim($validated['contactPhone'] ?? '') ?: null,
                'notes' => trim($validated['notes'] ?? '') ?: null,
                'attachment_path' => $newPath ?: $this->sourcing->attachment_path,
            ]);
        } catch (Throwable $exception) {
            if ($newPath) {
                Storage::disk('b2')->delete($newPath);
            }

            throw $exception;
        }

        if ($newPath && $result['previous_attachment']) {
            Storage::disk('b2')->delete($result['previous_attachment']);
        }

        $this->sourcing = $result['candidate']->refresh();
        $this->newAttachment = null;
        $this->editing = false;
        Notification::make()->title('Data supplier diperbarui')->success()->send();
    }

    public function canEdit(): bool
    {
        $user = auth()->user();

        return $user !== null
            && app(UpdateMaterialSupplierCandidateAction::class)->canEdit($this->sourcing, $user);
    }

    private function fillFromModel(): void
    {
        $this->supplierName = $this->sourcing->supplier_name;
        $this->brand = (string) $this->sourcing->brand;
        $this->price = (string) $this->sourcing->price;
        $this->moq = (string) $this->sourcing->moq;
        $this->leadTimeDays = (string) $this->sourcing->lead_time_days;
        $this->contactName = (string) $this->sourcing->contact_name;
        $this->contactPhone = (string) $this->sourcing->contact_phone;
        $this->notes = (string) $this->sourcing->notes;
    }

    public function render(): View
    {
        return view('livewire.material-sourcing-supplier-card');
    }
}
