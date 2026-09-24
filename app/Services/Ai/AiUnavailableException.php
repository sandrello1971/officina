<?php

namespace App\Services\Ai;

/**
 * L'AI non è utilizzabile per l'ente corrente: chiave mancante o budget
 * mensile sulla chiave di piattaforma esaurito. È una RuntimeException, così
 * i call-site che già gestiscono gli errori AI la trattano allo stesso modo.
 */
class AiUnavailableException extends \RuntimeException
{
}
