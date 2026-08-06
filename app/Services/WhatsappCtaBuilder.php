<?php

namespace App\Services;

use App\Models\WhatsappNotificationSetting;

class WhatsappCtaBuilder
{
    /** @param  array<string, string>  $values */
    public function build(string $moduleKey, array $values): ?string
    {
        $setting = WhatsappNotificationSetting::forModule($moduleKey);

        if (! $setting->is_enabled) {
            return null;
        }

        $phone = $this->normalizePhone((string) $setting->pic?->phone);

        if ($phone === '') {
            return null;
        }

        $message = urlencode($setting->renderMessage($values));

        return 'https://wa.me/'.$phone.'?text='.$message;
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);

        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        }

        return $digits;
    }
}
