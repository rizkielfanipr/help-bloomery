<?php

use App\Actions\Receiving\RecordInboundGoodsReceiptAction;
use App\Models\GoodsReceipt;
use App\Models\User;

it('rolls back a receipt when its item persistence fails', function () {
    $actor = User::factory()->create();
    $form = [
        'purchaseOrder' => ['purchaseDate' => '2026-09-18', 'branchID' => 1, 'branchName' => 'Kitchen', 'supplierID' => 1, 'supplierName' => 'Vendor'],
        'goodsReceiptDate' => '2026-09-18', 'deliveryNumber' => 'DO001', 'deliveryDate' => '2026-09-18',
        'invoiceStatus' => 'received', 'invoiceNumber' => 'INV001', 'invoiceDate' => '2026-09-18',
        'poDocumentMatch' => true, 'deliveryDocumentMatch' => true, 'invoiceDocumentMatch' => true, 'priceMatch' => true,
        'documentNotes' => null, 'additionalInfo' => null, 'autoClosePo' => false,
    ];
    $brokenItems = collect([['acceptedQty' => 1, 'holdQty' => 0, 'rejectedQty' => 0]]);

    expect(fn () => app(RecordInboundGoodsReceiptAction::class)->execute($actor, 'PO001', ['locationID' => 1, 'locationName' => 'Kitchen'], [], $brokenItems, [], $form))->toThrow(ErrorException::class);
    expect(GoodsReceipt::query()->count())->toBe(0);
});
