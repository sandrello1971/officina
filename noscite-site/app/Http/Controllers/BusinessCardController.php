<?php

namespace App\Http\Controllers;

use App\Models\BusinessCard;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class BusinessCardController extends Controller
{
    public function show(string $username): View
    {
        $card = BusinessCard::where('username', $username)
            ->where('is_active', true)
            ->firstOrFail();

        $card->increment('views_count');

        return view('business-card.show', compact('card'));
    }

    public function vcard(string $username): Response
    {
        $card = BusinessCard::where('username', $username)
            ->where('is_active', true)
            ->firstOrFail();

        $vcard = "BEGIN:VCARD\r\n";
        $vcard .= "VERSION:3.0\r\n";
        $vcard .= "FN:{$card->full_name}\r\n";

        if ($card->email) {
            $vcard .= "EMAIL:{$card->email}\r\n";
        }
        if ($card->phone) {
            $vcard .= "TEL:{$card->phone}\r\n";
        }
        if ($card->company) {
            $vcard .= "ORG:{$card->company}\r\n";
        }
        if ($card->role) {
            $vcard .= "TITLE:{$card->role}\r\n";
        }
        if ($card->website) {
            $vcard .= "URL:{$card->website}\r\n";
        }
        if ($card->bio) {
            $vcard .= "NOTE:{$card->bio}\r\n";
        }

        $vcard .= "END:VCARD\r\n";

        $filename = str_replace(' ', '_', $card->full_name) . '.vcf';

        return response($vcard, 200, [
            'Content-Type' => 'text/vcard; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
