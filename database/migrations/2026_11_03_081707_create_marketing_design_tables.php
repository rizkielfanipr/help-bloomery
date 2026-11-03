<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_design_bundles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->restrictOnDelete();
            $table->string('code', 100);
            $table->string('normalized_code', 100);
            $table->string('name');
            $table->string('category', 30);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('archived_at')->nullable();
            $table->text('archive_reason')->nullable();
            $table->timestamps();

            $table->unique(['brand_id', 'normalized_code'], 'md_bundles_brand_code_unique');
            $table->index(['status', 'category'], 'md_bundles_status_category_index');
        });

        Schema::create('marketing_design_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketing_design_bundle_id')->constrained('marketing_design_bundles', 'id', 'md_items_bundle_fk')->restrictOnDelete();
            $table->string('type', 30);
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('dimensions')->nullable();
            $table->string('material_specification')->nullable();
            $table->string('finishing')->nullable();
            $table->string('color_print_notes')->nullable();
            $table->string('sides', 100)->nullable();
            $table->text('production_notes')->nullable();
            $table->timestamps();

            $table->index(['marketing_design_bundle_id', 'sort_order'], 'md_items_bundle_sort_index');
            $table->index(['type', 'marketing_design_bundle_id'], 'md_items_type_bundle_index');
        });

        Schema::create('marketing_design_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketing_design_item_id')->constrained('marketing_design_items', 'id', 'md_files_item_fk')->restrictOnDelete();
            $table->string('disk', 50);
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->string('extension', 10);
            $table->unsignedBigInteger('size_bytes');
            $table->char('checksum_sha256', 64);
            $table->foreignId('uploaded_by')->nullable()->constrained('users', 'id', 'md_files_uploaded_by_fk')->nullOnDelete();
            $table->timestamp('uploaded_at');
            $table->softDeletes();
            $table->timestamps();

            $table->index(['marketing_design_item_id', 'deleted_at'], 'md_files_item_deleted_index');
        });

        Schema::create('marketing_design_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketing_design_bundle_id')->constrained('marketing_design_bundles', 'id', 'md_audit_bundle_fk')->restrictOnDelete();
            $table->foreignId('marketing_design_item_id')->nullable()->constrained('marketing_design_items', 'id', 'md_audit_item_fk')->nullOnDelete();
            $table->foreignId('marketing_design_file_id')->nullable()->constrained('marketing_design_files', 'id', 'md_audit_file_fk')->nullOnDelete();
            $table->string('event', 40);
            $table->foreignId('actor_id')->nullable()->constrained('users', 'id', 'md_audit_actor_fk')->nullOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30)->nullable();
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['marketing_design_bundle_id', 'created_at'], 'md_audit_bundle_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_design_audit_logs');
        Schema::dropIfExists('marketing_design_files');
        Schema::dropIfExists('marketing_design_items');
        Schema::dropIfExists('marketing_design_bundles');
    }
};
