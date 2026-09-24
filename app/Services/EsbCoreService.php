<?php

namespace App\Services;

/**
 * Compatibility facade while consumers migrate to domain-specific ESB services.
 */
class EsbCoreService
{
    public function __construct(
        private readonly EsbBillOfMaterialService $billOfMaterials,
        private readonly EsbPurchaseOrderService $purchaseOrders,
        private readonly EsbMasterProductService $products,
    ) {}

    /** @return array{page:int,limit:int,count:int,data:array<int,mixed>,prev:?string,next:?string} */
    public function getBillOfMaterials(array $filters = []): array
    {
        return $this->billOfMaterials->getBillOfMaterials($filters);
    }

    public function createAssembly(array $payload): int
    {
        return $this->billOfMaterials->createAssembly($payload);
    }

    /** @return array{productID:int,isTemp:bool} */
    public function createProduct(array $payload): array
    {
        return $this->products->createProduct($payload);
    }

    public function updateProduct(int $productId, array $payload): void
    {
        $this->products->updateProduct($productId, $payload);
    }

    public function findProductByExactName(string $productName): ?array
    {
        return $this->products->findProductByExactName($productName);
    }

    /** @return array<int, array> */
    public function getAllProducts(): array
    {
        return $this->products->getAllProducts();
    }

    public function findProductById(int $productId): ?array
    {
        return $this->products->findProductById($productId);
    }

    /** @return array<string, mixed> */
    public function getBillOfMaterial(int $bomId): array
    {
        return $this->billOfMaterials->getBillOfMaterial($bomId);
    }

    /** @return array{page:int,limit:int,count:int,data:array<int,mixed>,prev:?string,next:?string} */
    public function getPurchaseOrders(array $filters = []): array
    {
        return $this->purchaseOrders->getPurchaseOrders($filters);
    }

    /** @return array<string, mixed> */
    public function getPurchaseOrder(string $purchaseNum): array
    {
        return $this->purchaseOrders->getPurchaseOrder($purchaseNum);
    }

    public function updateBillOfMaterial(int $bomId, array $payload): void
    {
        $this->billOfMaterials->updateBillOfMaterial($bomId, $payload);
    }

    /** @return array<int, array> */
    public function getAllBillOfMaterials(): array
    {
        return $this->billOfMaterials->getAllBillOfMaterials();
    }

    /** @return array{page:int,limit:int,count:int,data:array<int,mixed>,prev:?string,next:?string} */
    public function getProducts(array $filters = []): array
    {
        return $this->products->getProducts($filters);
    }

    /** @return array{categories:array<int,string>,subCategories:array<int,string>} */
    public function getProductTaxonomy(): array
    {
        return $this->products->getProductTaxonomy();
    }

    public function suggestNextProductCode(int $categoryId): ?string
    {
        return $this->products->suggestNextProductCode($categoryId);
    }
}
