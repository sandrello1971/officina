"""
graph_client.py — Microsoft Graph client per SharePoint backend.

REGOLA ARCHITETTURALE CRITICA:
Tutte le chiamate usano /sites/{SITE_ID}/drive/... (drive singolare, senza id).
La forma /drives/{DRIVE_ID}/... causa "invalidRequest: drive id malformed" su
Team site group-connected — NON usarla.
"""

import logging
import os
import time
from pathlib import Path
from typing import Optional

import httpx
import msal

log = logging.getLogger("graph_client")

GRAPH_BASE = "https://graph.microsoft.com/v1.0"
AUTHORITY_TMPL = "https://login.microsoftonline.com/{tenant}"
SCOPES = ["https://graph.microsoft.com/.default"]

_RETRY_STATUS = {429, 503, 504}
_RETRY_DELAYS = [2, 4, 8]  # secondi

_CHUNK_DOWNLOAD = 1024 * 1024          # 1 MB
_CHUNK_UPLOAD   = 10 * 1024 * 1024     # 10 MB (resumable)
_SIMPLE_UPLOAD_LIMIT = 4 * 1024 * 1024 # 4 MB threshold per resumable


class GraphClient:
    def __init__(self):
        self.tenant_id = os.environ["MICROSOFT_TENANT_ID"]
        self.client_id = os.environ["MICROSOFT_CLIENT_ID"]
        self.client_secret = os.environ["GRAPH_CLIENT_SECRET"]
        self.site_id = os.environ["GRAPH_SITE_ID"]

        self._msal_app = msal.ConfidentialClientApplication(
            client_id=self.client_id,
            client_credential=self.client_secret,
            authority=AUTHORITY_TMPL.format(tenant=self.tenant_id),
        )
        self._site_base = f"/sites/{self.site_id}/drive"

    # ── Auth ────────────────────────────────────────────────────────────

    def _token(self) -> str:
        result = self._msal_app.acquire_token_for_client(scopes=SCOPES)
        if "access_token" not in result:
            raise RuntimeError(f"MSAL token error: {result.get('error_description', result)}")
        return result["access_token"]

    def _headers(self, extra: Optional[dict] = None) -> dict:
        headers = {"Authorization": f"Bearer {self._token()}"}
        if extra:
            headers.update(extra)
        return headers

    # ── HTTP helpers con retry ──────────────────────────────────────────

    def _request(self, method: str, path: str, *,
                 json_body=None, content: Optional[bytes] = None,
                 extra_headers: Optional[dict] = None,
                 stream: bool = False) -> httpx.Response:
        url = GRAPH_BASE + path if path.startswith("/") else path
        last_exc = None
        for attempt in range(len(_RETRY_DELAYS) + 1):
            try:
                with httpx.Client(timeout=120.0) as client:
                    resp = client.request(
                        method, url,
                        headers=self._headers(extra_headers),
                        json=json_body,
                        content=content,
                    )
                if resp.status_code in _RETRY_STATUS and attempt < len(_RETRY_DELAYS):
                    wait = _RETRY_DELAYS[attempt]
                    log.warning(f"Graph {resp.status_code} su {method} {path} — retry in {wait}s")
                    time.sleep(wait)
                    continue
                if resp.status_code >= 400:
                    msg = self._format_error(resp)
                    raise RuntimeError(f"Graph {method} {path} → {resp.status_code}: {msg}")
                return resp
            except httpx.TimeoutException as e:
                last_exc = e
                if attempt < len(_RETRY_DELAYS):
                    wait = _RETRY_DELAYS[attempt]
                    log.warning(f"Graph timeout su {method} {path} — retry in {wait}s")
                    time.sleep(wait)
                    continue
                raise
        raise last_exc if last_exc else RuntimeError("retry exhausted")

    @staticmethod
    def _format_error(resp: httpx.Response) -> str:
        try:
            body = resp.json()
            err = body.get("error", {})
            return f"{err.get('code', '?')}: {err.get('message', resp.text)}"
        except Exception:
            return resp.text[:300]

    def _get(self, path: str, **kwargs) -> httpx.Response:
        return self._request("GET", path, **kwargs)

    def _put(self, path: str, content: Optional[bytes] = None,
             extra_headers: Optional[dict] = None) -> httpx.Response:
        return self._request("PUT", path, content=content, extra_headers=extra_headers)

    def _patch(self, path: str, body: dict) -> httpx.Response:
        return self._request("PATCH", path, json_body=body,
                             extra_headers={"Content-Type": "application/json"})

    def _post(self, path: str, body: Optional[dict] = None) -> httpx.Response:
        return self._request("POST", path, json_body=body,
                             extra_headers={"Content-Type": "application/json"} if body else None)

    def _delete(self, path: str) -> httpx.Response:
        return self._request("DELETE", path)

    # ── Path helpers ────────────────────────────────────────────────────

    def _folder_url(self, folder_path: str, suffix: str) -> str:
        """
        folder_path: es. '_inbox' o '_archive/sub', oppure '' per root.
        suffix: es. ':/children', ':/content', ''.
        Ritorna path formato: /sites/{site}/drive/root:/<folder_path>:/{suffix}
        o /sites/{site}/drive/root se folder_path è vuoto e suffix è ''.
        """
        if folder_path == "" or folder_path == "/":
            if suffix:
                return f"{self._site_base}/root{suffix}"
            return f"{self._site_base}/root"
        return f"{self._site_base}/root:/{folder_path.strip('/')}{suffix}"

    def _item_url(self, item_id: str, suffix: str = "") -> str:
        return f"{self._site_base}/items/{item_id}{suffix}"

    # ── API pubbliche ───────────────────────────────────────────────────

    def list_folder(self, folder_path: str) -> list[dict]:
        """
        Elenca il contenuto di una cartella. folder_path relativo al root del drive.
        Gestisce paginazione via @odata.nextLink.
        """
        url = self._folder_url(folder_path, ":/children")
        items: list[dict] = []
        next_url: Optional[str] = url

        while next_url:
            if next_url.startswith("http"):
                # Chiamata assoluta (nextLink)
                resp = self._request("GET", next_url)
            else:
                resp = self._get(next_url)
            body = resp.json()
            items.extend(body.get("value", []))
            next_url = body.get("@odata.nextLink")

        return items

    def get_item_by_path(self, full_path: str) -> Optional[dict]:
        """
        Ritorna driveItem per il path dato (es. '_archive/file.pdf') o None se 404.
        """
        url = self._folder_url(full_path, "")
        try:
            resp = self._get(url)
            return resp.json()
        except RuntimeError as e:
            if " 404:" in str(e) or "itemNotFound" in str(e):
                return None
            raise

    def get_item_metadata(self, item_id: str) -> dict:
        return self._get(self._item_url(item_id)).json()

    def download_item(self, item_id: str, dest_path: Path) -> None:
        """Scarica un driveItem su disco, streamato in chunk."""
        url = GRAPH_BASE + self._item_url(item_id, "/content")
        with httpx.Client(timeout=300.0, follow_redirects=True) as client:
            with client.stream("GET", url, headers=self._headers()) as resp:
                if resp.status_code >= 400:
                    raise RuntimeError(f"Graph download {item_id} → {resp.status_code}")
                dest_path.parent.mkdir(parents=True, exist_ok=True)
                with open(dest_path, "wb") as f:
                    for chunk in resp.iter_bytes(_CHUNK_DOWNLOAD):
                        f.write(chunk)

    def download_item_content(self, item_id: str) -> bytes:
        """Scarica un driveItem in memoria (per file piccoli tipo .md)."""
        url = GRAPH_BASE + self._item_url(item_id, "/content")
        with httpx.Client(timeout=120.0, follow_redirects=True) as client:
            resp = client.get(url, headers=self._headers())
            if resp.status_code >= 400:
                raise RuntimeError(f"Graph download {item_id} → {resp.status_code}")
            return resp.content

    def get_item_content_at_path(self, full_path: str) -> Optional[bytes]:
        """Scarica contenuto di un file per path (None se 404)."""
        item = self.get_item_by_path(full_path)
        if not item:
            return None
        return self.download_item_content(item["id"])

    def upload_text(self, folder_path: str, filename: str, content: str,
                    content_type: str = "text/markdown; charset=utf-8") -> dict:
        """Upload testuale diretto (< 4 MB). Per file più grandi usa upload_binary."""
        data = content.encode("utf-8")
        return self._upload_simple(folder_path, filename, data, content_type)

    def upload_binary(self, folder_path: str, filename: str, data: bytes,
                      content_type: str = "application/octet-stream") -> dict:
        """Upload binario. Usa resumable se >= 4 MB."""
        if len(data) < _SIMPLE_UPLOAD_LIMIT:
            return self._upload_simple(folder_path, filename, data, content_type)
        return self._upload_resumable(folder_path, filename, data)

    def _upload_simple(self, folder_path: str, filename: str, data: bytes,
                       content_type: str) -> dict:
        target = f"{folder_path.strip('/')}/{filename}" if folder_path else filename
        url = self._folder_url(target, ":/content")
        resp = self._put(url, content=data, extra_headers={"Content-Type": content_type})
        return resp.json()

    def _upload_resumable(self, folder_path: str, filename: str, data: bytes) -> dict:
        target = f"{folder_path.strip('/')}/{filename}" if folder_path else filename
        session_url = self._folder_url(target, ":/createUploadSession")
        body = {
            "item": {
                "@microsoft.graph.conflictBehavior": "replace",
                "name": filename,
            }
        }
        session = self._post(session_url, body).json()
        upload_url = session["uploadUrl"]

        total = len(data)
        offset = 0
        last_json: Optional[dict] = None

        with httpx.Client(timeout=300.0) as client:
            while offset < total:
                end = min(offset + _CHUNK_UPLOAD, total)
                chunk = data[offset:end]
                headers = {
                    "Content-Length": str(len(chunk)),
                    "Content-Range": f"bytes {offset}-{end-1}/{total}",
                }
                resp = client.put(upload_url, content=chunk, headers=headers)
                if resp.status_code >= 400:
                    raise RuntimeError(
                        f"Graph resumable upload {offset}-{end-1}/{total} → {resp.status_code}: {resp.text[:200]}"
                    )
                if resp.status_code in (200, 201):
                    last_json = resp.json()
                offset = end

        return last_json or {}

    def move_item(self, item_id: str, new_folder_path: str,
                  new_name: Optional[str] = None) -> dict:
        """Sposta driveItem in altra cartella. new_folder_path relativo al root del drive."""
        body = {
            "parentReference": {
                "path": f"/drive/root:/{new_folder_path.strip('/')}" if new_folder_path else "/drive/root:"
            }
        }
        if new_name:
            body["name"] = new_name
        resp = self._patch(self._item_url(item_id), body)
        return resp.json()

    def delete_item(self, item_id: str) -> None:
        self._delete(self._item_url(item_id))


# Singleton per import facile
graph = GraphClient()
