<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * URL assoluta sull'host di amministrazione. Le rotte admin sono vincolate
     * ad admin.* via Route::domain(), quindi un path relativo non le raggiunge.
     */
    protected function adminUrl(string $path = '/'): string
    {
        return 'https://' . config('domains.admin') . '/' . ltrim($path, '/');
    }

    /** URL assoluta sull'host discenti (area learn, docente, scuola). */
    protected function learnUrl(string $path = '/'): string
    {
        return 'https://' . config('domains.learn') . '/' . ltrim($path, '/');
    }

    /** URL assoluta sull'host di vetrina. */
    protected function siteUrl(string $path = '/'): string
    {
        return 'https://' . config('domains.site') . '/' . ltrim($path, '/');
    }
}
