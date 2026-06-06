# Tech debt — noscite-videoai

## venv di produzione non isolato

Il servizio in produzione (`noscite-videoai.service`, uvicorn su
`127.0.0.1:8001`, `WorkingDirectory=/var/www/noscite-videoai`) **non usa un
virtualenv dedicato al progetto**: gira con il venv condiviso
`/home/noscite/venv` (vedi `ExecStart` nell'unit systemd). Il `venv/` presente
dentro `/var/www/noscite-videoai` è vuoto/abbandonato.

**Conseguenze:**

- Le dipendenze del servizio non sono isolate: aggiornare il venv condiviso per
  un altro progetto può rompere videoai (e viceversa).
- `requirements.txt` non riflette necessariamente le versioni realmente
  installate nel venv condiviso (verificare con `pip freeze`).

**Da fare (intervento pianificato, non in questa sessione):**

1. Creare un venv dedicato in produzione (es. `/var/www/noscite-videoai/.venv`).
2. `pip install -r requirements.txt` con versioni riconciliate.
3. Aggiornare `ExecStart` dell'unit systemd per puntare al venv dedicato.
4. `systemctl daemon-reload && systemctl restart noscite-videoai` + smoke test.

Finché non viene fatto, ogni modifica alle dipendenze in produzione va valutata
considerando l'impatto sugli altri consumatori di `/home/noscite/venv`.
