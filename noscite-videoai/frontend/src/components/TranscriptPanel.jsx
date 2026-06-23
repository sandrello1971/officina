import { useState, useEffect, useRef, useCallback } from "react";
import api from "../services/api";

export default function TranscriptPanel({ videoId, currentTime, onSeekTo }) {
  const [segments, setSegments] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [filter, setFilter] = useState("");
  const activeRef = useRef(null);

  useEffect(() => {
    if (!videoId) return;
    setLoading(true);
    api.getTranscript(videoId)
      .then((data) => {
        setSegments(data.segments || []);
        setError(null);
      })
      .catch((err) => setError(err.message))
      .finally(() => setLoading(false));
  }, [videoId]);

  // Auto-scroll to active segment
  useEffect(() => {
    if (activeRef.current) {
      activeRef.current.scrollIntoView({ behavior: "smooth", block: "center" });
    }
  }, [currentTime]);

  const handleCopyAll = useCallback(() => {
    const text = segments
      .map((s) => `[${s.timestamp_str}] ${s.text}`)
      .join("\n");
    navigator.clipboard.writeText(text);
  }, [segments]);

  const filtered = filter.trim()
    ? segments.filter((s) => s.text.toLowerCase().includes(filter.toLowerCase()))
    : segments;

  if (loading) {
    return (
      <div className="flex-1 flex items-center justify-center">
        <div className="animate-spin rounded-full h-6 w-6 border-2 border-blue-600 border-t-transparent" />
      </div>
    );
  }

  if (error) {
    return (
      <div className="flex-1 flex items-center justify-center p-4">
        <p className="text-red-500 text-sm">{error}</p>
      </div>
    );
  }

  return (
    <div className="flex flex-col h-full">
      {/* Search + copy */}
      <div className="px-3 py-2 border-b border-gray-200 flex gap-2 shrink-0">
        <input
          type="text"
          value={filter}
          onChange={(e) => setFilter(e.target.value)}
          placeholder="Cerca nella trascrizione..."
          className="flex-1 border border-gray-300 rounded-lg px-2.5 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-blue-500"
        />
        <button
          onClick={handleCopyAll}
          className="text-xs text-gray-500 hover:text-gray-700 px-2 py-1 border border-gray-300 rounded-lg transition-colors shrink-0"
          title="Copia tutta la trascrizione"
        >
          Copia
        </button>
      </div>

      {/* Segments */}
      <div className="flex-1 overflow-y-auto px-3 py-2 space-y-1">
        {filtered.length === 0 && (
          <p className="text-gray-400 text-sm text-center py-8">
            {filter ? "Nessun risultato" : "Trascrizione vuota"}
          </p>
        )}
        {filtered.map((seg, i) => {
          const isActive = currentTime >= seg.start && currentTime < seg.end;
          return (
            <div
              key={i}
              ref={isActive ? activeRef : null}
              className={`flex gap-2 px-2 py-1.5 rounded-lg transition-colors cursor-pointer hover:bg-gray-50 ${
                isActive ? "bg-yellow-50 border-l-2 border-yellow-400" : ""
              }`}
              onClick={() => onSeekTo(seg.start)}
            >
              <button className="text-xs font-mono text-blue-600 hover:text-blue-800 shrink-0 w-12 text-left">
                {seg.timestamp_str}
              </button>
              <span className="text-xs text-gray-700 leading-relaxed">{seg.text}</span>
            </div>
          );
        })}
      </div>
    </div>
  );
}
