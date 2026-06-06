import { useState, useCallback } from "react";
import api from "../services/api";

function ResultCard({ result, onOpenVideo }) {
  return (
    <div className="bg-white border border-gray-200 rounded-lg p-4 hover:shadow-sm transition-shadow">
      <div className="flex items-start gap-3">
        {/* Thumbnail */}
        <div className="w-20 h-12 bg-gray-100 rounded overflow-hidden shrink-0">
          {result.has_thumbnail ? (
            <img
              src={`/api/videos/${result.video_id}/thumbnail`}
              className="w-full h-full object-cover"
              alt=""
              onError={(e) => { e.target.style.display = "none"; }}
            />
          ) : (
            <div className="w-full h-full flex items-center justify-center text-lg opacity-40">🎬</div>
          )}
        </div>

        {/* Info */}
        <div className="flex-1 min-w-0">
          <div className="flex items-center justify-between gap-2">
            <h4 className="text-sm font-medium text-gray-800 truncate">
              {result.title || result.filename}
            </h4>
            <button
              onClick={() => onOpenVideo(result.video_id)}
              className="text-xs bg-blue-600 hover:bg-blue-700 text-white px-2.5 py-1 rounded-lg transition-colors shrink-0"
            >
              Apri
            </button>
          </div>
          {result.summary && (
            <p className="text-xs text-gray-500 italic mt-0.5 truncate">{result.summary}</p>
          )}

          {/* Matches */}
          <div className="mt-2 space-y-1">
            {result.matches?.map((m, i) => (
              <div key={i} className="flex items-start gap-2 text-xs">
                <button
                  onClick={() => onOpenVideo(result.video_id)}
                  className="text-blue-600 font-mono hover:text-blue-800 shrink-0"
                >
                  ▶ {m.timestamp_str}
                </button>
                <span className="text-gray-600 line-clamp-1">{m.text}</span>
              </div>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
}

export default function CrossVideoSearch({ onSelectVideo, onClose }) {
  const [query, setQuery] = useState("");
  const [results, setResults] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  const handleSearch = useCallback(async () => {
    if (!query.trim()) return;
    setLoading(true);
    setError(null);
    setResults(null);
    try {
      const data = await api.crossSearch(query.trim());
      setResults(data);
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  }, [query]);

  const handleKeyDown = (e) => {
    if (e.key === "Enter") handleSearch();
  };

  return (
    <div className="bg-white border-b border-gray-200 shadow-sm">
      <div className="max-w-6xl mx-auto px-6 py-4">
        {/* Header */}
        <div className="flex items-center justify-between mb-3">
          <h3 className="text-sm font-semibold text-gray-700">Cerca in tutti i video</h3>
          <button
            onClick={onClose}
            className="text-gray-400 hover:text-gray-600 transition-colors text-lg"
          >
            ×
          </button>
        </div>

        {/* Search input */}
        <div className="flex gap-2 mb-4">
          <input
            type="text"
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            onKeyDown={handleKeyDown}
            placeholder="In quali video si parla di..."
            className="flex-1 border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            autoFocus
          />
          <button
            onClick={handleSearch}
            disabled={loading || !query.trim()}
            className="bg-blue-600 hover:bg-blue-700 disabled:bg-gray-300 text-white font-medium px-5 py-2.5 rounded-lg text-sm transition-colors shrink-0"
          >
            {loading ? "Cerco..." : "Cerca"}
          </button>
        </div>

        {/* Loading */}
        {loading && (
          <div className="flex items-center gap-3 py-6 justify-center">
            <div className="animate-spin rounded-full h-5 w-5 border-2 border-blue-600 border-t-transparent" />
            <span className="text-sm text-gray-500">Analizzando i video...</span>
          </div>
        )}

        {/* Error */}
        {error && (
          <div className="bg-red-50 border border-red-200 rounded-lg p-3 mb-3">
            <p className="text-red-600 text-sm">{error}</p>
          </div>
        )}

        {/* Results */}
        {results && !loading && (
          <div className="space-y-2 max-h-96 overflow-y-auto">
            {results.length === 0 && (
              <p className="text-gray-400 text-sm text-center py-6">
                Nessun video parla di questo argomento
              </p>
            )}
            {results.map((r) => (
              <ResultCard key={r.video_id} result={r} onOpenVideo={onSelectVideo} />
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
