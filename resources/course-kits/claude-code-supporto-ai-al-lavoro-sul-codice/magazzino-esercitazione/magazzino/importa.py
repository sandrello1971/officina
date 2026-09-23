"""Import delle giacenze esportate dal gestionale (CSV separato da punto e virgola)."""
import csv


def _numero(testo):
    return float(testo.strip())


def importa_giacenze(percorso):
    """Legge il CSV del gestionale e restituisce {codice: {"descrizione", "quantita", "prezzo"}}.

    Colonne attese: codice;descrizione;quantita;prezzo
    """
    giacenze = {}
    with open(percorso, newline="", encoding="utf-8") as f:
        lettore = csv.DictReader(f, delimiter=";")
        for riga in lettore:
            try:
                giacenze[riga["codice"].strip()] = {
                    "descrizione": riga["descrizione"].strip(),
                    "quantita": int(_numero(riga["quantita"])),
                    "prezzo": _numero(riga["prezzo"]),
                }
            except Exception:
                # righe sporche del gestionale: le saltiamo
                continue
    return giacenze
