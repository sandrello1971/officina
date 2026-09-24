<?php

namespace App\Services;

use App\Models\Certificate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Tcpdf\Fpdi;

class CertificatePdfBuilder
{
    /**
     * Path relativo al base_path() del template PDF vettoriale.
     * Il template è caricato come "pagina importata" da FPDI e gli
     * elementi dinamici sono scritti sopra con TCPDF a coordinate in mm.
     * Il template stesso è generato da `php artisan certificates:build-template`
     * (App\Console\Commands\BuildCertificateTemplate), che usa le stesse
     * costanti di palette e layout definite qui: si modifica il layout in un
     * posto solo e si rigenera il template.
     */
    public const TEMPLATE_PATH = 'resources/pdf/templates/certificate-default.pdf';

    /**
     * Template in uso: quello committato (brand Effetto Glitch) per l'ente
     * primario, quello generato nello storage dell'ente per gli altri.
     */
    public static function templatePath(): string
    {
        $tenant = tenant();
        if ($tenant && ! $tenant->isPrimary()) {
            return storage_path('app/private/branding/certificate-template.pdf');
        }

        return base_path(self::TEMPLATE_PATH);
    }

    /**
     * Palette Effetto Glitch (RGB). INDIGO/VIOLET sono i due estremi del
     * gradiente del logo; INK è il fondo viola scurissimo del sito, usato
     * qui come colore del testo (il certificato si stampa: fondo bianco).
     */
    public const INK    = [20, 12, 49];
    public const MUTED  = [97, 94, 132];
    public const INDIGO = [87, 63, 255];
    public const VIOLET = [128, 28, 255];
    public const HAZE   = [205, 196, 255];

    /** Colonne del blocco dettagli (x centro in mm, larghezza). */
    public const COLUMNS = [
        'date'  => ['cx' => 88.5,  'w' => 58.0],
        'code'  => ['cx' => 148.5, 'w' => 58.0],
        'owner' => ['cx' => 208.5, 'w' => 58.0],
    ];

    /** Riga delle etichette del blocco dettagli (disegnate nel template). */
    public const DETAILS_LABEL_Y = 130.0;

    /**
     * Campi dinamici sul template A4 landscape (297×210mm).
     *  - y/h: cella in mm (testo centrato verticalmente nella cella);
     *  - col: colonna di self::COLUMNS (default: tutta la pagina);
     *  - fit: larghezza massima in mm; il corpo scende fino a min_size
     *    finché il testo ci sta (nomi e titoli di corso lunghi).
     * Font names = chiavi di self::FONTS.
     */
    private const FIELDS = [
        'student_name'  => ['y' => 55.0,  'h' => 14.0, 'font' => 'spacegroteskb',       'size' => 32,   'color' => self::INK,    'fit' => 240.0, 'min_size' => 20],
        'course_name'   => ['y' => 80.0,  'h' => 10.0, 'font' => 'spacegroteskmedium',  'size' => 19,   'color' => self::VIOLET, 'fit' => 240.0, 'min_size' => 11, 'uppercase' => true, 'spacing' => 0.6],
        'cert_subtitle' => ['y' => 91.0,  'h' => 6.0,  'font' => 'spacegrotesk',        'size' => 11.5, 'color' => self::INDIGO, 'fit' => 240.0, 'min_size' => 8],
        'score'         => ['y' => 102.0, 'h' => 9.0,  'font' => 'spacegroteskmedium',  'size' => 11,   'color' => self::VIOLET],
        'date_value'    => ['y' => 135.5, 'h' => 6.0,  'font' => 'spacegroteskmedium',  'size' => 12,   'color' => self::INK, 'col' => 'date'],
        'code_value'    => ['y' => 135.5, 'h' => 6.0,  'font' => 'jetbrainsmonomedium', 'size' => 11,   'color' => self::INK, 'col' => 'code', 'spacing' => 0.2],
        'owner_value'   => ['y' => 135.5, 'h' => 6.0,  'font' => 'spacegroteskmedium',  'size' => 12,   'color' => self::INK, 'col' => 'owner', 'min_size' => 8],
    ];

    /** Blocco QR + URL di verifica (basso a sinistra). */
    public const QR = ['x' => 36.5, 'y' => 155.0, 'size' => 22.0, 'block_x' => 25.0, 'block_w' => 45.0];

    /** Y della riga copyright: dentro la cornice interna del template. */
    public const COPYRIGHT_Y = 193.0;

    /**
     * Font custom: nome TCPDF => .ttf sorgente (relativo a base_path()).
     * Font del brand (business.effettoglitch.it): Space Grotesk + JetBrains
     * Mono, statici da Google Fonts, licenza OFL (accanto ai .ttf).
     * Il nome TCPDF è derivato dal nome del file, non scelto da noi:
     * ensureFonts() verifica che coincida.
     *
     * Le definizioni generate vivono in storage/fonts/tcpdf, NON in
     * vendor/tecnickcom/tcpdf/fonts: vendor/ è rigenerato da composer e un
     * deploy le aveva cancellate, rompendo l'emissione di tutti i certificati.
     */
    public const FONTS = [
        'spacegrotesk'        => 'resources/fonts/space-grotesk/SpaceGrotesk-Regular.ttf',
        'spacegroteskmedium'  => 'resources/fonts/space-grotesk/SpaceGrotesk-Medium.ttf',
        'spacegroteskb'       => 'resources/fonts/space-grotesk/SpaceGrotesk-Bold.ttf',
        'jetbrainsmono'       => 'resources/fonts/jetbrains-mono/JetBrainsMono-Regular.ttf',
        'jetbrainsmonomedium' => 'resources/fonts/jetbrains-mono/JetBrainsMono-Medium.ttf',
    ];

    public static function fontDir(): string
    {
        // Asset di piattaforma, condivisi da tutti gli enti: base_path e non
        // storage_path, che dentro un ente punta a storage/tenant<id>.
        return base_path('storage/fonts/tcpdf');
    }

    /**
     * Genera le definizioni TCPDF dei font mancanti. Idempotente: se sono già
     * su disco non fa nulla. Chiamata a ogni build (costo: un file_exists per font) e
     * dal comando pdf:register-tcpdf-fonts.
     *
     * @return array<string, string> nome TCPDF => path della definizione .php
     */
    public static function ensureFonts(): array
    {
        $dir = self::fontDir();
        $defs = [];
        foreach (array_keys(self::FONTS) as $name) {
            $defs[$name] = "{$dir}/{$name}.php";
        }

        $missing = array_filter($defs, fn (string $def) => !is_file($def));
        if ($missing === []) {
            return $defs;
        }

        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException("Impossibile creare la cartella font TCPDF: {$dir}");
        }

        // Lock: due richieste concorrenti non devono scrivere lo stesso file
        // mentre l'altra lo include a metà.
        $lock = fopen("{$dir}/.lock", 'c');
        flock($lock, LOCK_EX);
        try {
            foreach (array_keys($missing) as $name) {
                if (is_file($defs[$name])) {
                    continue; // generato da chi aveva il lock prima di noi
                }
                $ttf = base_path(self::FONTS[$name]);
                if (!is_file($ttf)) {
                    throw new \RuntimeException("Font sorgente mancante: {$ttf}");
                }
                $generated = \TCPDF_FONTS::addTTFfont($ttf, 'TrueTypeUnicode', '', 32, "{$dir}/");
                if ($generated !== $name) {
                    throw new \RuntimeException(
                        "Registrazione font {$ttf} fallita: atteso '{$name}', ottenuto '"
                        . var_export($generated, true) . "'"
                    );
                }
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }

        return $defs;
    }

    /**
     * Genera i bytes del PDF certificato.
     */
    public function build(Certificate $cert): string
    {
        $student = $cert->student;
        $course = $cert->course; // null safe: lo snapshot del Certificate ha tutti i dati
        $verifyUrl = route('certificate.verify', ['code' => $cert->code]);
        $date = Carbon::parse($cert->issued_at)->locale('it')->isoFormat('D MMMM YYYY');

        $templateAbsPath = self::templatePath();
        if (!file_exists($templateAbsPath) && $templateAbsPath !== base_path(self::TEMPLATE_PATH)) {
            // Template dell'ente non ancora generato: lo si crea al primo uso.
            \Illuminate\Support\Facades\Artisan::call('certificates:build-template');
        }
        if (!file_exists($templateAbsPath)) {
            throw new \RuntimeException("Template PDF mancante: {$templateAbsPath}");
        }

        $pdf = self::newDocument();
        $pdf->setSourceFile($templateAbsPath);
        $tplId = $pdf->importPage(1);
        $size = $pdf->getTemplateSize($tplId);

        $pdf->SetTitle("Certificato {$cert->code}");
        [$portraitW, $portraitH, $orientation] = self::pageFormat($size['width'], $size['height']);
        $pdf->AddPage($orientation, [$portraitW, $portraitH]);
        $pdf->useTemplate($tplId);

        $pageW = $orientation === 'L' ? $portraitH : $portraitW;
        $owner = atheneum_setting('platform_owner', 'Stefano Andrello');

        // === Campi dinamici ===
        self::writeField($pdf, $student->name, self::FIELDS['student_name'], $pageW);

        $courseName = $course?->name ?? $cert->certification_name;
        self::writeField($pdf, $courseName, self::FIELDS['course_name'], $pageW);

        if ($cert->certification_name && $cert->certification_name !== $courseName) {
            self::writeField($pdf, $cert->certification_name, self::FIELDS['cert_subtitle'], $pageW);
        }

        // Score: pill compatta disegnata qui (non nel template) perché esiste
        // solo se c'è un punteggio. Fill bianco = fondo del template.
        if ($cert->score) {
            $cfg = self::FIELDS['score'];
            $pillW = 52.0;
            $pillX = ($pageW - $pillW) / 2.0;
            $pdf->SetFillColor(255, 255, 255);
            $pdf->SetDrawColor(...self::VIOLET);
            $pdf->SetLineWidth(0.35);
            $pdf->RoundedRect($pillX, $cfg['y'], $pillW, $cfg['h'], $cfg['h'] / 2.0, '1111', 'DF');
            self::writeField($pdf, "Punteggio: {$cert->score}%", $cfg + ['x' => $pillX, 'w' => $pillW], $pageW);
        }

        self::writeField($pdf, $date, self::FIELDS['date_value'], $pageW);
        self::writeField($pdf, $cert->code, self::FIELDS['code_value'], $pageW);
        self::writeField($pdf, $owner, self::FIELDS['owner_value'], $pageW);

        // === QR + verify URL (basso a sinistra) ===
        $qr = self::QR;
        $pdf->write2DBarcode($verifyUrl, 'QRCODE,M', $qr['x'], $qr['y'], $qr['size'], $qr['size'], [
            'border'  => 0,
            'padding' => 0,
            'fgcolor' => self::INK,
            'bgcolor' => false,
        ], 'N');

        $pdf->SetFont('jetbrainsmonomedium', '', 6);
        $pdf->SetTextColor(...self::MUTED);
        $pdf->setFontSpacing(0.5);
        $pdf->SetXY($qr['block_x'], $qr['y'] + $qr['size'] + 1.5);
        $pdf->Cell($qr['block_w'], 3, mb_strtoupper('Verifica online'), 0, 0, 'C');

        $pdf->SetFont('jetbrainsmono', '', 5);
        $pdf->setFontSpacing(0);
        $pdf->SetXY($qr['block_x'], $qr['y'] + $qr['size'] + 5.0);
        $pdf->MultiCell($qr['block_w'], 2.5, self::verifyUrlLines($verifyUrl), 0, 'C');

        // === Copyright (tutela diritto d'autore) ===
        // In piccolo, centrato, dentro la cornice interna del template
        // (auto page-break OFF: coordinate fisse). Space Grotesk ha il glifo ©.
        $notice = copyright_notice();
        if ($notice !== '') {
            $pdf->SetFont('spacegrotesk', '', 6);
            $pdf->SetTextColor(...self::MUTED);
            $pdf->setFontSpacing(0);
            $pdf->SetXY(0, self::COPYRIGHT_Y);
            $pdf->Cell($pageW, 4, $notice, 0, 0, 'C');
        }

        // === Output bytes ===
        // 'S' = ritorna come stringa (i bytes del PDF)
        return $pdf->Output('', 'S');
    }

    /**
     * Documento TCPDF/FPDI con font del brand e impostazioni comuni a
     * certificato e template (nessun header/footer, niente page-break).
     */
    public static function newDocument(): Fpdi
    {
        $pdf = new Fpdi();
        foreach (self::ensureFonts() as $name => $def) {
            $pdf->AddFont($name, '', $def);
        }
        $pdf->SetCreator('Officina — Effetto Glitch');
        $pdf->SetAuthor(atheneum_setting('platform_owner', 'Stefano Andrello'));
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->SetMargins(0, 0, 0);

        return $pdf;
    }

    /**
     * AddPage vuole il format in ordine PORTRAIT [minore, maggiore] e swappa
     * da sé con orientation 'L': passare un format già landscape produce una
     * pagina con dimensioni invertite.
     *
     * @return array{0: float, 1: float, 2: string}
     */
    public static function pageFormat(float $width, float $height): array
    {
        return [min($width, $height), max($width, $height), $width >= $height ? 'L' : 'P'];
    }

    /**
     * Scrive un campo centrato nella sua cella: tutta la pagina, una colonna
     * di self::COLUMNS, oppure x/w espliciti. Con 'fit' (o in colonna) il
     * corpo scende di mezzo punto alla volta fino a min_size finché il testo
     * sta nella larghezza.
     */
    public static function writeField(Fpdi $pdf, string $text, array $cfg, float $pageW): void
    {
        if (isset($cfg['col'])) {
            $col = self::COLUMNS[$cfg['col']];
            $x = $col['cx'] - $col['w'] / 2.0;
            $w = $col['w'];
        } else {
            $x = $cfg['x'] ?? 0.0;
            $w = $cfg['w'] ?? $pageW;
        }

        $text = !empty($cfg['uppercase']) ? mb_strtoupper($text) : $text;
        $spacing = $cfg['spacing'] ?? 0;
        $maxW = min($cfg['fit'] ?? $w, $w);
        $size = $cfg['size'];
        $minSize = $cfg['min_size'] ?? $size;

        $pdf->setFontSpacing($spacing);
        $pdf->SetFont($cfg['font'], '', $size);
        while ($size > $minSize && $pdf->GetStringWidth($text) > $maxW) {
            $size -= 0.5;
            $pdf->SetFont($cfg['font'], '', $size);
        }

        $pdf->SetTextColor(...$cfg['color']);
        $pdf->SetXY($x, $cfg['y']);
        $pdf->Cell($w, $cfg['h'], $text, 0, 0, 'C', false, '', 1); // stretch=1: comprime solo se ancora troppo largo
        $pdf->setFontSpacing(0);
    }

    /**
     * URL di verifica su tre righe: dominio / percorso / codice. In monospazio
     * l'a capo automatico spezzava in un punto qualunque, anche a metà codice
     * (che è la parte che una persona deve poter ricopiare).
     */
    public static function verifyUrlLines(string $url): string
    {
        $parts = parse_url($url);
        if (!isset($parts['scheme'], $parts['host'], $parts['path'])) {
            return $url;
        }
        $origin = $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
        $slash = strrpos($parts['path'], '/');

        return $origin . "\n" . substr($parts['path'], 0, $slash + 1) . "\n" . substr($parts['path'], $slash + 1);
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
        Storage::disk('local')->put($path, $this->build($cert));
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
     * Convenzione path per PDF firmato. Usata dall'admin UI
     * quando il legale rappresentante carica la versione firmata.
     */
    public function signedPathFor(Certificate $cert): string
    {
        return "certificates/signed/{$cert->code}.pdf";
    }
}
