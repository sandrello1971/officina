import unittest

from magazzino.prezzi import importo_riga, sconto_riga, totale_ordine


class TestPrezzi(unittest.TestCase):
    def test_nessuno_sconto_sotto_scaglione(self):
        self.assertEqual(importo_riga(10.0, 3), 30.0)

    def test_scaglione_dieci_pezzi(self):
        self.assertEqual(importo_riga(10.0, 10), 95.0)

    def test_rivenditore_rispetta_il_tetto(self):
        self.assertAlmostEqual(sconto_riga(60, "rivenditore"), 0.12)

    def test_totale_con_iva(self):
        self.assertEqual(totale_ordine([(10.0, 2), (5.0, 4)]), (40.0, 8.8, 48.8))
