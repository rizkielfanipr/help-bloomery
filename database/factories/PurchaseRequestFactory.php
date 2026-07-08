<?php

namespace Database\Factories;

use App\Enums\PurchaseRequestStatus;
use App\Enums\PurchaseType;
use App\Models\Branch;
use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseRequest>
 */
class PurchaseRequestFactory extends Factory
{
    private static array $items = [
        'Laptop Dell Inspiron 15', 'Mouse Logitech MX Master', 'Keyboard Mechanical Keychron',
        'Monitor LG 24"', 'Printer Canon PIXMA', 'Meja Kerja Lipat', 'Kursi Ergonomis',
        'Lampu Meja LED', 'Kabel LAN Cat6 10m', 'Switch Hub 8 Port', 'Tinta Printer Epson',
        'Kertas HVS A4 80gsm', 'Stopkontak 5 Lubang', 'UPS APC 650VA', 'Headset Jabra Evolve',
        'Webcam Logitech C920', 'Flash Disk 64GB SanDisk', 'Hard Disk Eksternal 1TB',
        'Baterai AA Alkaline (pack)', 'Stapler Besar Joyko',
    ];

    private static array $divisions = [
        'IT', 'Finance', 'HRD', 'Operations', 'Marketing', 'Procurement', 'Logistik', 'General Affairs',
    ];

    public function definition(): array
    {
        $status = fake()->randomElement(PurchaseRequestStatus::cases());
        $isProcessed = ! in_array($status, [PurchaseRequestStatus::Submitted]);

        return [
            'user_id' => User::inRandomOrder()->value('id'),
            'branch_id' => Branch::inRandomOrder()->value('id'),
            'division' => fake()->randomElement(self::$divisions),
            'item_name' => fake()->randomElement(self::$items),
            'quantity' => fake()->numberBetween(1, 10),
            'purchase_reason' => fake()->sentence(10),
            'purchase_type' => fake()->randomElement(PurchaseType::cases()),
            'journal_item_number' => fake()->boolean(40) ? strtoupper(fake()->bothify('JRN-####-??')) : null,
            'purchase_request_number' => strtoupper(fake()->bothify('PR-####-??')),
            'ecommerce_link' => fake()->boolean(30) ? fake()->url() : null,
            'attachment_paths' => null,
            'status' => $status,
            'admin_notes' => $isProcessed ? fake()->optional(0.5)->sentence(8) : null,
            'processed_by' => $isProcessed ? User::inRandomOrder()->value('id') : null,
        ];
    }

    public function submitted(): static
    {
        return $this->state(['status' => PurchaseRequestStatus::Submitted, 'processed_by' => null, 'admin_notes' => null]);
    }

    public function inProcess(): static
    {
        return $this->state(['status' => PurchaseRequestStatus::InProcess]);
    }

    public function completed(): static
    {
        return $this->state(['status' => PurchaseRequestStatus::Completed]);
    }
}
