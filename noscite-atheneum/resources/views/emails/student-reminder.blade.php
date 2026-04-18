@component('mail::message')
# Ciao {{ $student->name }}!

Sono passati alcuni giorni dall'ultima volta che hai visitato **Atheneum Noscite**.

Il tuo percorso formativo ti aspetta. Ogni modulo che completi è un passo concreto verso una gestione più efficace dell'AI nella tua azienda.

@component('mail::button', ['url' => 'https://atheneum.noscite.it/learn/dashboard', 'color' => 'success'])
Riprendi il tuo percorso →
@endcomponent

Hai domande sui contenuti? Il chatbot **Minerva** è sempre disponibile nell'area studenti.

*In digitālī nova virtūs*

**Team Noscite**
@endcomponent
