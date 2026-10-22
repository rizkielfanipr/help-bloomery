<?php

namespace App\Actions\SalesReport;

use App\Actions\CalculateBasketSizeAction;
use App\Enums\SalesReportDataSource;
use App\Enums\SalesReportStatus;
use App\Models\ComplimentType;
use App\Models\Employee;
use App\Models\SalesReport;
use App\Models\SalesReportEntry;
use App\Models\SalesReportShiftSubmission;
use App\Models\User;
use App\Services\SalesReport\SalesReportEvidenceStore;
use App\Services\SalesReportReconciliationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Submits one Sales Report shift (docs/sales-report-compliment-void-remediation-prd.md §17):
 * sales entries, staff, Compliment, Void, and the ESB transaction snapshot are written in one
 * locked transaction; the report moves `Draft → PendingSupervisor` once every shift is in.
 *
 * Verified Sales Numbers and Payment Methods are resolved against the server-side snapshot of
 * the same branch, date, and shift — never against client state. Evidence is uploaded before
 * the transaction and removed again if it fails. The ESB reconciliation (HTTP) runs after the
 * commit, never inside the transaction.
 */
class SaveSalesReportShiftAction
{
    public function __construct(
        private readonly SalesReportEvidenceStore $evidence,
        private readonly CalculateBasketSizeAction $calculateBasketSize,
        private readonly SalesReportReconciliationService $reconciliation,
    ) {}

    /**
     * Field rules shared by the Store page (per-field messages) and this Action (authoritative).
     *
     * @return array<string, mixed>
     */
    public static function rules(User $user): array
    {
        return [
            'employeeIds' => ['required', 'array', 'min:1'],
            'employeeIds.*' => [
                'integer',
                Rule::exists('employees', 'id')->where(fn ($query) => $query
                    ->where('branch_id', $user->branch_id)
                    ->where('is_active', true)),
            ],
            'rows.*.sales_store' => ['nullable', 'numeric', 'min:0'],
            'rows.*.notes' => ['nullable', 'string', 'max:2000'],
            'compliments' => ['array'],
            'compliments.*.compliment_type_id' => ['required', 'integer', Rule::exists('compliment_types', 'id')->where('is_active', true)],
            'compliments.*.sales_number' => ['required', 'string', 'max:50'],
            'compliments.*.sales_number_source' => ['required', Rule::in(SalesReportDataSource::submittableValues())],
            'compliments.*.notes' => ['required', 'string', 'max:2000'],
            'compliments.*.attachments' => ['required', 'array', 'min:1', 'max:'.SalesReportEvidenceStore::MAX_COMPLIMENT_FILES],
            'compliments.*.attachments.*' => SalesReportEvidenceStore::imageRules(),
            'voids' => ['array'],
            'voids.*.sales_number' => ['required', 'string', 'max:50'],
            'voids.*.sales_number_source' => ['required', Rule::in(SalesReportDataSource::submittableValues())],
            'voids.*.payment_method_name' => ['required', 'string', 'max:100'],
            'voids.*.payment_method_source' => ['required', Rule::in(SalesReportDataSource::submittableValues())],
            'voids.*.reason' => ['required', 'string', 'max:2000'],
            'voids.*.original_receipt' => ['required', ...SalesReportEvidenceStore::imageRules()],
            'voids.*.voided_receipt' => ['required', ...SalesReportEvidenceStore::imageRules()],
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'employeeIds.required' => 'Pilih minimal satu staff yang mengisi Sales Report.',
            'employeeIds.min' => 'Pilih minimal satu staff yang mengisi Sales Report.',
            'employeeIds.*.exists' => 'Staff tidak aktif atau tidak terdaftar pada branch ini.',
            'compliments.*.compliment_type_id.required' => 'Pilih jenis compliment.',
            'compliments.*.compliment_type_id.exists' => 'Jenis compliment sudah tidak aktif atau tidak tersedia.',
            'compliments.*.sales_number.required' => 'Sales Number wajib diisi.',
            'compliments.*.sales_number_source.required' => 'Pilih Sales Number dari transaksi ESB atau gunakan Input Manual.',
            'compliments.*.notes.required' => 'Keterangan Transaksi wajib diisi.',
            'compliments.*.attachments.required' => 'Upload minimal satu foto bukti.',
            'compliments.*.attachments.min' => 'Upload minimal satu foto bukti.',
            'compliments.*.attachments.max' => 'Maksimal '.SalesReportEvidenceStore::MAX_COMPLIMENT_FILES.' foto per compliment.',
            'compliments.*.attachments.*.mimes' => 'Foto bukti harus berupa JPG, JPEG, PNG, atau WEBP.',
            'compliments.*.attachments.*.max' => 'Ukuran setiap foto maksimal 5 MB.',
            'voids.*.sales_number.required' => 'Sales Number wajib diisi.',
            'voids.*.sales_number_source.required' => 'Pilih Sales Number dari transaksi ESB atau gunakan Input Manual.',
            'voids.*.payment_method_name.required' => 'Pilih Payment Method.',
            'voids.*.payment_method_source.required' => 'Pilih Payment Method dari daftar atau gunakan Input Manual.',
            'voids.*.reason.required' => 'Alasan Void wajib diisi.',
            'voids.*.original_receipt.required' => 'Upload Foto Nota Awal.',
            'voids.*.voided_receipt.required' => 'Upload Foto Nota Setelah Void.',
            'voids.*.original_receipt.mimes' => 'Foto Nota Awal harus berupa JPG, JPEG, PNG, atau WEBP.',
            'voids.*.voided_receipt.mimes' => 'Foto Nota Setelah Void harus berupa JPG, JPEG, PNG, atau WEBP.',
            'voids.*.original_receipt.max' => 'Ukuran Foto Nota Awal maksimal 5 MB.',
            'voids.*.voided_receipt.max' => 'Ukuran Foto Nota Setelah Void maksimal 5 MB.',
        ];
    }

    /**
     * @param  array{employeeIds: array<int, int>, rows: array<int, array<string, mixed>>, compliments: array<int, array<string, mixed>>, voids: array<int, array<string, mixed>>}  $input
     * @param  array{transactions: list<array<string, mixed>>, payment_methods: list<string>}|null  $snapshot  server-side ESB snapshot of this shift
     * @return array{report: SalesReport, completed_all_shifts: bool}
     *
     * @throws AuthorizationException|ValidationException
     */
    public function execute(User $actor, string $reportDate, int $shiftNumber, array $input, ?array $snapshot): array
    {
        $branch = $actor->branch;
        if (! $branch || ! $branch->hasSalesShift($shiftNumber)) {
            throw new AuthorizationException('Akun ini tidak dapat mengirim Sales Report untuk shift tersebut.');
        }

        $input = ['employeeIds' => [], 'rows' => [], 'compliments' => [], 'voids' => [], ...$input];
        Validator::make($input, self::rules($actor), self::messages())->validate();

        $transactions = collect($snapshot['transactions'] ?? [])->keyBy(fn (array $transaction): string => (string) $transaction['sales_num']);
        $paymentMethods = collect($snapshot['payment_methods'] ?? []);
        $compliments = $this->resolveCompliments(array_values($input['compliments']), $transactions);
        $voids = $this->resolveVoids(array_values($input['voids']), $transactions, $paymentMethods);
        $employees = Employee::query()->whereIn('id', $input['employeeIds'])->where('branch_id', $actor->branch_id)->where('is_active', true)->get();

        $storedPaths = [];
        $completedAllShifts = false;
        $report = null;

        try {
            $compliments = $compliments->map(function (array $compliment) use (&$storedPaths): array {
                $compliment['attachment_paths'] = collect($compliment['attachments'])->map(function (UploadedFile $file) use (&$storedPaths): string {
                    return $storedPaths[] = $this->evidence->store($file, SalesReportEvidenceStore::COMPLIMENT_DIRECTORY);
                })->all();
                unset($compliment['attachments']);

                return $compliment;
            });
            $voids = $voids->map(function (array $void) use (&$storedPaths): array {
                $void['original_receipt_path'] = $storedPaths[] = $this->evidence->store($void['original_receipt'], SalesReportEvidenceStore::VOID_DIRECTORY);
                $void['voided_receipt_path'] = $storedPaths[] = $this->evidence->store($void['voided_receipt'], SalesReportEvidenceStore::VOID_DIRECTORY);
                unset($void['original_receipt'], $void['voided_receipt']);

                return $void;
            });

            DB::transaction(function () use ($actor, $branch, $reportDate, $shiftNumber, $input, $employees, $compliments, $voids, $snapshot, &$completedAllShifts, &$report): void {
                $report = SalesReport::query()
                    ->where('branch_id', $actor->branch_id)
                    ->whereDate('report_date', $reportDate)
                    ->lockForUpdate()
                    ->first()
                    ?? SalesReport::query()->create([
                        'branch_id' => $actor->branch_id,
                        'report_date' => $reportDate,
                        'status' => SalesReportStatus::Draft->value,
                    ]);

                if ($report->isShiftSubmitted($shiftNumber)) {
                    throw ValidationException::withMessages(['shift' => 'Shift '.$shiftNumber.' sudah dikirim.']);
                }

                foreach ($input['rows'] as $row) {
                    SalesReportEntry::query()->create([
                        'sales_report_id' => $report->id,
                        'shift_number' => $shiftNumber,
                        'payment_method_name' => $row['name'],
                        'label' => $row['label'],
                        'sales_store_amount' => (float) ($row['sales_store'] ?? 0),
                        'notes' => trim((string) ($row['notes'] ?? '')) ?: null,
                    ]);
                }

                SalesReportShiftSubmission::query()->create([
                    'sales_report_id' => $report->id,
                    'shift_number' => $shiftNumber,
                    'submitted_by' => $actor->id,
                    'submitted_at' => now(),
                ]);

                $report->employees()->where('shift_number', $shiftNumber)->delete();
                $report->employees()->createMany($employees->map(fn (Employee $employee): array => [
                    'shift_number' => $shiftNumber,
                    'employee_id' => $employee->id,
                    'employee_code' => $employee->employee_code,
                    'employee_name' => $employee->name,
                    'employee_position' => $employee->position,
                ])->all());

                $report->compliments()->createMany($compliments->map(fn (array $compliment): array => [
                    ...$compliment, 'shift_number' => $shiftNumber, 'submitted_by' => $actor->id,
                ])->all());
                $report->voids()->createMany($voids->map(fn (array $void): array => [
                    ...$void, 'shift_number' => $shiftNumber, 'submitted_by' => $actor->id,
                ])->all());

                $report->replaceEsbTransactions($shiftNumber, $snapshot['transactions'] ?? []);
                $this->calculateBasketSize->execute($report, $shiftNumber);

                $report->refresh()->load('shiftSubmissions');
                $completedAllShifts = $report->allShiftsSubmitted($branch->salesShiftNumbers());

                if ($completedAllShifts) {
                    $report->update([
                        'status' => SalesReportStatus::PendingSupervisor->value,
                        'submitted_at' => now(),
                    ]);
                }
            });
        } catch (Throwable $exception) {
            $this->evidence->delete($storedPaths);

            throw $exception;
        }

        if ($completedAllShifts) {
            try {
                $this->reconciliation->reconcile($report->fresh());
            } catch (Throwable) {
                // Best-effort: the submission already succeeded. A Supervisor can retry the ESB
                // pull from the review page.
            }
        }

        return ['report' => $report->fresh(), 'completed_all_shifts' => $completedAllShifts];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  Collection<string, array<string, mixed>>  $transactions
     * @return Collection<int, array<string, mixed>>
     *
     * @throws ValidationException
     */
    private function resolveCompliments(array $rows, Collection $transactions): Collection
    {
        $types = ComplimentType::query()->whereIn('id', collect($rows)->pluck('compliment_type_id'))->where('is_active', true)->get()->keyBy('id');
        $seen = [];

        return collect($rows)->map(function (array $row, int $index) use ($types, $transactions, &$seen): array {
            $field = "compliments.{$index}";
            $salesNumber = $this->resolveSalesNumber($field, $row, $transactions);
            $duplicateKey = $salesNumber['sales_number'].'|'.$row['compliment_type_id'];
            if (isset($seen[$duplicateKey])) {
                throw ValidationException::withMessages(["{$field}.sales_number" => 'Compliment dengan Sales Number dan jenis yang sama sudah ditambahkan.']);
            }
            $seen[$duplicateKey] = true;

            return [
                ...$salesNumber,
                'compliment_type_id' => (int) $row['compliment_type_id'],
                'compliment_type_name' => $types[(int) $row['compliment_type_id']]->name,
                'notes' => trim((string) $row['notes']),
                'attachments' => array_values((array) $row['attachments']),
            ];
        });
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  Collection<string, array<string, mixed>>  $transactions
     * @param  Collection<int, string>  $paymentMethods
     * @return Collection<int, array<string, mixed>>
     *
     * @throws ValidationException
     */
    private function resolveVoids(array $rows, Collection $transactions, Collection $paymentMethods): Collection
    {
        $seen = [];

        return collect($rows)->map(function (array $row, int $index) use ($transactions, $paymentMethods, &$seen): array {
            $field = "voids.{$index}";
            $salesNumber = $this->resolveSalesNumber($field, $row, $transactions);
            if (isset($seen[$salesNumber['sales_number']])) {
                throw ValidationException::withMessages(["{$field}.sales_number" => 'Void dengan Sales Number yang sama sudah ditambahkan.']);
            }
            $seen[$salesNumber['sales_number']] = true;

            $paymentMethod = trim((string) $row['payment_method_name']);
            $paymentSource = SalesReportDataSource::from((string) $row['payment_method_source']);
            if ($paymentSource === SalesReportDataSource::EsbSnapshot && ! $paymentMethods->contains($paymentMethod)) {
                throw ValidationException::withMessages(["{$field}.payment_method_name" => 'Payment Method tidak ada pada daftar ESB shift ini. Pilih dari daftar atau gunakan Input Manual.']);
            }

            return [
                ...$salesNumber,
                'transaction_amount' => $salesNumber['sales_number_source'] === SalesReportDataSource::EsbSnapshot->value
                    ? $transactions[$salesNumber['sales_number']]['payment_total'] ?? null
                    : null,
                'payment_method_id' => null,
                'payment_method_name' => $paymentMethod,
                'payment_method_source' => $paymentSource->value,
                'reason' => trim((string) $row['reason']),
                'original_receipt' => $row['original_receipt'],
                'voided_receipt' => $row['voided_receipt'],
            ];
        });
    }

    /**
     * Verified numbers must exist in this shift's snapshot and take their context from it; a
     * manual number keeps its characters (only trimmed) and is stored unverified.
     *
     * @param  array<string, mixed>  $row
     * @param  Collection<string, array<string, mixed>>  $transactions
     * @return array{sales_number: string, sales_number_source: string, source_comcode: ?string, source_branch_code: ?string, sales_transaction_at: ?string}
     *
     * @throws ValidationException
     */
    private function resolveSalesNumber(string $field, array $row, Collection $transactions): array
    {
        $salesNumber = trim((string) $row['sales_number']);
        if ($row['sales_number_source'] !== SalesReportDataSource::EsbSnapshot->value) {
            return ['sales_number' => $salesNumber, 'sales_number_source' => SalesReportDataSource::Manual->value, 'source_comcode' => null, 'source_branch_code' => null, 'sales_transaction_at' => null];
        }

        $transaction = $transactions->get($salesNumber);
        if (! $transaction) {
            throw ValidationException::withMessages(["{$field}.sales_number" => 'Sales Number tidak ditemukan pada transaksi ESB shift ini. Pilih dari daftar atau gunakan Input Manual.']);
        }

        return [
            'sales_number' => $salesNumber,
            'sales_number_source' => SalesReportDataSource::EsbSnapshot->value,
            'source_comcode' => $transaction['source_comcode'] ?? null,
            'source_branch_code' => $transaction['source_branch_code'] ?? null,
            'sales_transaction_at' => $transaction['sales_date_out'] ?? null,
        ];
    }
}
