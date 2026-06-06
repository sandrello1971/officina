const BASE_URL = "";

async function request(method, path, body = null, timeout = 60000) {
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), timeout);

  const options = { method, signal: controller.signal };

  if (body instanceof FormData) {
    options.body = body;
  } else if (body) {
    options.headers = { "Content-Type": "application/json" };
    options.body = JSON.stringify(body);
  }

  try {
    const res = await fetch(BASE_URL + path, options);
    clearTimeout(timer);

    if (!res.ok) {
      const err = await res.json().catch(() => ({}));
      throw new Error(err.detail || `Errore HTTP ${res.status}`);
    }

    return res.json();
  } catch (err) {
    clearTimeout(timer);
    if (err.name === "AbortError") {
      throw new Error("Richiesta scaduta (timeout)");
    }
    throw err;
  }
}

const api = {
  ingestVideo: (file) => {
    const form = new FormData();
    form.append("file", file);
    return request("POST", "/api/videos/ingest", form, 300000);
  },
  getStatus: (videoId) => request("GET", `/api/videos/${videoId}/status`),
  chat: (videoId, question, history) =>
    request("POST", `/api/videos/${videoId}/chat`, { question, history }),
  listVideos: () => request("GET", "/api/videos/"),
  searchVideos: (params = {}) => {
    const qs = new URLSearchParams(
      Object.entries(params).filter(([, v]) => v)
    ).toString();
    return request("GET", `/api/videos/${qs ? "?" + qs : ""}`);
  },
  getFilters: () => request("GET", "/api/videos/filters"),
  updateVideoMetadata: (videoId, data) =>
    request("PATCH", `/api/videos/${videoId}/metadata`, data),
  deleteVideo: (videoId) => request("DELETE", `/api/videos/${videoId}`),
  getVideoUrl: (videoId) => `/api/videos/${videoId}/stream`,
  getTranscript: (videoId) => request("GET", `/api/videos/${videoId}/transcript`),
  regenerateTags: (videoId) => request("POST", `/api/videos/${videoId}/regenerate-tags`, {}, 60000),
  crossSearch: (question, videoIds = null) =>
    request("POST", "/api/search", { question, video_ids: videoIds }, 120000),
  autoOrganize: () => request("POST", "/api/videos/auto-organize", {}, 120000),
  globalChat: (question, history) =>
    request("POST", "/api/chat/global", { question, history }, 120000),
  getGraph: (params = {}) => {
    const qs = new URLSearchParams(
      Object.entries(params).filter(([, v]) => v !== undefined && v !== "")
    ).toString();
    return request("GET", `/api/graph${qs ? "?" + qs : ""}`);
  },
};

export default api;
