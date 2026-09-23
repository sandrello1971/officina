import os
import tempfile
import unittest

from magazzino.importa import importa_giacenze


class TestImporta(unittest.TestCase):
    def test_import_base(self):
        with tempfile.NamedTemporaryFile("w", suffix=".csv", delete=False, encoding="utf-8") as f:
            f.write("codice;descrizione;quantita;prezzo\nX-1;Articolo prova;10;2.50\n")
        try:
            g = importa_giacenze(f.name)
        finally:
            os.unlink(f.name)
        self.assertEqual(g["X-1"], {"descrizione": "Articolo prova", "quantita": 10, "prezzo": 2.5})
