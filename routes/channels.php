<?php

use App\Models\Conversation;
use Illuminate\Support\Facades\Broadcast;

/*
 * Ogni canale esiste in due forme: `t.{tenant}.<nome>` dentro un ente
 * (tenant_channel()) e `<nome>` fuori da un tenant. La forma con prefisso vale
 * solo per l'ente della richiesta di autorizzazione.
 */
$forTenant = fn (?string $tenant) => $tenant === null || $tenant === tenant()?->getTenantKey();

/**
 * Channel privato per-conversation: ricevono MessageSent solo i 2 partecipanti.
 * Usato in show.blade.php per appendere il nuovo messaggio live.
 */
$conversation = function ($user, string $id) {
    $conv = Conversation::find($id);
    if (!$conv) {
        return false;
    }
    return $user->id === $conv->student_id || $user->id === $conv->instructor_id;
};
Broadcast::channel('conversation.{id}', fn ($user, string $id) => tenant() === null && $conversation($user, $id));
Broadcast::channel('t.{tenant}.conversation.{id}', fn ($user, string $tenant, string $id) => $forTenant($tenant) && $conversation($user, $id));

/**
 * Channel privato per-utente: usato per badge unread live nella sidebar e per
 * notifiche di "nuovo thread aperto con te" quando l'utente non e' sulla pagina
 * del thread. Ognuno puo' subscribere solo al proprio user channel.
 */
Broadcast::channel('user.{id}', fn ($user, string $id) => tenant() === null && $user->id === $id);
Broadcast::channel('t.{tenant}.user.{id}', fn ($user, string $tenant, string $id) => $forTenant($tenant) && $user->id === $id);

/**
 * Presence channel per-conversation: typing indicator. I 2 partecipanti
 * vedono chi e' "presente" (sta guardando il thread) e ricevono i whisper
 * events "typing" via .listenForWhisper('typing', ...).
 * Ritorna le info utente che vengono distribuite ai presenti.
 */
$presence = function ($user, string $id) {
    $conv = Conversation::find($id);
    if (!$conv) {
        return false;
    }
    if ($user->id !== $conv->student_id && $user->id !== $conv->instructor_id) {
        return false;
    }
    return ['id' => $user->id, 'name' => $user->name];
};
Broadcast::channel('presence-conversation.{id}', fn ($user, string $id) => tenant() === null ? $presence($user, $id) : false);
Broadcast::channel('presence-t.{tenant}.conversation.{id}', fn ($user, string $tenant, string $id) => $forTenant($tenant) ? $presence($user, $id) : false);
