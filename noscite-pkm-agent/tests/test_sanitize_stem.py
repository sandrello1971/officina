"""
Test unitari per _sanitize_stem (processor.py)

Casi edge verificati:
  - Rimozione di [], ^, |, # (sintassi Obsidian)
  - Normalizzazione lettere accentate in ASCII
  - Compressione spazi multipli e trim
  - Caratteri misti in combinazione
  - Stringa vuota e stringa già pulita
"""

import unittest
from processor import _sanitize_stem


class TestSanitizeStem(unittest.TestCase):

    # ── Caratteri Obsidian singoli ───────────────────────────────────────────

    def test_square_brackets_removed(self):
        self.assertEqual(_sanitize_stem("[documento]"), "documento")

    def test_open_bracket_removed(self):
        self.assertEqual(_sanitize_stem("[report"), "report")

    def test_close_bracket_removed(self):
        self.assertEqual(_sanitize_stem("report]"), "report")

    def test_caret_removed(self):
        self.assertEqual(_sanitize_stem("blocco^A1"), "bloccoA1")

    def test_pipe_removed(self):
        self.assertEqual(_sanitize_stem("titolo|alias"), "titoloalias")

    def test_hash_removed(self):
        self.assertEqual(_sanitize_stem("capitolo#3"), "capitolo3")

    # ── Lettere accentate ────────────────────────────────────────────────────

    def test_accented_e(self):
        self.assertEqual(_sanitize_stem("relazione_finanziaria_piu_recente"), "relazione_finanziaria_piu_recente")

    def test_accented_e_acute(self):
        self.assertEqual(_sanitize_stem("perché"), "perche")

    def test_accented_a_grave(self):
        self.assertEqual(_sanitize_stem("città"), "citta")

    def test_accented_u(self):
        self.assertEqual(_sanitize_stem("müller"), "muller")

    def test_accented_n_tilde(self):
        self.assertEqual(_sanitize_stem("españa"), "espana")

    def test_multiple_accents(self):
        self.assertEqual(_sanitize_stem("élève"), "eleve")

    # ── Spazi ────────────────────────────────────────────────────────────────

    def test_leading_spaces_stripped(self):
        self.assertEqual(_sanitize_stem("  documento"), "documento")

    def test_trailing_spaces_stripped(self):
        self.assertEqual(_sanitize_stem("documento  "), "documento")

    def test_multiple_spaces_collapsed(self):
        self.assertEqual(_sanitize_stem("documento  fiscale   2024"), "documento fiscale 2024")

    def test_tabs_collapsed(self):
        self.assertEqual(_sanitize_stem("documento\t\tfiscale"), "documento fiscale")

    # ── Caratteri misti ──────────────────────────────────────────────────────

    def test_brackets_and_pipe(self):
        self.assertEqual(_sanitize_stem("[titolo|alias]"), "titoloalias")

    def test_hash_and_caret(self):
        self.assertEqual(_sanitize_stem("report#3^blocco"), "report3blocco")

    def test_all_special_chars(self):
        self.assertEqual(_sanitize_stem("[#|^]"), "")

    def test_accents_and_special_chars(self):
        self.assertEqual(_sanitize_stem("[relazione_città#2]"), "relazione_citta2")

    def test_accents_special_and_spaces(self):
        # [, |, ] rimossi; spazi risultanti collassati a uno solo
        self.assertEqual(_sanitize_stem("  [résumé | alias]  "), "resume alias")

    def test_realistic_filename(self):
        # [2024-03] → 2024-03; " | " → " " (pipe rimosso, spazi collassati)
        self.assertEqual(
            _sanitize_stem("Verbale CdA [2024-03] | approvazione bilancio"),
            "Verbale CdA 2024-03 approvazione bilancio"
        )

    # ── Casi limite ──────────────────────────────────────────────────────────

    def test_empty_string(self):
        self.assertEqual(_sanitize_stem(""), "")

    def test_only_special_chars(self):
        self.assertEqual(_sanitize_stem("[^|#]"), "")

    def test_only_spaces(self):
        self.assertEqual(_sanitize_stem("   "), "")

    def test_already_clean(self):
        stem = "documento_fiscale_2024"
        self.assertEqual(_sanitize_stem(stem), stem)

    def test_no_change_on_alphanumeric(self):
        stem = "Report Q1 2024"
        self.assertEqual(_sanitize_stem(stem), stem)


if __name__ == "__main__":
    unittest.main(verbosity=2)
