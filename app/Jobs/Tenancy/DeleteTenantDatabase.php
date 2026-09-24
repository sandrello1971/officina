<?php

declare(strict_types=1);

namespace App\Jobs\Tenancy;

use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Events\DatabaseDeleted;
use Stancl\Tenancy\Events\DeletingDatabase;

/**
 * Elimina il database del tenant SOLO se lo abbiamo creato noi.
 *
 * Sostituisce Stancl\Tenancy\Jobs\DeleteDatabase (che droppa SEMPRE) per non
 * distruggere un DB registrato ma non creato dalla piattaforma — tipicamente il
 * "tenant zero" (il DB di produzione esistente, agganciato con
 * tenancy_create_database=false). Per quei tenant `tenant:delete` rimuove solo
 * il record central e i domini, lasciando il database intatto.
 */
class DeleteTenantDatabase
{
    public function __construct(protected TenantWithDatabase $tenant) {}

    public function handle(): void
    {
        // Registrato (non creato da noi) → non droppare il DB.
        if ($this->tenant->getInternal('create_database') === false) {
            return;
        }

        event(new DeletingDatabase($this->tenant));
        $this->tenant->database()->manager()->deleteDatabase($this->tenant);
        event(new DatabaseDeleted($this->tenant));
    }
}
