<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class PageController extends Controller
{
    public function index(): View
    {
        return view('home');
    }

    public function primus(): View
    {
        return view('primus');
    }

    public function consilium(): View
    {
        return view('consilium');
    }

    public function initium(): View
    {
        return view('initium');
    }

    public function structura(): View
    {
        return view('structura');
    }

    public function aiAgentsMcp(): View
    {
        return view('ai-agents-mcp');
    }

    public function risorse(): View
    {
        return view('risorse');
    }

    public function contatti(): View
    {
        return view('contatti');
    }

    public function privacyPolicy(): View
    {
        return view('privacy-policy');
    }

    public function cookiePolicy(): View
    {
        return view('cookie-policy');
    }
}
