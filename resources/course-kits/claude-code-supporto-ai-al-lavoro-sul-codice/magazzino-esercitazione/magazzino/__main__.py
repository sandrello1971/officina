"""Uso: python -m magazzino dati/giacenze_esempio.csv"""
import sys

from .importa import importa_giacenze


def main(argv):
    if len(argv) != 2:
        print(__doc__)
        return 1
    giacenze = importa_giacenze(argv[1])
    valore = 0.0
    for codice, art in sorted(giacenze.items()):
        print(f"{codice:<8} {art['descrizione']:<32} {art['quantita']:>6}  {art['prezzo']:>8.2f}")
        valore += art["quantita"] * art["prezzo"]
    print(f"\n{len(giacenze)} articoli — valore di magazzino: {valore:,.2f} EUR")
    return 0


sys.exit(main(sys.argv))
