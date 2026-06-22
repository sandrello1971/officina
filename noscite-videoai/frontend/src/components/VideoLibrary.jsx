import { useState, useEffect, useRef, useCallback } from "react";
import api from "../services/api";
import VideoEditModal from "./VideoEditModal";

function formatDate(dateStr) {
  if (!dateStr) return "";
  const d = new Date(typeof dateStr === "number" ? dateStr * 1000 : dateStr);
  if (isNaN(d.getTime())) return "";
  const months = [
    "gen", "feb", "mar", "apr", "mag", "giu",
    "lug", "ago", "set", "ott", "nov", "dic",
  ];
  return `${d.getDate()} ${months[d.getMonth()]} ${d.getFullYear()}`;
}

function SkeletonCard() {
  return (
    <div className="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
      <div className="aspect-video bg-gray-200 animate-pulse" />
      <div className="p-4 space-y-3">
        <div className="h-4 bg-gray-200 rounded animate-pulse w-3/4" />
        <div className="h-3 bg-gray-200 rounded animate-pulse w-1/2" />
        <div className="flex justify-between items-center pt-2">
          <div className="h-5 bg-gray-200 rounded-full animate-pulse w-16" />
          <div className="h-8 bg-gray-200 rounded-lg animate-pulse w-14" />
        </div>
      </div>
    </div>
  );
}

function VideoCard({ video, onSelect, onDelete, onEdit }) {
  const isReady = video.status === "ready";

  const handleDelete = (e) => {
    e.stopPropagation();
    if (confirm(`Eliminare "${video.filename}"? Questa azione è irreversibile.`)) {
      onDelete(video.video_id);
    }
  };

  const tags = Array.isArray(video.tags) ? video.tags : [];

  return (
    <div className="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 group">
      {/* Thumbnail */}
      <div className="aspect-video bg-gray-100 flex items-center justify-center overflow-hidden relative">
        {/* Edit button overlay */}
        <button
          onClick={(e) => { e.stopPropagation(); onEdit(video); }}
          className="absolute top-2 right-2 z-10 bg-black/40 hover:bg-black/60 text-white rounded-lg p-1.5 opacity-0 group-hover:opacity-100 transition-opacity"
          title="Modifica metadati"
        >
          <svg xmlns="http://www.w3.org/2000/svg" className="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
            <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
          </svg>
        </button>
        {video.has_thumbnail ? (
          <img
            src={`/api/videos/${video.video_id}/thumbnail`}
            className="w-full aspect-video object-cover rounded-t-xl"
            onError={(e) => { e.target.style.display = "none"; }}
            alt=""
          />
        ) : (
          <span className="text-4xl opacity-40 group-hover:opacity-60 transition-opacity">
            🎬
          </span>
        )}
      </div>

      {/* Body */}
      <div className="p-4">
        <h3 className="font-medium text-gray-800 truncate text-sm" title={video.title || video.filename}>
          {video.title || video.filename}
        </h3>
        {video.summary && (
          <p className="text-xs text-gray-500 italic mt-0.5 truncate" title={video.summary}>
            {video.summary}
          </p>
        )}
        <div className="flex items-center gap-3 mt-1 text-xs text-gray-500">
          <span>{video.duration_str}</span>
          <span>{formatDate(video.created_at)}</span>
          <span>{video.chunks_count} chunk</span>
        </div>

        {/* Tags */}
        {tags.length > 0 && (
          <div className="flex flex-wrap gap-1 mt-2">
            {tags.map((tag) => (
              <span key={tag} className="text-[10px] bg-blue-50 text-blue-600 rounded-full px-1.5 py-0.5">
                {tag}
              </span>
            ))}
          </div>
        )}

        {/* Footer */}
        <div className="flex items-center justify-between mt-3 pt-3 border-t border-gray-100">
          {/* Status badge */}
          <div className="flex items-center gap-2">
            {isReady ? (
              <span className="inline-flex items-center gap-1 text-xs font-medium text-green-700 bg-green-50 border border-green-200 rounded-full px-2 py-0.5">
                <span className="w-1.5 h-1.5 rounded-full bg-green-500" />
                Pronto
              </span>
            ) : (
              <span className="inline-flex items-center gap-1 text-xs font-medium text-yellow-700 bg-yellow-50 border border-yellow-200 rounded-full px-2 py-0.5">
                <span className="w-1.5 h-1.5 rounded-full bg-yellow-500 animate-pulse" />
                In elaborazione
              </span>
            )}
            {video.collection && video.collection !== "Generale" && (
              <span className="text-[10px] bg-gray-100 text-gray-600 rounded-full px-1.5 py-0.5">
                {video.collection}
              </span>
            )}
          </div>

          {/* Actions */}
          <div className="flex items-center gap-1">
            <button
              onClick={handleDelete}
              className="p-1.5 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors"
              title="Elimina video"
            >
              <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                <path fillRule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clipRule="evenodd" />
              </svg>
            </button>
            <button
              onClick={() => onSelect(video.video_id)}
              disabled={!isReady}
              className="bg-blue-600 hover:bg-blue-700 disabled:bg-gray-300 text-white text-xs font-medium px-3 py-1.5 rounded-lg transition-colors"
            >
              Apri
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}

function SearchBar({ searchQuery, onSearchChange, filters, activeFilters, onFilterChange }) {
  return (
    <div className="mb-6 space-y-3">
      {/* Search input */}
      <div className="relative">
        <svg className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
          <path fillRule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clipRule="evenodd" />
        </svg>
        <input
          type="text"
          value={searchQuery}
          onChange={(e) => onSearchChange(e.target.value)}
          placeholder="Cerca video per titolo, contenuto, tag..."
          className="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
        />
      </div>

      {/* Filter row */}
      {(filters.collections?.length > 0 || filters.languages?.length > 0 || filters.tags?.length > 0) && (
        <div className="flex flex-wrap items-center gap-2">
          <span className="text-xs text-gray-500 font-medium">Filtri:</span>

          {filters.collections?.length > 0 && (
            <select
              value={activeFilters.collection}
              onChange={(e) => onFilterChange("collection", e.target.value)}
              className="text-xs border border-gray-300 rounded-lg px-2 py-1.5 bg-white focus:outline-none focus:ring-1 focus:ring-blue-500"
            >
              <option value="">Tutte le collezioni</option>
              {filters.collections.map((c) => (
                <option key={c} value={c}>{c}</option>
              ))}
            </select>
          )}

          {filters.languages?.length > 0 && (
            <select
              value={activeFilters.language}
              onChange={(e) => onFilterChange("language", e.target.value)}
              className="text-xs border border-gray-300 rounded-lg px-2 py-1.5 bg-white focus:outline-none focus:ring-1 focus:ring-blue-500"
            >
              <option value="">Tutte le lingue</option>
              {filters.languages.map((l) => (
                <option key={l} value={l}>{l}</option>
              ))}
            </select>
          )}

          {filters.tags?.length > 0 && (
            <select
              value={activeFilters.tag}
              onChange={(e) => onFilterChange("tag", e.target.value)}
              className="text-xs border border-gray-300 rounded-lg px-2 py-1.5 bg-white focus:outline-none focus:ring-1 focus:ring-blue-500"
            >
              <option value="">Tutti i tag</option>
              {filters.tags.map((t) => (
                <option key={t} value={t}>{t}</option>
              ))}
            </select>
          )}

          {(activeFilters.collection || activeFilters.language || activeFilters.tag) && (
            <button
              onClick={() => {
                onFilterChange("collection", "");
                onFilterChange("language", "");
                onFilterChange("tag", "");
              }}
              className="text-xs text-red-500 hover:text-red-700 transition-colors"
            >
              Rimuovi filtri
            </button>
          )}

          {filters.stats && (
            <span className="ml-auto text-xs text-gray-400">
              {filters.stats.total_videos} video
            </span>
          )}
        </div>
      )}
    </div>
  );
}

export default function VideoLibrary({ onSelectVideo }) {
  const [videos, setVideos] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [searchQuery, setSearchQuery] = useState("");
  const [filters, setFilters] = useState({});
  const [activeFilters, setActiveFilters] = useState({ collection: "", language: "", tag: "" });
  const [editingVideo, setEditingVideo] = useState(null);
  const [organizing, setOrganizing] = useState(false);
  const [toast, setToast] = useState("");
  const pollRef = useRef(null);
  const searchTimerRef = useRef(null);

  const fetchVideos = useCallback(async (params = {}) => {
    try {
      const data = await api.searchVideos(params);
      setVideos(data);
      setError(null);
      return data;
    } catch (err) {
      setError(err.message);
      return [];
    } finally {
      setLoading(false);
    }
  }, []);

  const fetchFilters = useCallback(async () => {
    try {
      const data = await api.getFilters();
      setFilters(data);
    } catch {
      // Filters are optional
    }
  }, []);

  // Build search params from current state
  const getSearchParams = useCallback(() => {
    const params = {};
    if (searchQuery.trim()) params.q = searchQuery.trim();
    if (activeFilters.collection) params.collection = activeFilters.collection;
    if (activeFilters.language) params.language = activeFilters.language;
    if (activeFilters.tag) params.tag = activeFilters.tag;
    return params;
  }, [searchQuery, activeFilters]);

  // Initial fetch
  useEffect(() => {
    fetchVideos();
    fetchFilters();
  }, [fetchVideos, fetchFilters]);

  // Debounced search on query/filter change
  useEffect(() => {
    if (searchTimerRef.current) clearTimeout(searchTimerRef.current);
    searchTimerRef.current = setTimeout(() => {
      fetchVideos(getSearchParams());
    }, 300);
    return () => {
      if (searchTimerRef.current) clearTimeout(searchTimerRef.current);
    };
  }, [searchQuery, activeFilters, fetchVideos, getSearchParams]);

  // Poll every 5s if any video is still processing
  useEffect(() => {
    const hasProcessing = videos.some((v) => v.status !== "ready");
    if (hasProcessing) {
      pollRef.current = setInterval(() => fetchVideos(getSearchParams()), 5000);
    } else if (pollRef.current) {
      clearInterval(pollRef.current);
      pollRef.current = null;
    }
    return () => {
      if (pollRef.current) clearInterval(pollRef.current);
    };
  }, [videos, fetchVideos, getSearchParams]);

  const handleFilterChange = (key, value) => {
    setActiveFilters((prev) => ({ ...prev, [key]: value }));
  };

  const handleAutoOrganize = async () => {
    setOrganizing(true);
    try {
      const data = await api.autoOrganize();
      setToast(`${data.updated} video organizzati!`);
      setTimeout(() => setToast(""), 3000);
      fetchVideos(getSearchParams());
      fetchFilters();
    } catch (err) {
      setError(err.message);
    } finally {
      setOrganizing(false);
    }
  };

  const handleEditSaved = () => {
    fetchVideos(getSearchParams());
    fetchFilters();
  };

  const handleDelete = async (videoId) => {
    try {
      await api.deleteVideo(videoId);
      setVideos((prev) => prev.filter((v) => v.video_id !== videoId));
      fetchFilters(); // Refresh filter options
    } catch (err) {
      setError(err.message);
    }
  };

  // Loading
  if (loading) {
    return (
      <div>
        <SearchBar
          searchQuery={searchQuery}
          onSearchChange={setSearchQuery}
          filters={filters}
          activeFilters={activeFilters}
          onFilterChange={handleFilterChange}
        />
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <SkeletonCard />
          <SkeletonCard />
          <SkeletonCard />
        </div>
      </div>
    );
  }

  // Error
  if (error) {
    return (
      <div className="bg-red-50 border border-red-200 rounded-lg p-4">
        <p className="text-red-600 text-sm">{error}</p>
      </div>
    );
  }

  return (
    <div>
      <SearchBar
        searchQuery={searchQuery}
        onSearchChange={setSearchQuery}
        filters={filters}
        activeFilters={activeFilters}
        onFilterChange={handleFilterChange}
      />

      {/* Empty */}
      {videos.length === 0 && (
        <div className="flex flex-col items-center justify-center py-16 text-gray-400">
          <span className="text-5xl mb-4">📭</span>
          {searchQuery || activeFilters.collection || activeFilters.language || activeFilters.tag ? (
            <>
              <p className="text-lg font-medium">Nessun risultato</p>
              <p className="text-sm mt-1">Prova a modificare la ricerca o i filtri</p>
            </>
          ) : (
            <>
              <p className="text-lg font-medium">Nessun video ancora analizzato</p>
              <p className="text-sm mt-1">Carica il tuo primo video per iniziare</p>
            </>
          )}
        </div>
      )}

      {/* Auto-organize + toast */}
      {videos.length > 0 && videos.some((v) => !v.collection || v.collection === "Generale") && (
        <div className="mb-4 flex items-center gap-3">
          <button
            onClick={handleAutoOrganize}
            disabled={organizing}
            className="text-xs bg-purple-50 hover:bg-purple-100 disabled:opacity-50 text-purple-700 border border-purple-200 px-3 py-1.5 rounded-lg transition-colors flex items-center gap-1.5"
          >
            {organizing ? (
              <span className="animate-spin inline-block w-3 h-3 border-2 border-purple-400 border-t-transparent rounded-full" />
            ) : (
              "✨"
            )}
            Organizza automaticamente
          </button>
          {toast && (
            <span className="text-xs text-green-600 font-medium animate-pulse">{toast}</span>
          )}
        </div>
      )}

      {/* Grid */}
      {videos.length > 0 && (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          {videos.map((video) => (
            <VideoCard
              key={video.video_id}
              video={video}
              onSelect={onSelectVideo}
              onDelete={handleDelete}
              onEdit={setEditingVideo}
            />
          ))}
        </div>
      )}

      {/* Edit Modal */}
      {editingVideo && (
        <VideoEditModal
          video={editingVideo}
          onClose={() => setEditingVideo(null)}
          onSaved={handleEditSaved}
        />
      )}
    </div>
  );
}
