# 🗂️ Obsidian AI Metadata Agent

Sistema automatico per catalogare qualsiasi tipo di documento nel tuo vault Obsidian usando Claude AI.

---

## Architettura del vault

```
ObsidianVault/
│
├── _inbox/          ← METTI QUI i file da catalogare
├── _archive/        ← File originali dopo la catalogazione
├── _metadata/       ← File .md con metadati generati dall'AI
│   ├── _INDEX.md    ← Tabella di ricerca globale
│   ├── documento1.md
│   ├── documento2.md
│   └── ...
└── _agent.log       ← Log dell'agent
```

---

## Installazione

### 1. Clona / copia i file dell'agent

Metti tutti i file `.py` in una cartella, ad esempio `~/obsidian-agent/`.

### 2. Crea un ambiente virtuale Python

```bash
cd ~/obsidian-agent
python3 -m venv venv
source venv/bin/activate        # macOS/Linux
# oppure: venv\Scripts\activate  # Windows
```

### 3. Installa le dipendenze

```bash
pip install -r requirements.txt
```

### 4. Installa ffmpeg (per video e audio)

```bash
# macOS
brew install ffmpeg

# Ubuntu/Debian
sudo apt install ffmpeg

# Windows — scarica da https://ffmpeg.org/download.html
```

### 5. Configura l'API key di Anthropic

```bash
export ANTHROPIC_API_KEY="sk-ant-..."   # macOS/Linux

# Windows (PowerShell)
$env:ANTHROPIC_API_KEY = "sk-ant-..."
```

Per renderla permanente, aggiungila al tuo `~/.zshrc` o `~/.bashrc`.

### 6. Configura il percorso del vault

Imposta la variabile d'ambiente con il percorso del tuo vault Obsidian:

```bash
export OBSIDIAN_VAULT="/Users/tuonome/Documents/ObsidianVault"
```

In alternativa, modifica direttamente `config.py`:

```python
VAULT_ROOT = Path("/Users/tuonome/Documents/ObsidianVault")
```

---

## Avvio

```bash
cd ~/obsidian-agent
source venv/bin/activate
python watcher.py
```

L'agent:
1. **Processa tutti i file** già presenti in `_inbox/` all'avvio
2. **Rimane in ascolto** per i nuovi file aggiunti
3. Per ogni file: estrae il testo → chiama Claude API → genera `.md` → archivia l'originale → aggiorna l'indice

Premi `Ctrl+C` per fermarlo.

---

## Avvio automatico (opzionale)

### macOS — launchd

Crea `~/Library/LaunchAgents/com.obsidian.agent.plist`:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "...">
<plist version="1.0">
<dict>
    <key>Label</key>
    <string>com.obsidian.agent</string>
    <key>ProgramArguments</key>
    <array>
        <string>/Users/tuonome/obsidian-agent/venv/bin/python</string>
        <string>/Users/tuonome/obsidian-agent/watcher.py</string>
    </array>
    <key>EnvironmentVariables</key>
    <dict>
        <key>ANTHROPIC_API_KEY</key>
        <string>sk-ant-...</string>
        <key>OBSIDIAN_VAULT</key>
        <string>/Users/tuonome/Documents/ObsidianVault</string>
    </dict>
    <key>RunAtLoad</key>
    <true/>
    <key>StandardOutPath</key>
    <string>/tmp/obsidian-agent.log</string>
    <key>StandardErrorPath</key>
    <string>/tmp/obsidian-agent-err.log</string>
</dict>
</plist>
```

```bash
launchctl load ~/Library/LaunchAgents/com.obsidian.agent.plist
```

### Linux — systemd

```ini
[Unit]
Description=Obsidian AI Metadata Agent

[Service]
ExecStart=/home/utente/obsidian-agent/venv/bin/python /home/utente/obsidian-agent/watcher.py
Environment=ANTHROPIC_API_KEY=sk-ant-...
Environment=OBSIDIAN_VAULT=/home/utente/Documents/ObsidianVault
Restart=always

[Install]
WantedBy=default.target
```

---

## Come funziona il grafo Obsidian

Ogni file `.md` di metadati contiene **wikilink** che Obsidian usa per costruire il grafo delle relazioni:

- `[[topic_machine_learning]]` → collega tutti i doc sullo stesso argomento
- `[[tag_fattura]]` → collega tutti i doc con lo stesso tag
- `[[_archive/documento.pdf|📎]]` → link diretto al file originale

Aprendo il **Graph View** in Obsidian vedrai automaticamente:
- Cluster di documenti per argomento
- Nodi hub per i tag più comuni
- Connessioni tra documenti correlati

---

## Tipi di file supportati

| Tipo | Estrazione testo | Metadati tecnici |
|------|-----------------|-----------------|
| PDF | ✅ (pdfplumber) | ✅ (pagine) |
| DOCX | ✅ (python-docx) | ✅ |
| PPTX | ✅ (python-pptx) | ✅ (slide) |
| XLSX/XLS | ✅ (openpyxl) | ✅ (fogli) |
| TXT/CSV/MD | ✅ | ✅ |
| MP4/AVI/MOV/... | ❌ | ✅ (durata, formato) |
| MP3/WAV/FLAC/... | ❌ | ✅ (durata, bitrate) |
| JPG/PNG/... | ❌ | ✅ (dimensioni) |
| ZIP/RAR/... | ❌ | ✅ (dimensione) |

---

## File generati per ogni documento

Esempio di `relazione_q3.md` in `_metadata/`:

```markdown
---
title: "Relazione Q3 2024 - Performance Commerciale"
file_originale: "[[_archive/relazione_q3.pdf]]"
data_catalogazione: 2024-11-15
tipo_documento: report
lingua: it
tags:
  - report_vendite
  - q3_2024
  - performance
---

# Relazione Q3 2024 - Performance Commerciale

> Documento che analizza le performance commerciali del terzo trimestre...

## 🔗 Argomenti Correlati
[[topic_vendite|Vendite]]  [[topic_performance|Performance]]

## 🏷️ Tag
[[tag_report_vendite]]  [[tag_q3_2024]]
```

---

## Costi API indicativi

| Tipo documento | Token stimati | Costo (claude-sonnet) |
|----------------|---------------|----------------------|
| PDF 10 pagine | ~3.000 | ~$0.01 |
| Presentazione 20 slide | ~2.000 | ~$0.007 |
| File media (video/audio) | ~300 | ~$0.001 |
| **100 documenti misti** | ~200.000 | **~$0.60** |

---

## Problemi comuni

**"ModuleNotFoundError"** → Assicurati di aver attivato il venv e installato i requirements.

**"AuthenticationError"** → Controlla che `ANTHROPIC_API_KEY` sia impostata correttamente.

**File non processato** → Controlla `_agent.log` nel vault per dettagli sull'errore.

**ffprobe non trovato** → Installa ffmpeg (vedi sopra). I file video/audio vengono comunque catalogati senza metadati tecnici.
