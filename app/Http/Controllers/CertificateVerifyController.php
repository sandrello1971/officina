<?php

namespace App\Http\Controllers;

use App\Models\Certificate;

class CertificateVerifyController extends Controller
{
    public function show(string $code)
    {
        $cert = Certificate::with([
                'student:id,name,email',
                'course:id,name,certification_name',
            ])
            ->where('code', $code)
            ->first();

        return view('certificate.verify', [
            'cert' => $cert,
            'code' => $code,
        ]);
    }
}
