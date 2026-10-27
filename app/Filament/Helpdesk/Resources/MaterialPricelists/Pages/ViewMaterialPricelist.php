<?php

namespace App\Filament\Helpdesk\Resources\MaterialPricelists\Pages;

use App\Actions\Rnd\Material\ConfirmMaterialPricelistAction;
use App\Actions\Rnd\Material\ReturnMaterialPricingToItAction;
use App\Actions\Rnd\Material\SaveMaterialPricingDraftAction;
use App\Actions\Rnd\Material\StartMaterialPriceRevisionAction;
use App\Enums\MaterialPricingStatus;
use App\Filament\Helpdesk\Resources\MaterialPricelists\MaterialPricelistResource;
use App\Models\RndMaterialPricingRequest;
use App\Services\Rnd\Material\MaterialPricelistGateway;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;

/**
 * Pricelist detail (§17.5, §18.2). Product, Product Detail, and UOM are read-only; the offer price
 * is shown only as a reference. Manual controlled mode: confirmation with an ESB reference is the
 * Finance approval and the only way to `priced`.
 */
class ViewMaterialPricelist extends ViewRecord
{
    protected static string $resource = MaterialPricelistResource::class;

    protected string $view = 'filament.helpdesk.material-pricelists.view';

    protected Width|string|null $maxContentWidth = Width::Full;

    public function mount(int|string $record): void
    {
        parent::mount($record);
        $this->record->load(['material:id,product_code,product_name,category_name,material_section,esb_product_id,created_by', 'drafter:id,name', 'verifier:id,name', 'sampleCycle:id,cycle_number,evaluated_at']);
    }

    public function getTitle(): string
    {
        return 'Product Pricelist';
    }

    public function pricing(): RndMaterialPricingRequest
    {
        /** @var RndMaterialPricingRequest $record */
        $record = $this->getRecord();

        return $record;
    }

    /** @return Collection<int, RndMaterialPricingRequest> */
    #[Computed]
    public function history(): Collection
    {
        return RndMaterialPricingRequest::query()->where('rnd_product_esb_material_id', $this->pricing()->rnd_product_esb_material_id)
            ->with('verifier:id,name')->latest('id')->limit(10)
            ->get(['id', 'uuid', 'revision', 'status', 'price', 'external_reference', 'verified_by', 'verified_at', 'product_detail_id', 'conflict_reason', 'failure_reason', 'created_at']);
    }

    public function pricelistMode(): string
    {
        return app(MaterialPricelistGateway::class)->mode();
    }

    protected function getHeaderActions(): array
    {
        $canManage = fn (): bool => Gate::allows('manage', $this->pricing());
        $is = fn (MaterialPricingStatus ...$statuses): bool => in_array($this->pricing()->status, $statuses, true);

        return [
            Action::make('saveDraft')
                ->label('Simpan Draft Harga')
                ->icon('heroicon-o-pencil-square')
                ->visible(fn (): bool => $canManage() && $is(MaterialPricingStatus::ReadyForPricing, MaterialPricingStatus::DraftPricing, MaterialPricingStatus::Failed))
                ->fillForm(fn (): array => $this->pricing()->only(['price', 'company_code', 'branch_scope', 'finance_notes']) + ['effective_date' => $this->pricing()->effective_date?->toDateString()])
                ->schema([
                    TextInput::make('price')->label('Harga Pricelist Resmi')->prefix('Rp')->numeric()->required()->gt(0),
                    Select::make('company_code')->label('Company')->options(collect(array_keys((array) config('esb.core.companies', [])))->mapWithKeys(fn (string $code): array => [$code => $code])->all()),
                    TextInput::make('branch_scope')->label('Cakupan Branch')->maxLength(255)->helperText('Kosongkan bila berlaku untuk seluruh branch company.'),
                    DatePicker::make('effective_date')->label('Tanggal Mulai Berlaku'),
                    Textarea::make('finance_notes')->label('Catatan Finance')->rows(2)->maxLength(2000),
                ])
                ->action(fn (array $data) => $this->run(fn () => app(SaveMaterialPricingDraftAction::class)->execute($this->pricing(), auth()->user(), $data), 'Draft harga disimpan (belum Priced)')),

            Action::make('confirmPricelist')
                ->label('Konfirmasi Pricelist')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn (): bool => $canManage() && $is(MaterialPricingStatus::DraftPricing, MaterialPricingStatus::VerificationRequired))
                ->modalDescription('Mode manual terkontrol: pastikan harga sudah diinput di Master Pricelist ESB. Konfirmasi ini sekaligus menjadi approval Finance.')
                ->schema([
                    TextInput::make('external_reference')->label('Referensi Pricelist ESB')->required()->minLength(3)->maxLength(120)->helperText('Nomor/ID Pricelist di ESB sebagai bukti.'),
                    Checkbox::make('confirmed')->label('Saya sudah memastikan harga dan Product Detail tercatat di ESB.')->accepted(),
                    Textarea::make('note')->label('Catatan')->rows(2)->maxLength(2000),
                ])
                ->action(fn (array $data) => $this->run(fn () => app(ConfirmMaterialPricelistAction::class)->execute($this->pricing(), auth()->user(), $data), 'Pricelist terverifikasi (Priced)')),

            Action::make('returnToIt')
                ->label('Kembalikan Mapping ke IT')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning')
                ->visible(fn (): bool => $canManage() && $is(MaterialPricingStatus::ReadyForPricing, MaterialPricingStatus::DraftPricing, MaterialPricingStatus::VerificationRequired))
                ->schema([Textarea::make('reason')->label('Masalah Product Detail / UOM')->required()->minLength(10)->rows(3)])
                ->action(fn (array $data) => $this->run(fn () => app(ReturnMaterialPricingToItAction::class)->execute($this->pricing(), auth()->user(), $data['reason']), 'Dikembalikan ke IT untuk rekonsiliasi')),

            Action::make('startRevision')
                ->label('Revisi Harga')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->visible(fn (): bool => $canManage() && $is(MaterialPricingStatus::Priced) && $this->pricing()->active_key === null
                    && ! RndMaterialPricingRequest::query()->where('rnd_product_esb_material_id', $this->pricing()->rnd_product_esb_material_id)->whereNotNull('active_key')->exists())
                ->schema([Textarea::make('reason')->label('Alasan Revisi')->required()->minLength(10)->rows(2)])
                ->action(function (array $data): void {
                    try {
                        $new = app(StartMaterialPriceRevisionAction::class)->execute($this->pricing(), auth()->user(), $data['reason']);
                    } catch (ValidationException $exception) {
                        Notification::make()->title('Tidak dapat diproses')->body(collect($exception->errors())->flatten()->implode(' '))->danger()->send();

                        return;
                    }
                    $this->redirect(MaterialPricelistResource::getUrl('view', ['record' => $new]));
                }),
        ];
    }

    private function run(callable $operation, string $success): void
    {
        try {
            $operation();
        } catch (ValidationException $exception) {
            Notification::make()->title('Tidak dapat diproses')->body(collect($exception->errors())->flatten()->implode(' '))->danger()->send();

            return;
        }

        $this->record = $this->pricing()->fresh(['material:id,product_code,product_name,category_name,material_section,esb_product_id,created_by', 'drafter:id,name', 'verifier:id,name', 'sampleCycle:id,cycle_number,evaluated_at']);
        unset($this->history);
        Notification::make()->title($success)->success()->send();
    }
}
