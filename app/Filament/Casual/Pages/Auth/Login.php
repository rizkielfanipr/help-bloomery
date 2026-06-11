<?php

namespace App\Filament\Casual\Pages\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class Login extends BaseLogin
{
    protected static string $layout = 'filament.casual.layouts.bare';

    protected string $view = 'filament.casual.pages.auth.login';

    public function getTitle(): string|Htmlable
    {
        return new HtmlString('');
    }
}
