<?php

it('redirects the helpdesk root to its login page for guests', function () {
    $this->get('/')->assertRedirect(route('filament.helpdesk.auth.login'));
});
