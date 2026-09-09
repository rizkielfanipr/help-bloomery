<?php

namespace App\Observers;

use App\Models\Asset;
use App\Services\QrCodeGenerator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AssetObserver
{
    public function __construct(private readonly QrCodeGenerator $qrCodeGenerator) {}

    public function creating(Asset $asset): void
    {
        $asset->qr_token ??= (string) Str::uuid();
    }

    public function created(Asset $asset): void
    {
        $asset->asset_number = sprintf('AST-BR%04d-%s-%06d', $asset->branch_id, $asset->created_at->format('Y'), $asset->id);
        $asset->qr_svg_path = $this->generateQr($asset);
        $asset->saveQuietly();
    }

    public function deleted(Asset $asset): void
    {
        if ($asset->qr_svg_path) {
            Storage::disk('b2')->delete($asset->qr_svg_path);
        }
    }

    public function regenerate(Asset $asset): string
    {
        $path = $this->generateQr($asset);
        $asset->updateQuietly(['qr_svg_path' => $path]);

        return $path;
    }

    private function generateQr(Asset $asset): string
    {
        return $this->qrCodeGenerator->generate(
            route('assets.scan', $asset->qr_token),
            "assets/{$asset->branch_id}/{$asset->id}.svg",
        );
    }
}
