"""Creazione e gestione degli ordini."""
import itertools

from .prezzi import totale_ordine

_progressivo = itertools.count(1)


class Ordine:
    def __init__(self, numero, cliente, tipo_cliente, righe):
        self.numero = numero
        self.cliente = cliente
        self.tipo_cliente = tipo_cliente
        self.righe = righe  # lista di dict {"codice", "prezzo", "quantita"}

    def aggiungi(self, codice, prezzo, quantita):
        self.righe.append({"codice": codice, "prezzo": prezzo, "quantita": quantita})

    def totali(self):
        return totale_ordine([(r["prezzo"], r["quantita"]) for r in self.righe], self.tipo_cliente)


def nuovo_ordine(cliente, tipo_cliente="privato", righe=[]):
    """Crea un ordine; `righe` permette di partire da righe già note (es. riordino)."""
    return Ordine(next(_progressivo), cliente, tipo_cliente, righe)
