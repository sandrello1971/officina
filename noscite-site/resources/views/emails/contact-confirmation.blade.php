<x-mail::message>
# Grazie per averci contattato, {{ $contact->name }}!

Abbiamo ricevuto il tuo messaggio e ti risponderemo il prima possibile, generalmente entro **24 ore lavorative**.

### Riepilogo del tuo messaggio

> {{ $contact->message }}

Nel frattempo, puoi esplorare il nostro sito per scoprire di piu su come possiamo aiutare la tua impresa.

<x-mail::button :url="route('home')">
Visita noscite.it
</x-mail::button>

Cordiali saluti,<br>
**Il team Noscite**<br>
<small>Umanesimo Digitale per le PMI — Milano, Italia</small>
</x-mail::message>
