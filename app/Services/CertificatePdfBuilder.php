<?php

namespace App\Services;

use App\Models\Certificate;
use Barryvdh\DomPDF\Facade\Pdf;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\Storage;

class CertificatePdfBuilder
{
    /**
     * Costruisce l'oggetto PDF (DomPDF) per un Certificate, senza salvarlo.
     * Logica estratta dal vecchio Student\CertificateController per renderla
     * riusabile da Observer (persistenza alla creazione) e da Controller
     * (fallback on-the-fly per certificati legacy).
     */
    public function build(Certificate $cert): \Barryvdh\DomPDF\PDF
    {
        $student = $cert->student;
        $course = $cert->course; // può essere null se corso eliminato (Certificate ha snapshot completo)

        $verifyUrl = route('certificate.verify', ['code' => $cert->code]);
        $qrDataUri = Builder::create()
            ->writer(new PngWriter())
            ->data($verifyUrl)
            ->size(220)
            ->margin(8)
            ->build()
            ->getDataUri();

        $date = $cert->issued_at->locale('it')->isoFormat('D MMMM YYYY');

        return Pdf::loadView('pdf.certificate', [
            'cert' => $cert,
            'student' => $student,
            'course' => $course,
            'date' => $date,
            'verifyUrl' => $verifyUrl,
            'qrDataUri' => $qrDataUri,
        ])->setPaper('a4', 'landscape');
    }

    /**
     * Genera il PDF e lo salva sul disco 'local' nella cartella unsigned.
     * Ritorna il path relativo (utilizzabile con Storage::disk('local')).
     *
     * Idempotente: se il file esiste già viene sovrascritto. Utile per
     * eventuali rigenerazioni controllate (es. correzione di un dato
     * anagrafico nello snapshot del Certificate).
     */
    public function saveUnsigned(Certificate $cert): string
    {
        $path = $this->unsignedPathFor($cert);
        Storage::disk('local')->put($path, $this->build($cert)->output());
        return $path;
    }

    /**
     * Convenzione path per PDF non firmato.
     * Il code del Certificate è univoco e validato a creazione,
     * quindi safe come componente del filename (no path traversal).
     */
    public function unsignedPathFor(Certificate $cert): string
    {
        return "certificates/unsigned/{$cert->code}.pdf";
    }

    /**
     * Convenzione path per PDF firmato. Usata dall'admin UI (Step 2)
     * quando il legale rappresentante carica la versione firmata.
     */
    public function signedPathFor(Certificate $cert): string
    {
        return "certificates/signed/{$cert->code}.pdf";
    }
}
