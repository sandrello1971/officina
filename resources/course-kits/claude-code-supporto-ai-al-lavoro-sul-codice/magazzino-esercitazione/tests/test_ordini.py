import unittest

from magazzino.ordini import nuovo_ordine


class TestOrdini(unittest.TestCase):
    def test_aggiunta_righe(self):
        o = nuovo_ordine("Ferramenta Rossi", righe=[])
        o.aggiungi("A-100", 3.90, 2)
        self.assertEqual(len(o.righe), 1)
        self.assertEqual(o.totali(), (7.8, 1.72, 9.52))

    def test_numerazione_progressiva(self):
        a = nuovo_ordine("Cliente A", righe=[])
        b = nuovo_ordine("Cliente B", righe=[])
        self.assertEqual(b.numero, a.numero + 1)
