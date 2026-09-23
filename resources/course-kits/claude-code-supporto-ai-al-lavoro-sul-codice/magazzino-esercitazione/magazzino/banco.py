"""Simulazione del banco vendita: registra gli ordini della mattina e stampa i riepiloghi.

Uso: python -m magazzino.banco
"""
from .ordini import nuovo_ordine

LISTINO = {"A-100": 3.90, "A-101": 4.20, "C-300": 9.90, "D-900": 4.75}

MATTINA = [
    ("Ferramenta Rossi", "rivenditore", [("A-100", 20), ("A-101", 10)]),
    ("Mario Bianchi", "privato", [("C-300", 1)]),
    ("Edilnord srl", "rivenditore", [("D-900", 60)]),
]


def registra(cliente, tipo, articoli):
    ordine = nuovo_ordine(cliente, tipo)
    for codice, quantita in articoli:
        ordine.aggiungi(codice, LISTINO[codice], quantita)
    return ordine


def stampa(ordine):
    imponibile, iva, totale = ordine.totali()
    print(f"Ordine {ordine.numero} — {ordine.cliente} ({ordine.tipo_cliente})")
    for r in ordine.righe:
        print(f"   {r['codice']:<6} x{r['quantita']:>3}  a {r['prezzo']:.2f}")
    print(f"   imponibile {imponibile:.2f}  IVA {iva:.2f}  totale {totale:.2f}\n")


if __name__ == "__main__":
    for cliente, tipo, articoli in MATTINA:
        stampa(registra(cliente, tipo, articoli))
