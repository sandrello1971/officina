<x-mail::message>
# Benvenuto in Atheneum Noscite, {{ $student->name }}!

Il tuo account studente e stato attivato. Puoi accedere all'area riservata e iniziare il tuo percorso formativo.

@if(count($courseNames) > 0)
## Corsi assegnati
@foreach($courseNames as $c)
- {{ $c }}
@endforeach
@endif

## Le tue credenziali

**URL:** {{ $loginUrl }}
**Email:** {{ $student->email }}
**Password temporanea:** `{{ $tempPassword }}`

<x-mail::button :url="$loginUrl">
Accedi ad Atheneum
</x-mail::button>

> Al primo accesso ti verra chiesto di impostare una password personale.
> Conserva questa email in un luogo sicuro fino a quando non avrai completato il primo accesso.

Se hai domande, scrivi a [info@noscite.it](mailto:info@noscite.it).

Buon lavoro,<br>
**Il team Noscite**<br>
<small>Atheneum Noscite — Umanesimo Digitale per le PMI</small>
</x-mail::message>
