<?php

namespace App\Filament\Helpdesk\Pages\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class Login extends BaseLogin
{
    protected static string $layout = 'filament.helpdesk.layouts.bare';

    protected string $view = 'filament.helpdesk.pages.auth.login';

    public function getTitle(): string|Htmlable
    {
        return new HtmlString('');
    }
}
