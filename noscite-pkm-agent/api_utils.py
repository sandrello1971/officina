"""
api_utils.py — Utility per le chiamate all'API Anthropic con retry robusto.
Gestisce sia 429 (rate limit) che 529 (overloaded).
"""

import time
import logging
import anthropic

log = logging.getLogger(__name__)

# Dopo quanti 529 consecutivi si rinuncia e si ri-tenta più tardi
MAX_OVERLOAD_RETRIES = 3
# Attesa base per overload (raddoppia ad ogni tentativo)
OVERLOAD_BASE_WAIT = 15
# Attesa per rate limit
RATE_LIMIT_WAIT = 35


class OverloadedError(Exception):
    """Sollevato quando i server sono in overload prolungato."""
    pass


def call_with_retry(fn, *args, **kwargs):
    """
    Chiama fn(*args, **kwargs) con retry automatico.
    - 429 rate limit: attende RATE_LIMIT_WAIT secondi e riprova
    - 529 overloaded: backoff esponenziale, max MAX_OVERLOAD_RETRIES tentativi
    Solleva OverloadedError se i server sono irraggiungibili dopo tutti i tentativi.
    """
    overload_count = 0

    for attempt in range(10):
        try:
            return fn(*args, **kwargs)

        except anthropic.RateLimitError:
            log.warning(f"  429 rate limit, attendo {RATE_LIMIT_WAIT}s...")
            time.sleep(RATE_LIMIT_WAIT)

        except anthropic.APIStatusError as e:
            if e.status_code == 529:
                overload_count += 1
                if overload_count >= MAX_OVERLOAD_RETRIES:
                    raise OverloadedError(
                        f"Server Anthropic in overload dopo {overload_count} tentativi. Riprova tra qualche minuto."
                    )
                wait = OVERLOAD_BASE_WAIT * (2 ** (overload_count - 1))
                log.warning(f"  529 overloaded ({overload_count}/{MAX_OVERLOAD_RETRIES}), attendo {wait}s...")
                time.sleep(wait)
            else:
                raise

        except Exception as e:
            msg = str(e).lower()
            if "529" in str(e) or "overloaded" in msg:
                overload_count += 1
                if overload_count >= MAX_OVERLOAD_RETRIES:
                    raise OverloadedError(
                        f"Server Anthropic in overload dopo {overload_count} tentativi. Riprova tra qualche minuto."
                    )
                wait = OVERLOAD_BASE_WAIT * (2 ** (overload_count - 1))
                log.warning(f"  529 overloaded ({overload_count}/{MAX_OVERLOAD_RETRIES}), attendo {wait}s...")
                time.sleep(wait)
            elif "429" in str(e) or "rate_limit" in msg:
                log.warning(f"  429 rate limit, attendo {RATE_LIMIT_WAIT}s...")
                time.sleep(RATE_LIMIT_WAIT)
            else:
                raise

    raise OverloadedError("API Anthropic: troppi tentativi falliti")
