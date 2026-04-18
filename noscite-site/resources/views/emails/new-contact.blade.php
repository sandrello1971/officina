<x-mail::message>
# Nuovo messaggio dal sito

Hai ricevuto un nuovo messaggio dal modulo di contatto di **noscite.it**.

<x-mail::table>
| Campo | Dettaglio |
|:------|:----------|
| **Nome** | {{ $contact->name }} |
| **Email** | {{ $contact->email }} |
| **Telefono** | {{ $contact->phone ?? '—' }} |
| **Azienda** | {{ $contact->company ?? '—' }} |
| **Data** | {{ $contact->created_at->format('d/m/Y H:i') }} |
| **IP** | {{ $contact->ip_address ?? '—' }} |
</x-mail::table>

### Messaggio

{{ $contact->message }}

<x-mail::button :url="route('admin.contacts')">
Vai ai messaggi in admin
</x-mail::button>

Noscite — Umanesimo Digitale per le PMI
</x-mail::message>
