<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var array<string, string> Old RequestStatus value => new DesignRequestStatus value. */
    private array $map = [
        'draft' => 'design_request',
        'submitted' => 'design_request',
        'in_review' => 'in_progress',
        'approved' => 'approval',
        'in_progress' => 'printing_process',
        'completed' => 'completed',
        'rejected' => 'rejected',
    ];

    /** @var array<string, string> New DesignRequestStatus value => old RequestStatus value (lossy). */
    private array $reverseMap = [
        'design_request' => 'submitted',
        'in_progress' => 'in_review',
        'approval' => 'approved',
        'printing_process' => 'in_progress',
        'completed' => 'completed',
        'rejected' => 'rejected',
    ];

    /**
     * Run the migrations.
     *
     * Snapshots each row's current status by ID first, then writes the
     * mapped value keyed by ID — avoids the classic bug where a
     * value-based UPDATE (old => new) cascades into a later iteration
     * whose "old" value equals an earlier iteration's "new" value.
     */
    public function up(): void
    {
        $this->remapByStatus('design_requests', 'status', $this->map);
        $this->remapByStatus('design_request_status_histories', 'from_status', $this->map, nullable: true);
        $this->remapByStatus('design_request_status_histories', 'to_status', $this->map);
    }

    /**
     * Reverse the migrations. Lossy: "design_request" could have originally
     * been "draft" or "submitted" — reversed to "submitted" as the closest
     * previously-valid state.
     */
    public function down(): void
    {
        $this->remapByStatus('design_requests', 'status', $this->reverseMap);
        $this->remapByStatus('design_request_status_histories', 'from_status', $this->reverseMap, nullable: true);
        $this->remapByStatus('design_request_status_histories', 'to_status', $this->reverseMap);
    }

    /** @param  array<string, string>  $map */
    private function remapByStatus(string $table, string $column, array $map, bool $nullable = false): void
    {
        $rows = DB::table($table)->select('id', $column)->get();

        foreach ($rows as $row) {
            $current = $row->{$column};
            if ($nullable && $current === null) {
                continue;
            }

            if (! array_key_exists($current, $map)) {
                continue;
            }

            DB::table($table)->where('id', $row->id)->update([$column => $map[$current]]);
        }
    }
};
