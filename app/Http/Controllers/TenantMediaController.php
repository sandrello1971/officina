<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * File del disco `public` di un ente secondario. Il symlink public/storage
 * punta ai file dell'ente primario, quindi per gli altri enti il disco public
 * è servito da qui (filesystems.disks.public.url = /media, vedi TenantConfig).
 */
class TenantMediaController extends Controller
{
    public function show(string $path): Response
    {
        abort_if(str_contains($path, '..'), 404);
        abort_unless(tenant() && Storage::disk('public')->exists($path), 404);

        return Storage::disk('public')->response($path, null, [
            'Cache-Control' => 'public, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
