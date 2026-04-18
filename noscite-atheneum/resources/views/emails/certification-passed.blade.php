@component('mail::message')
# 🎓 Congratulazioni, {{ $student->name }}!

Hai superato l'esame finale del corso **{{ $course->name }}** con un punteggio del **{{ $score }}%**.

Hai ottenuto la certificazione:

> **{{ $course->certification_name }}**

Il tuo certificato è allegato a questa email in formato PDF. Puoi anche scaricarlo in qualsiasi momento dalla piattaforma Atheneum.

@component('mail::button', ['url' => 'https://atheneum.noscite.it/learn/dashboard', 'color' => 'success'])
Vai ad Atheneum
@endcomponent

**Continua ad imparare.** Il tuo percorso Noscite prosegue con gli altri corsi del portfolio.

*In digitālī nova virtūs*

**Team Noscite**
@endcomponent
