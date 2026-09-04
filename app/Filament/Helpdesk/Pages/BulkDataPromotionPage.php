<?php

namespace App\Filament\Helpdesk\Pages;

use App\Models\Branch;
use App\Models\BranchEsbCode;
use App\Services\EsbPromotionService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Throwable;

class BulkDataPromotionPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static ?string $title = 'Bulk Data Promotion';

    protected static ?string $slug = 'bulk-data/promotion';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.helpdesk.pages.bulk-data-promotion-page';

    public ?array $data = [];

    public bool $pickerOpen = false;

    public string $pickerType = 'category';

    public int $pickerPage = 1;

    public int $pickerPerPage = 10;

    public string $pickerSearch = '';

    /** @var list<string> */
    public array $pickerBranchIds = [];

    /** @var list<array{value:string,label:string,meta:string}> */
    public array $pickerRows = [];

    public bool $pickerHasNext = false;

    public int $pickerTotal = 0;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view bulk product submissions') ?? false;
    }

    public function mount(): void
    {
        $this->form->fill([
            'target_comcodes' => ['ALL'],
            'promotionType' => 4,
            'discountAccountNumber' => 'Refer to Account in Mapping',
            'authorizationNeeded' => false,
            'promotionDaysID' => [1, 2, 3, 4, 5, 6, 7],
            'allCategories' => true,
            'selectPromotionTime' => 'all_days',
            'applyToAllApplication' => false,
            'usedForLoyalty' => false,
            'applyTo' => 'All Transaction',
            'applyToApplicationID' => ['pos'],
            'promotionTime' => [],
            'branchCode' => [],
            'menuCategoryID' => [],
            'menuCategoryDetailID' => [],
            'menuID' => [],
            'menuCategorySummary' => 'Belum ada yang dipilih',
            'menuCategoryDetailSummary' => 'Belum ada yang dipilih',
            'menuSummary' => 'Belum ada yang dipilih',
            'employeeGroupName' => [],
            'selfOrderPaymentMethodCode' => [],
            'visitPurposeDisplay' => 'All Visit Purpose',
            'bankIdentificationNumbers' => [],
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Create Promotion - New')
                    ->schema([
                        Select::make('promotionType')
                            ->label('Promotion Type')
                            ->options([4 => 'FREE ITEM'])
                            ->default(4)
                            ->native(false)
                            ->required()
                            ->disabled()
                            ->dehydrated(),
                        Select::make('target_comcodes')
                            ->label('Comcode')
                            ->options(['ALL' => 'All Comcode'] + self::comcodeOptions())
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->live()
                            ->required()
                            ->afterStateUpdated(fn (Set $set): null => $this->resetDependentFields($set)),
                        Select::make('branch_ids')
                            ->label('Branch')
                            ->options(fn (Get $get): array => $this->branchOptions($get('target_comcodes') ?? []))
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->live()
                            ->required()
                            ->placeholder('Select Branch')
                            ->helperText(fn (Get $get): ?string => blank($get('target_comcodes')) ? 'Please select Comcode first' : null)
                            ->disabled(fn (Get $get): bool => blank($get('target_comcodes')))
                            ->afterStateUpdated(fn (Set $set): null => $this->resetBranchDependentFields($set)),
                        TextInput::make('promotionMasterCode')
                            ->label('Promotion Master Code')
                            ->required()
                            ->maxLength(50),
                        Select::make('discountAccountNumber')
                            ->label('Discount Account Number')
                            ->options([
                                'Refer to Account in Mapping' => 'Refer to Account in Mapping',
                            ])
                            ->default('Refer to Account in Mapping')
                            ->native(false),
                        TextInput::make('notes')
                            ->label('Promotion Notes')
                            ->maxLength(100),
                        Select::make('authorizationNeeded')
                            ->label('Authorization Needed ?')
                            ->boolean()
                            ->native(false)
                            ->required(),
                        Select::make('promotionDaysID')
                            ->label('Promotion Days')
                            ->options(self::dayOptions())
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->required()
                            ->placeholder('Select Days'),
                        DateTimePicker::make('startDate')
                            ->label('Start Date')
                            ->seconds()
                            ->required(),
                        DateTimePicker::make('endDate')
                            ->label('End Date')
                            ->seconds()
                            ->required()
                            ->afterOrEqual('startDate'),
                        Select::make('selectPromotionTime')
                            ->label('Select Promotion Time')
                            ->options([
                                'all_days' => 'All Days',
                                'specific_time' => 'Specific Time',
                            ])
                            ->default('all_days')
                            ->native(false)
                            ->live()
                            ->required(),
                        Select::make('applyToAllApplication')
                            ->label('Apply To All Application')
                            ->boolean()
                            ->native(false)
                            ->live()
                            ->required(),
                        Select::make('applyToApplicationID')
                            ->label('Apply To Application')
                            ->options(['pos' => 'POS', 'eso' => 'ESB Order'])
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->required(fn (Get $get): bool => ! (bool) $get('applyToAllApplication'))
                            ->disabled(fn (Get $get): bool => (bool) $get('applyToAllApplication'))
                            ->dehydrated(),
                        Select::make('allCategories')
                            ->label('Select All Categories?')
                            ->boolean()
                            ->native(false)
                            ->live()
                            ->required()
                            ->afterStateUpdated(fn (Set $set): null => $this->resetMenuSelections($set)),
                        Select::make('applyDiscountTo')
                            ->label('Apply Discount To')
                            ->options([
                                1 => 'Menu Category',
                                2 => 'Menu Category Detail',
                                3 => 'Menu',
                            ])
                            ->native(false)
                            ->visible(fn (Get $get): bool => ! (bool) $get('allCategories'))
                            ->required(fn (Get $get): bool => ! (bool) $get('allCategories'))
                            ->live()
                            ->afterStateUpdated(fn (Set $set): null => $this->resetMenuSelections($set)),
                        TextInput::make('menuCategorySummary')
                            ->label('Menu Category')
                            ->readOnly()
                            ->dehydrated(false)
                            ->suffixAction(Action::make('pickMenuCategory')
                                ->label('Pilih')
                                ->icon('heroicon-m-list-bullet')
                                ->action(fn (Get $get): null => $this->openPicker('category', $get('branch_ids') ?? [])))
                            ->helperText('Pilih menu category yang promotion-nya akan aktif. Label dibedakan per comcode dan branch.')
                            ->visible(fn (Get $get): bool => ! (bool) $get('allCategories') && (int) $get('applyDiscountTo') === 1)
                            ->disabled(fn (Get $get): bool => blank($get('branch_ids'))),
                        TextInput::make('menuCategoryDetailSummary')
                            ->label('Menu Category Detail')
                            ->readOnly()
                            ->dehydrated(false)
                            ->suffixAction(Action::make('pickMenuCategoryDetail')
                                ->label('Pilih')
                                ->icon('heroicon-m-list-bullet')
                                ->action(fn (Get $get): null => $this->openPicker('category_detail', $get('branch_ids') ?? [])))
                            ->helperText('Pilih menu category detail yang promotion-nya akan aktif. Label dibedakan per comcode dan branch.')
                            ->visible(fn (Get $get): bool => ! (bool) $get('allCategories') && (int) $get('applyDiscountTo') === 2)
                            ->disabled(fn (Get $get): bool => blank($get('branch_ids'))),
                        TextInput::make('menuSummary')
                            ->label('Menu')
                            ->readOnly()
                            ->dehydrated(false)
                            ->suffixAction(Action::make('pickMenu')
                                ->label('Pilih')
                                ->icon('heroicon-m-list-bullet')
                                ->action(fn (Get $get): null => $this->openPicker('menu', $get('branch_ids') ?? [])))
                            ->helperText('Pilih menu yang promotion-nya akan aktif. Label dibedakan per comcode dan branch.')
                            ->visible(fn (Get $get): bool => ! (bool) $get('allCategories') && (int) $get('applyDiscountTo') === 3)
                            ->disabled(fn (Get $get): bool => blank($get('branch_ids'))),
                        Hidden::make('menuCategoryID'),
                        Hidden::make('menuCategoryDetailID'),
                        Hidden::make('menuID'),
                        Select::make('applyTo')
                            ->label('Apply To')
                            ->options(self::applyToOptions())
                            ->native(false)
                            ->required()
                            ->live(),
                        Select::make('usedForLoyalty')
                            ->label('Used For Loyalty Integration')
                            ->boolean()
                            ->native(false)
                            ->required(),
                        TextInput::make('promotionDesc')
                            ->label('Promotion Desc')
                            ->required()
                            ->maxLength(100),
                        TextInput::make('promotionCode')
                            ->label('Promotion Code')
                            ->maxLength(20),
                        Select::make('voucherSourceName')
                            ->label('Voucher Source')
                            ->options(['' => '- Select Source Voucher -', 'ESB' => 'ESB', 'Giftee' => 'Giftee'])
                            ->native(false)
                            ->live(),
                        TextInput::make('minSalesPrice')
                            ->label('Min. Sales Price')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->required(fn (Get $get): bool => in_array($get('voucherSourceName'), ['ESB', 'Giftee'], true))
                            ->disabled(fn (Get $get): bool => ! in_array($get('voucherSourceName'), ['ESB', 'Giftee'], true))
                            ->dehydrated(),
                        TextInput::make('maxUsage')
                            ->label('Max Usage per Customer')
                            ->numeric()
                            ->minValue(1)
                            ->required(fn (Get $get): bool => $this->usesEso($get))
                            ->disabled(fn (Get $get): bool => ! $this->usesEso($get))
                            ->dehydrated(),
                        TextInput::make('maxUsageTotal')
                            ->label('Max Usage Total')
                            ->numeric()
                            ->minValue(1)
                            ->required(fn (Get $get): bool => $this->usesEso($get))
                            ->disabled(fn (Get $get): bool => ! $this->usesEso($get))
                            ->dehydrated(),
                        Select::make('selfOrderPaymentMethodCode')
                            ->label('Self Order Payment Method')
                            ->options(fn (Get $get): array => $this->selfOrderPaymentMethodOptions($get))
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->placeholder('Select Payment')
                            ->required(fn (Get $get): bool => $this->usesEso($get))
                            ->disabled(fn (Get $get): bool => ! $this->usesEso($get) || blank($get('branch_ids')))
                            ->dehydrated(),
                        Select::make('paymentMethodName')
                            ->label('Payment Method')
                            ->options(fn (Get $get): array => ['' => '- Select Payment Method -'] + $this->paymentMethodOptions($get))
                            ->native(false)
                            ->searchable()
                            ->preload()
                            ->disabled(fn (Get $get): bool => blank($get('branch_ids')))
                            ->placeholder('- Select Payment Method -'),
                        Select::make('settingBinRequired')
                            ->label('Setting BIN Required')
                            ->boolean()
                            ->native(false)
                            ->live()
                            ->required(),
                        TextInput::make('visitPurposeDisplay')
                            ->label('Visit Purpose')
                            ->default('All Visit Purpose')
                            ->readOnly()
                            ->dehydrated(false),
                        TagsInput::make('bankIdentificationNumbers')
                            ->label('Bank Identification Numbers')
                            ->placeholder('Ketik BIN lalu Enter')
                            ->visible(fn (Get $get): bool => (bool) $get('settingBinRequired'))
                            ->required(fn (Get $get): bool => (bool) $get('settingBinRequired')),
                        TextInput::make('prefixPromotion')
                            ->label('Prefix Promotion')
                            ->maxLength(5)
                            ->visible(fn (Get $get): bool => $get('voucherSourceName') === 'Giftee')
                            ->required(fn (Get $get): bool => $get('voucherSourceName') === 'Giftee'),
                        Repeater::make('promotionTime')
                            ->label('Promotion Time Detail')
                            ->schema([
                                TextInput::make('startTime')
                                    ->label('Start Time')
                                    ->placeholder('07:00:00')
                                    ->required()
                                    ->regex('/^\d{2}:\d{2}:\d{2}$/'),
                                TextInput::make('endTime')
                                    ->label('End Time')
                                    ->placeholder('10:00:00')
                                    ->required()
                                    ->regex('/^\d{2}:\d{2}:\d{2}$/'),
                            ])
                            ->columns(2)
                            ->defaultItems(1)
                            ->addActionLabel('Tambah Jam Promo')
                            ->visible(fn (Get $get): bool => $get('selectPromotionTime') === 'specific_time')
                            ->columnSpanFull(),
                    ])
                    ->columns(4),
            ])
            ->statePath('data');
    }

    public function submit(): void
    {
        $data = $this->form->getState();
        $this->validatePickerSelection($data);

        $targets = $this->targetsForBranches($data['branch_ids'] ?? []);
        if ($targets === []) {
            throw ValidationException::withMessages(['branch_ids' => 'Branch belum memiliki comcode ESB aktif yang terdaftar.']);
        }

        $payload = $this->payload($data);
        $results = [];

        foreach ($targets as $comcode) {
            try {
                $response = app(EsbPromotionService::class)->createFreeItem($comcode, $this->payloadForComcode($payload, $data, $comcode));
                $results[] = $comcode.' sukses'.($response['promotionID'] ? ' (#'.$response['promotionID'].')' : '');
            } catch (Throwable $exception) {
                report($exception);
                $results[] = $comcode.' gagal: '.$exception->getMessage();
            }
        }

        Notification::make()
            ->title('Bulk Data Promotion selesai diproses')
            ->body(implode("\n", $results))
            ->success()
            ->send();
    }

    public function openPicker(string $type, mixed $branchIds = null): null
    {
        $resolvedBranchIds = filled($branchIds)
            ? $branchIds
            : ($this->data['branch_ids'] ?? ($this->form->getState()['branch_ids'] ?? []));

        $this->pickerBranchIds = $this->stringList($resolvedBranchIds);

        if ($this->selectedEsbBranchPairs($this->pickerBranchIds)->isEmpty()) {
            Notification::make()
                ->title('Pilih Branch terlebih dahulu')
                ->body('Data category, category detail, dan menu diambil berdasarkan comcode + branch yang dipilih.')
                ->warning()
                ->send();

            return null;
        }

        $this->pickerType = in_array($type, ['category', 'category_detail', 'menu'], true) ? $type : 'category';
        $this->pickerPage = 1;
        $this->pickerSearch = '';
        $this->pickerOpen = true;
        $this->loadPickerRows();

        return null;
    }

    public function closePicker(): void
    {
        $this->pickerOpen = false;
    }

    public function updatedPickerSearch(): void
    {
        $this->pickerPage = 1;
        $this->loadPickerRows();
    }

    public function setPickerPerPage(mixed $perPage): void
    {
        $this->pickerPerPage = in_array((int) $perPage, [10, 20], true) ? (int) $perPage : 10;
        $this->pickerPage = 1;
        $this->loadPickerRows();
    }

    public function nextPickerPage(): void
    {
        if (! $this->pickerHasNext) {
            return;
        }

        $this->pickerPage++;
        $this->loadPickerRows();
    }

    public function previousPickerPage(): void
    {
        $this->pickerPage = max(1, $this->pickerPage - 1);
        $this->loadPickerRows();
    }

    public function loadPickerRows(): void
    {
        $pairs = $this->selectedEsbBranchPairs($this->pickerBranchIds ?: ($this->data['branch_ids'] ?? []))->all();
        $service = app(EsbPromotionService::class);
        $result = $this->pickerType === 'menu'
            ? $service->menuPage($pairs, $this->pickerPage, $this->pickerPerPage, $this->pickerSearch)
            : $service->menuCategoryPage($pairs, $this->pickerType, $this->pickerPage, $this->pickerPerPage);

        $this->pickerRows = $result['rows'];
        $this->pickerPage = $result['page'];
        $this->pickerPerPage = $result['perPage'];
        $this->pickerTotal = $result['total'];
        $this->pickerHasNext = $result['hasNext'];
    }

    public function togglePickerValue(string $value): void
    {
        $field = $this->pickerField();
        $selected = $this->stringList($this->data[$field] ?? []);

        $this->data[$field] = in_array($value, $selected, true)
            ? array_values(array_diff($selected, [$value]))
            : [...$selected, $value];

        $this->refreshMenuSummary($field);
    }

    public function isPickerValueSelected(string $value): bool
    {
        return in_array($value, $this->stringList($this->data[$this->pickerField()] ?? []), true);
    }

    public function selectedPickerCount(): int
    {
        return count($this->stringList($this->data[$this->pickerField()] ?? []));
    }

    public function pickerTitle(): string
    {
        return match ($this->pickerType) {
            'category_detail' => 'Pilih Menu Category Detail',
            'menu' => 'Pilih Menu',
            default => 'Pilih Menu Category',
        };
    }

    /** @return array<string, string> */
    private static function comcodeOptions(): array
    {
        return BranchEsbCode::query()
            ->where('is_active', true)
            ->pluck('esb_comcode')
            ->merge(array_keys((array) config('esb.tokens', [])))
            ->filter(fn (string $comcode): bool => trim($comcode) !== '')
            ->unique()
            ->sort()
            ->mapWithKeys(fn (string $comcode): array => [$comcode => $comcode])
            ->all();
    }

    /** @return array<string, string> */
    private function branchOptions(mixed $comcodes): array
    {
        $selectedComcodes = $this->selectedComcodes($comcodes);
        $options = app(EsbPromotionService::class)->branchOptions($selectedComcodes);
        if ($options !== []) {
            return $options;
        }

        $branches = Branch::query()
            ->where('is_active', true)
            ->with(['esbCodes' => function ($query) use ($selectedComcodes): void {
                $query->where('is_active', true);
                if (! in_array('ALL', $selectedComcodes, true)) {
                    $query->whereIn('esb_comcode', $selectedComcodes);
                }
            }])
            ->whereHas('esbCodes', function ($query) use ($selectedComcodes): void {
                $query->where('is_active', true);
                if (! in_array('ALL', $selectedComcodes, true)) {
                    $query->whereIn('esb_comcode', $selectedComcodes);
                }
            })
            ->orderBy('name')
            ->get();

        $fallback = [];
        foreach ($branches as $branch) {
            foreach ($branch->esbCodes as $code) {
                $comcode = $code->esb_comcode;
                $branchCode = $code->esb_branch_code;
                $key = "{$comcode}|{$branchCode}";
                $fallback[$key] = "{$comcode} - {$branch->name} ({$branchCode})";
            }
        }

        return $fallback;
    }

    /** @return array<int, string> */
    private static function dayOptions(): array
    {
        return [
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
            7 => 'Sunday',
        ];
    }

    /** @return array<string, string> */
    private static function applyToOptions(): array
    {
        return [
            'All Transaction' => 'All Transaction',
            'Member & Staff' => 'Member & Staff',
            'Staff Only' => 'Staff Only',
            'Member Only' => 'Member Only',
        ];
    }

    /** @return array<string, string> */
    private function selfOrderPaymentMethodOptions(Get $get): array
    {
        return app(EsbPromotionService::class)->selfOrderPaymentMethodOptions(
            $this->selectedTargetComcodes($get),
            $this->selectedBranchCodes($get),
        ) ?: [
            'cc88' => 'Credit Card (cc88)',
            'qris' => 'QRIS',
            'va' => 'Virtual Account',
            'ewallet' => 'E-Wallet',
        ];
    }

    /** @return array<string, string> */
    private function paymentMethodOptions(Get $get): array
    {
        return app(EsbPromotionService::class)->paymentMethodOptions(
            $this->selectedTargetComcodes($get),
            $this->selectedBranchCodes($get),
        ) ?: [
            'CASH' => 'CASH',
            'QRIS' => 'QRIS',
            'CREDIT CARD' => 'CREDIT CARD',
            'DEBIT CARD' => 'DEBIT CARD',
            'TRANSFER' => 'TRANSFER',
        ];
    }

    private function payload(array $data): array
    {
        $allCategories = (bool) $data['allCategories'];
        $applicationIds = (bool) ($data['applyToAllApplication'] ?? false)
            ? ['pos', 'eso']
            : $this->stringList($data['applyToApplicationID'] ?? []);
        $voucherSource = trim((string) ($data['voucherSourceName'] ?? ''));
        $paymentMethod = trim((string) ($data['paymentMethodName'] ?? ''));

        return [
            'promotionMasterCode' => (string) $data['promotionMasterCode'],
            'branchCode' => $this->branchCodesForBranches($data['branch_ids'] ?? []),
            'promotionType' => 4,
            'notes' => (string) ($data['notes'] ?? ''),
            'authorizationNeeded' => $this->yesNo((bool) $data['authorizationNeeded']),
            'promotionDaysID' => $this->integerList($data['promotionDaysID'] ?? []),
            'startDate' => $this->dateTime($data['startDate']),
            'endDate' => $this->dateTime($data['endDate']),
            'allCategories' => $this->yesNo($allCategories),
            'applyDiscountTo' => $allCategories ? null : (int) $data['applyDiscountTo'],
            'menuCategoryID' => (! $allCategories && (int) $data['applyDiscountTo'] === 1) ? $this->integerList($data['menuCategoryID'] ?? []) : [],
            'menuCategoryDetailID' => (! $allCategories && (int) $data['applyDiscountTo'] === 2) ? $this->integerList($data['menuCategoryDetailID'] ?? []) : [],
            'menuID' => (! $allCategories && (int) $data['applyDiscountTo'] === 3) ? $this->integerList($data['menuID'] ?? []) : [],
            'usedForLoyalty' => $this->yesNo((bool) $data['usedForLoyalty']),
            'applyTo' => (string) $data['applyTo'],
            'employeeGroupName' => in_array($data['applyTo'], ['Member & Staff', 'Staff Only'], true) ? $this->stringList($data['employeeGroupName'] ?? []) : [],
            'applyToApplicationID' => $applicationIds,
            'selfOrderPaymentMethodCode' => in_array('eso', $applicationIds, true) ? $this->stringList($data['selfOrderPaymentMethodCode'] ?? []) : [],
            'maxUsage' => in_array('eso', $applicationIds, true) ? (int) $data['maxUsage'] : null,
            'maxUsageTotal' => in_array('eso', $applicationIds, true) ? (int) $data['maxUsageTotal'] : null,
            'visitPurposeID' => [],
            'promotionTime' => $this->promotionTimes($data['promotionTime'] ?? []),
            'promotionCode' => (string) ($data['promotionCode'] ?? ''),
            'promotionDesc' => (string) $data['promotionDesc'],
            'paymentMethodName' => $paymentMethod,
            'voucherSourceName' => $voucherSource,
            'minSalesPrice' => in_array($voucherSource, ['ESB', 'Giftee'], true) ? (float) $data['minSalesPrice'] : null,
            'bankIdentificationNumbers' => (bool) ($data['settingBinRequired'] ?? false) ? $this->stringList($data['bankIdentificationNumbers'] ?? []) : [],
            'prefixPromotion' => $voucherSource === 'Giftee' ? (string) $data['prefixPromotion'] : '',
            'discountAccountNumber' => (string) ($data['discountAccountNumber'] ?? ''),
        ];
    }

    private function payloadForComcode(array $payload, array $data, string $comcode): array
    {
        $allCategories = (bool) $data['allCategories'];
        $applyDiscountTo = (int) ($data['applyDiscountTo'] ?? 0);

        $payload['branchCode'] = $this->branchCodesForComcode($data['branch_ids'] ?? [], $comcode);
        $payload['menuCategoryID'] = (! $allCategories && $applyDiscountTo === 1) ? $this->selectedScopedIds($data['menuCategoryID'] ?? [], $comcode) : [];
        $payload['menuCategoryDetailID'] = (! $allCategories && $applyDiscountTo === 2) ? $this->selectedScopedIds($data['menuCategoryDetailID'] ?? [], $comcode) : [];
        $payload['menuID'] = (! $allCategories && $applyDiscountTo === 3) ? $this->selectedScopedIds($data['menuID'] ?? [], $comcode) : [];

        return $payload;
    }

    private function usesEso(Get $get): bool
    {
        return (bool) $get('applyToAllApplication') || in_array('eso', $get('applyToApplicationID') ?? [], true);
    }

    private function resetDependentFields(Set $set): null
    {
        $set('branch_ids', []);
        $this->resetBranchDependentFields($set);

        return null;
    }

    private function resetBranchDependentFields(Set $set): null
    {
        $set('selfOrderPaymentMethodCode', []);
        $set('paymentMethodName', null);
        $set('visitPurposeDisplay', 'All Visit Purpose');
        $this->resetMenuSelections($set);

        return null;
    }

    private function resetMenuSelections(Set $set): null
    {
        $set('menuCategoryID', []);
        $set('menuCategoryDetailID', []);
        $set('menuID', []);
        $set('menuCategorySummary', 'Belum ada yang dipilih');
        $set('menuCategoryDetailSummary', 'Belum ada yang dipilih');
        $set('menuSummary', 'Belum ada yang dipilih');

        return null;
    }

    private function validatePickerSelection(array $data): void
    {
        if ((bool) ($data['allCategories'] ?? false)) {
            return;
        }

        $applyDiscountTo = (int) ($data['applyDiscountTo'] ?? 0);
        $field = match ($applyDiscountTo) {
            1 => 'menuCategoryID',
            2 => 'menuCategoryDetailID',
            3 => 'menuID',
            default => null,
        };

        if ($field === null || $this->stringList($data[$field] ?? []) !== []) {
            return;
        }

        throw ValidationException::withMessages([
            $this->summaryField($field) => 'Pilih minimal satu '.$this->pickerLabelForField($field).'.',
        ]);
    }

    /** @return list<string> */
    private function targetsForBranches(mixed $branchIds): array
    {
        $selectedComcodes = $this->selectedComcodes($this->data['target_comcodes'] ?? []);

        return Branch::query()
            ->whereIn('id', $this->integerList($branchIds))
            ->with('esbCodes')
            ->get()
            ->flatMap(fn (Branch $branch) => $branch->esbCodes->where('is_active', true)->pluck('esb_comcode'))
            ->merge($this->selectedEsbBranchPairs($branchIds)->pluck('comcode'))
            ->when(! in_array('ALL', $selectedComcodes, true), fn ($comcodes) => $comcodes->intersect($selectedComcodes))
            ->intersect(array_keys((array) config('esb.tokens', [])))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /** @return list<string> */
    private function branchCodesForBranches(mixed $branchIds): array
    {
        $selectedComcodes = $this->selectedComcodes($this->data['target_comcodes'] ?? []);

        return Branch::query()
            ->whereIn('id', $this->integerList($branchIds))
            ->with('esbCodes')
            ->get()
            ->flatMap(fn (Branch $branch) => $branch->esbCodes
                ->where('is_active', true)
                ->when(! in_array('ALL', $selectedComcodes, true), fn ($codes) => $codes->whereIn('esb_comcode', $selectedComcodes))
                ->pluck('esb_branch_code'))
            ->merge($this->selectedEsbBranchPairs($branchIds)->pluck('branchCode'))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /** @return list<string> */
    private function selectedTargetComcodes(Get $get): array
    {
        return $this->targetsForBranches($get('branch_ids') ?? []);
    }

    /** @return list<string> */
    private function selectedBranchCodes(Get $get): array
    {
        return $this->branchCodesForBranches($get('branch_ids') ?? []);
    }

    /** @return list<string> */
    private function selectedComcodes(mixed $comcodes): array
    {
        $selected = $this->stringList($comcodes);

        if ($selected === [] || in_array('ALL', $selected, true)) {
            return array_keys(self::comcodeOptions());
        }

        return $selected;
    }

    private function selectedEsbBranchPairs(mixed $values): Collection
    {
        $rawValues = collect($values)->filter(fn ($v): bool => filled($v));
        $pairs = collect();

        foreach ($rawValues as $value) {
            $valueStr = (string) $value;
            if (str_contains($valueStr, '|')) {
                $parts = explode('|', $valueStr, 2);
                if (count($parts) === 2 && trim($parts[0]) !== '' && trim($parts[1]) !== '') {
                    $pairs->push([
                        'comcode' => trim($parts[0]),
                        'branchCode' => trim($parts[1]),
                        'branchName' => $this->branchLabel($valueStr),
                    ]);
                }
            } elseif (is_numeric($valueStr) && (int) $valueStr > 0) {
                $selectedComcodes = $this->selectedComcodes($this->data['target_comcodes'] ?? []);
                $branch = Branch::with(['esbCodes' => function ($query) use ($selectedComcodes): void {
                    $query->where('is_active', true);
                    if (! in_array('ALL', $selectedComcodes, true)) {
                        $query->whereIn('esb_comcode', $selectedComcodes);
                    }
                }])->find((int) $valueStr);

                if ($branch) {
                    foreach ($branch->esbCodes as $code) {
                        $pairs->push([
                            'comcode' => $code->esb_comcode,
                            'branchCode' => $code->esb_branch_code,
                            'branchName' => $branch->name,
                        ]);
                    }
                }
            }
        }

        return $pairs->unique(fn (array $pair): string => $pair['comcode'].'|'.$pair['branchCode'])->values();
    }

    /** @return list<int> */
    private function selectedScopedIds(mixed $values, string $comcode): array
    {
        return collect($values)
            ->map(function (mixed $value) use ($comcode): ?int {
                $parts = explode('|', (string) $value);
                if (count($parts) === 3 && $parts[0] === $comcode) {
                    return (int) $parts[2];
                }

                if (count($parts) === 1) {
                    return (int) $parts[0];
                }

                return null;
            })
            ->filter(fn (?int $value): bool => $value !== null && $value > 0)
            ->unique()
            ->values()
            ->all();
    }

    /** @return list<string> */
    private function branchCodesForComcode(mixed $values, string $comcode): array
    {
        return $this->selectedEsbBranchPairs($values)
            ->filter(fn (array $pair): bool => $pair['comcode'] === $comcode)
            ->pluck('branchCode')
            ->unique()
            ->values()
            ->all();
    }

    private function branchLabel(string $value): string
    {
        $options = $this->branchOptions($this->data['target_comcodes'] ?? []);
        $label = (string) ($options[$value] ?? '');
        if ($label !== '') {
            return trim((string) preg_replace('/\s*\([^)]*\)\s*$/', '', $label));
        }

        if (str_contains($value, '|')) {
            [$comcode, $branchCode] = explode('|', $value, 2);
            $esbCode = BranchEsbCode::with('branch')
                ->where('esb_comcode', $comcode)
                ->where('esb_branch_code', $branchCode)
                ->first();
            if ($esbCode?->branch) {
                return "{$comcode} - {$esbCode->branch->name}";
            }

            return "{$comcode} - {$branchCode}";
        }

        if (is_numeric($value)) {
            $branch = Branch::find((int) $value);
            if ($branch) {
                return $branch->name;
            }
        }

        return $value;
    }

    private function pickerField(): string
    {
        return match ($this->pickerType) {
            'category_detail' => 'menuCategoryDetailID',
            'menu' => 'menuID',
            default => 'menuCategoryID',
        };
    }

    private function summaryField(string $field): string
    {
        return match ($field) {
            'menuCategoryDetailID' => 'menuCategoryDetailSummary',
            'menuID' => 'menuSummary',
            default => 'menuCategorySummary',
        };
    }

    private function refreshMenuSummary(string $field): void
    {
        $count = count($this->stringList($this->data[$field] ?? []));
        $this->data[$this->summaryField($field)] = $count > 0
            ? $count.' '.$this->pickerLabelForField($field).' dipilih'
            : 'Belum ada yang dipilih';
    }

    private function pickerLabelForField(string $field): string
    {
        return match ($field) {
            'menuCategoryDetailID' => 'Menu Category Detail',
            'menuID' => 'Menu',
            default => 'Menu Category',
        };
    }

    private function yesNo(bool $state): string
    {
        return $state ? 'Yes' : 'No';
    }

    private function dateTime(mixed $value): string
    {
        return Carbon::parse($value)->format('Y-m-d H:i:s');
    }

    /** @return list<int> */
    private function integerList(mixed $values): array
    {
        return collect($values)->map(fn (mixed $value): int => (int) $value)->filter(fn (int $value): bool => $value > 0)->values()->all();
    }

    /** @return list<string> */
    private function stringList(mixed $values): array
    {
        return collect($values)->map(fn (mixed $value): string => trim((string) $value))->filter()->values()->all();
    }

    /** @return list<array{startTime:string,endTime:string}> */
    private function promotionTimes(mixed $values): array
    {
        return collect($values)
            ->map(fn (array $value): array => [
                'startTime' => (string) ($value['startTime'] ?? ''),
                'endTime' => (string) ($value['endTime'] ?? ''),
            ])
            ->filter(fn (array $value): bool => $value['startTime'] !== '' && $value['endTime'] !== '')
            ->values()
            ->all();
    }
}
