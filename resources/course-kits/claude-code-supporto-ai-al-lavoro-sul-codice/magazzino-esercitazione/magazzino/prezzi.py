"""Calcolo dei prezzi di vendita.

Regole commerciali in vigore (circolare vendite 03/2025):
- sconto quantità a scaglioni sulla singola riga;
- i rivenditori hanno uno sconto aggiuntivo, ma lo sconto complessivo
  non può superare il tetto concordato;
- l'IVA si applica al totale imponibile dell'ordine.
"""

ALIQUOTA_IVA = 0.22
TETTO_SCONTO = 0.12
SCAGLIONI = [(50, 0.10), (10, 0.05)]  # (quantità minima, sconto) dal più alto
SCONTO_RIVENDITORE = 0.03


def sconto_quantita(quantita):
    for minimo, sconto in SCAGLIONI:
        if quantita >= minimo:
            return sconto
    return 0.0


def sconto_riga(quantita, tipo_cliente):
    sconto = sconto_quantita(quantita)
    if tipo_cliente == "rivenditore":
        sconto = min(sconto + SCONTO_RIVENDITORE, TETTO_SCONTO)
    return sconto


def importo_riga(prezzo_unitario, quantita, tipo_cliente="privato"):
    """Importo imponibile della riga, arrotondato al centesimo."""
    lordo = prezzo_unitario * quantita
    netto = lordo * (1 - sconto_riga(quantita, tipo_cliente))
    return round(netto, 2)


def totale_ordine(righe, tipo_cliente="privato"):
    """righe: lista di (prezzo_unitario, quantita). Restituisce (imponibile, iva, totale)."""
    imponibile = sum(importo_riga(p, q, tipo_cliente) for p, q in righe)
    iva = round(imponibile * ALIQUOTA_IVA, 2)
    return round(imponibile, 2), iva, round(imponibile + iva, 2)
