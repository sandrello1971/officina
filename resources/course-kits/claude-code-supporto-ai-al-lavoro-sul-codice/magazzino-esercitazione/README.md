# Magazzino — repository di esercitazione

Piccolo gestionale di magazzino di un distributore di ferramenta: import delle
giacenze dal gestionale aziendale, listino con sconti, ordini dal banco vendita.
È il **codice sconosciuto** su cui lavorerete nel Laboratorio 2 (e, se non avete
un repository vostro, anche nel Laboratorio 1).

Non serve installare nulla oltre a Python 3.10 o superiore: usa solo la libreria standard.

## Struttura

```
magazzino/
  importa.py   import del CSV esportato dal gestionale
  prezzi.py    regole commerciali: sconti a scaglioni, rivenditori, IVA
  ordini.py    creazione degli ordini
  banco.py     simulazione del banco vendita
  __main__.py  report delle giacenze
dati/
  giacenze_esempio.csv   export reale (anonimizzato) del gestionale
tests/                   test automatici
SEGNALAZIONI.md          i ticket aperti dall'assistenza: sono i bug da analizzare
```

## Comandi

```bash
python -m unittest                             # esegue i test
python -m magazzino dati/giacenze_esempio.csv  # report giacenze
python -m magazzino.banco                      # simula il banco vendita
```

## Come usarlo nel laboratorio

1. Aprite Claude Code nella cartella del repository e fatevi spiegare il modulo
   `prezzi.py` **senza leggerlo prima voi**. Poi verificate la spiegazione:
   quali file ha considerato? cosa non ha potuto verificare?
2. Prendete la segnalazione che vi assegna il formatore da `SEGNALAZIONI.md` e
   conducete il debug seguendo i passi della *Scheda di analisi bug*.
3. La suite di test è verde: non vuol dire che il codice sia corretto.
   Scrivete un test che **riproduce** il bug prima di correggerlo.
