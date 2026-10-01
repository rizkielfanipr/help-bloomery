<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * docs/customer-complaints-prd.md §10.3 lists Internal Notes as a Follow-up field, but §14.1's
     * column list is explicitly only the "minimum" set and does not mention it. Added here as its
     * own additive migration rather than editing the already-created customer_complaints table.
     */
    public function up(): void
    {
        Schema::table('customer_complaints', function (Blueprint $table): void {
            $table->text('internal_notes')->nullable()->after('resolution');
        });
    }

    public function down(): void
    {
        Schema::table('customer_complaints', function (Blueprint $table): void {
            $table->dropColumn('internal_notes');
        });
    }
};
