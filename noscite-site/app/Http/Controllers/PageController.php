<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class PageController extends Controller
{
    public function index(): View
    {
        return view('pages.index');
    }

    public function profilumSocietatis(): View
    {
        return view('pages.profilum-societatis');
    }

    public function fundamenta(): View
    {
        return view('pages.fundamenta');
    }

    public function methodus(): View
    {
        return view('pages.methodus');
    }

    public function valor(): View
    {
        return view('pages.valor');
    }

    public function atheneum(): View
    {
        return view('pages.atheneum');
    }

    public function chiSiamo(): View
    {
        return view('pages.chi-siamo');
    }

    public function servizi(): View
    {
        return view('pages.servizi');
    }

    public function percorsi(): View
    {
        return view('pages.percorsi');
    }

    public function risorse(): View
    {
        return view('pages.risorse');
    }

    public function contatti(): View
    {
        return view('pages.contatti');
    }

    public function contactus(): View
    {
        return view('pages.contactus');
    }

    public function privacyPolicy(): View
    {
        return view('pages.privacy-policy');
    }

    public function cookiePolicy(): View
    {
        return view('pages.cookie-policy');
    }

    public function jooiceLanding(): View
    {
        return view('pages.jooice');
    }
}
