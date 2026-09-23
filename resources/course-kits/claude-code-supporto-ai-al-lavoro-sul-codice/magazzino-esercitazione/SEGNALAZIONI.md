# Segnalazioni aperte

Ticket arrivati all'assistenza. Sono scritti da chi usa il programma: descrivono
il **sintomo**, non la causa. Il vostro compito è arrivare alla causa radice.

---

## #1 — Dopo l'import mancano articoli e una giacenza è sbagliata
*Aperto da: ufficio acquisti — priorità alta*

> Abbiamo importato l'export di stamattina del gestionale (`dati/giacenze_esempio.csv`,
> 8 articoli). Nel report ne compaiono solo 6: mancano il nastro carta B-011 e il
> silicone D-900. Nessun messaggio di errore.
> Inoltre il nastro telato B-010 risulta con giacenza 1, ma a scaffale ce ne sono
> più di mille. Il valore di magazzino non torna con quello del gestionale.

Per riprodurre: `python -m magazzino dati/giacenze_esempio.csv`

---

## #2 — Il totale non quadra di un centesimo
*Aperto da: amministrazione — priorità media*

> Il cliente Mario Bianchi (privato) ha ordinato 11 confezioni di viti A-100
> (3,90 € l'una). Lui si è rifatto il conto in Excel: con lo sconto quantità del 5%
> l'imponibile gli viene 40,76 €, a noi 40,75 €. Capita solo ogni tanto, con alcune
> quantità: con 10 o 12 confezioni i conti tornano. In fattura elettronica un
> centesimo di differenza ci fa scartare il documento dal cliente.

Per riprodurre: `python3 -c "from magazzino.prezzi import importo_riga; print(importo_riga(3.90, 11))"`

---

## #3 — Negli ordini compaiono articoli di altri clienti
*Aperto da: banco vendita — priorità alta*

> Il primo ordine della mattina è sempre giusto. Dal secondo in poi ogni ordine
> contiene anche gli articoli dei clienti precedenti, e i totali crescono.
> Se chiudiamo e riapriamo il programma il primo ordine torna giusto.
> I test automatici sono tutti verdi.

Per riprodurre: `python -m magazzino.banco`
