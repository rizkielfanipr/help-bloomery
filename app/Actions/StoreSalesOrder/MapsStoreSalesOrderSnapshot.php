<?php

namespace App\Actions\StoreSalesOrder;

use App\Models\Branch;
use App\Models\BranchEsbCode;

/**
 * Shared by Create and Refresh (docs/store-sales-order-prd.md §9.2, §14): the exact same fields are
 * written whether a record is first created or later refreshed from ESB, so the two Actions never
 * drift on which columns count as "ESB snapshot".
 */
trait MapsStoreSalesOrderSnapshot
{
    /**
     * @param  array<string, mixed>  $snapshot  normalized output of EsbProductSalesService::exactLookup()
     * @return array<string, mixed>
     */
    private function snapshotAttributes(Branch $branch, BranchEsbCode $mapping, array $snapshot): array
    {
        return [
            'branch_id' => $branch->id,
            'branch_esb_code_id' => $mapping->id,
            'company_code_snapshot' => strtoupper(trim($mapping->esb_comcode)),
            'branch_code_snapshot' => $mapping->esb_branch_code,
            'esb_branch_id_snapshot' => $mapping->esb_branch_id,
            'branch_name_snapshot' => $snapshot['branch_name'] ?? $mapping->esb_branch_code,
            'product_sales_number' => $snapshot['product_sales_number'],
            'product_sales_date' => $snapshot['product_sales_date'],
            'required_date' => $snapshot['required_date'],
            'customer_id_snapshot' => $snapshot['customer_id'],
            'customer_name_snapshot' => $snapshot['customer_name'],
            'customer_address_snapshot' => $snapshot['customer_address'],
            'product_sales_total' => $snapshot['total'],
            'currency_sign' => $snapshot['currency_sign'],
            'esb_status_id' => $snapshot['status_id'],
            'esb_status_name' => $snapshot['status_name'],
            'esb_created_by' => $snapshot['created_by'],
            'link_purchase_number' => $snapshot['link_purchase_number'],
            'esb_additional_info' => $snapshot['additional_info'],
            'esb_snapshot' => $snapshot['raw'],
            'last_verified_at' => now(),
        ];
    }
}
