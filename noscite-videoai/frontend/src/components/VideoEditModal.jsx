import { useState, useEffect, useCallback } from "react";
import api from "../services/api";

export default function VideoEditModal({ video, onClose, onSaved }) {
  const [title, setTitle] = useState(video.title || "");
  const [collection, setCollection] = useState(video.collection || "Generale");
  const [tags, setTags] = useState(
    Array.isArray(video.tags) ? video.tags : []
  );
  const [notes, setNotes] = useState(video.notes || "");
  const [newTag, setNewTag] = useState("");
  const [newCollection, setNewCollection] = useState("");
  const [collections, setCollections] = useState([]);
  const [saving, setSaving] = useState(false);
  const [toast, setToast] = useState(false);
  const [regenLoading, setRegenLoading] = useState(false);

  // Transcript accordion
  const [showTranscript, setShowTranscript] = useState(false);
  const [transcript, setTranscript] = useState(null);
  const [transcriptLoading, setTranscriptLoading] = useState(false);

  useEffect(() => {
    api.getFilters().then((f) => setCollections(f.collections || [])).catch(() => {});
  }, []);

  const handleAddTag = () => {
    const t = newTag.trim().toLowerCase();
    if (t && !tags.includes(t)) {
      setTags([...tags, t]);
    }
    setNewTag("");
  };

  const handleRemoveTag = (tag) => {
    setTags(tags.filter((t) => t !== tag));
  };

  const handleRegenerateTags = async () => {
    setRegenLoading(true);
    try {
      const data = await api.regenerateTags(video.video_id);
      if (data.tags && data.tags.length > 0) {
        setTags(data.tags);
      }
    } catch (err) {
      alert("Errore rigenerazione tag: " + err.message);
    } finally {
      setRegenLoading(false);
    }
  };

  const handleToggleTranscript = async () => {
    if (!showTranscript && !transcript) {
      setTranscriptLoading(true);
      try {
        const data = await api.getTranscript(video.video_id);
        setTranscript(data.segments || []);
      } catch {
        setTranscript([]);
      } finally {
        setTranscriptLoading(false);
      }
    }
    setShowTranscript(!showTranscript);
  };

  const handleCopyTranscript = () => {
    if (!transcript) return;
    const text = transcript.map((s) => s.text).join("\n");
    navigator.clipboard.writeText(text);
  };

  const handleSave = useCallback(async () => {
    setSaving(true);
    try {
      const col = newCollection.trim() || collection;
      await api.updateVideoMetadata(video.video_id, {
        title: title.trim() || video.filename,
        tags,
        collection: col,
        notes,
      });
      setToast(true);
      setTimeout(() => {
        setToast(false);
        onSaved?.();
        onClose();
      }, 1500);
    } catch (err) {
      alert("Errore: " + err.message);
    } finally {
      setSaving(false);
    }
  }, [title, tags, collection, newCollection, notes, video, onSaved, onClose]);

  const handleCopyId = () => {
    navigator.clipboard.writeText(video.video_id);
  };

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4" onClick={onClose}>
      <div className="bg-white rounded-xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto" onClick={(e) => e.stopPropagation()}>
        {/* Header */}
        <div className="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
          <h2 className="font-semibold text-gray-800">Modifica metadati</h2>
          <button onClick={onClose} className="text-gray-400 hover:text-gray-600 text-xl">×</button>
        </div>

        <div className="px-6 py-4 space-y-4">
          {/* Read-only info */}
          <div className="bg-gray-50 rounded-lg p-3 space-y-1.5 text-xs text-gray-600">
            <div className="flex justify-between">
              <span>ID:</span>
              <button onClick={handleCopyId} className="font-mono text-gray-800 hover:text-blue-600" title="Clicca per copiare">
                {video.video_id.slice(0, 16)}...
              </button>
            </div>
            <div className="flex justify-between">
              <span>Durata:</span>
              <span className="text-gray-800">{video.duration_str}</span>
            </div>
            <div className="flex justify-between">
              <span>Lingua:</span>
              <span className="text-gray-800">{video.language || "—"}</span>
            </div>
            <div className="flex justify-between">
              <span>Chunk:</span>
              <span className="text-gray-800">{video.chunks_count}</span>
            </div>
            {video.summary && (
              <div className="pt-1.5 border-t border-gray-200">
                <span className="text-gray-500">Summary AI:</span>
                <p className="text-gray-700 italic mt-0.5">{video.summary}</p>
              </div>
            )}
          </div>

          {/* Title */}
          <div>
            <label className="text-xs font-medium text-gray-700 block mb-1">Titolo</label>
            <input
              type="text"
              value={title}
              onChange={(e) => setTitle(e.target.value)}
              className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>

          {/* Collection */}
          <div>
            <label className="text-xs font-medium text-gray-700 block mb-1">Collezione</label>
            <div className="flex gap-2">
              <select
                value={collection}
                onChange={(e) => { setCollection(e.target.value); setNewCollection(""); }}
                className="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                {collections.map((c) => (
                  <option key={c} value={c}>{c}</option>
                ))}
                {!collections.includes(collection) && collection && (
                  <option value={collection}>{collection}</option>
                )}
              </select>
              <input
                type="text"
                value={newCollection}
                onChange={(e) => setNewCollection(e.target.value)}
                placeholder="Nuova..."
                className="w-28 border border-gray-300 rounded-lg px-2 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500"
              />
            </div>
          </div>

          {/* Tags */}
          <div>
            <div className="flex items-center justify-between mb-1">
              <label className="text-xs font-medium text-gray-700">Tag</label>
              <button
                onClick={handleRegenerateTags}
                disabled={regenLoading}
                className="text-[10px] text-purple-600 hover:text-purple-800 disabled:text-gray-400 transition-colors"
              >
                {regenLoading ? "Generando..." : "↻ Rigenera tag AI"}
              </button>
            </div>
            <div className="flex flex-wrap gap-1.5 mb-2">
              {tags.map((tag) => (
                <span key={tag} className="inline-flex items-center gap-1 text-xs bg-purple-50 text-purple-700 border border-purple-200 rounded-full px-2.5 py-1">
                  {tag}
                  <button onClick={() => handleRemoveTag(tag)} className="text-purple-400 hover:text-red-500">×</button>
                </span>
              ))}
              {tags.length === 0 && (
                <span className="text-xs text-gray-400 italic">
                  Nessun tag — generati automaticamente all'ingest
                </span>
              )}
            </div>
            <div className="flex gap-2">
              <input
                type="text"
                value={newTag}
                onChange={(e) => setNewTag(e.target.value)}
                onKeyDown={(e) => e.key === "Enter" && (e.preventDefault(), handleAddTag())}
                placeholder="Aggiungi tag..."
                className="flex-1 border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500"
              />
              <button
                onClick={handleAddTag}
                disabled={!newTag.trim()}
                className="text-xs bg-gray-100 hover:bg-gray-200 disabled:opacity-50 text-gray-700 px-3 py-1.5 rounded-lg transition-colors"
              >
                +
              </button>
            </div>
          </div>

          {/* Notes */}
          <div>
            <label className="text-xs font-medium text-gray-700 block mb-1">Note</label>
            <textarea
              value={notes}
              onChange={(e) => setNotes(e.target.value)}
              rows={3}
              placeholder="Aggiungi note personali..."
              className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"
            />
          </div>

          {/* Transcript accordion */}
          <div className="border border-gray-200 rounded-lg overflow-hidden">
            <button
              onClick={handleToggleTranscript}
              className="w-full flex items-center justify-between px-4 py-2.5 bg-gray-50 hover:bg-gray-100 transition-colors text-sm text-gray-700 font-medium"
            >
              <span>Trascrizione completa</span>
              <span className="text-gray-400">{showTranscript ? "▲" : "▼"}</span>
            </button>

            {showTranscript && (
              <div className="border-t border-gray-200">
                {transcriptLoading && (
                  <div className="flex items-center justify-center py-8">
                    <div className="animate-spin rounded-full h-5 w-5 border-2 border-blue-600 border-t-transparent" />
                  </div>
                )}

                {!transcriptLoading && transcript && (
                  <div>
                    {/* Copy button */}
                    <div className="flex justify-end px-3 pt-2">
                      <button
                        onClick={handleCopyTranscript}
                        className="text-xs text-gray-500 hover:text-gray-700 border border-gray-300 rounded px-2 py-1 transition-colors"
                      >
                        Copia tutto
                      </button>
                    </div>

                    {/* Segments */}
                    <div className="max-h-[300px] overflow-y-auto px-4 py-2 space-y-1.5">
                      {transcript.length === 0 && (
                        <p className="text-gray-400 text-xs text-center py-4">Trascrizione non disponibile</p>
                      )}
                      {transcript.map((seg, i) => (
                        <div key={i} className="flex gap-2 text-xs">
                          <span className="font-mono text-gray-400 shrink-0 w-10">
                            {seg.timestamp_str}
                          </span>
                          <span className="text-gray-700">{seg.text}</span>
                        </div>
                      ))}
                    </div>
                  </div>
                )}
              </div>
            )}
          </div>
        </div>

        {/* Footer */}
        <div className="px-6 py-4 border-t border-gray-200 flex items-center justify-between">
          {toast && <span className="text-green-600 text-sm font-medium">Salvato!</span>}
          {!toast && <div />}
          <div className="flex gap-2">
            <button onClick={onClose} className="text-sm text-gray-500 hover:text-gray-700 px-4 py-2 transition-colors">
              Annulla
            </button>
            <button
              onClick={handleSave}
              disabled={saving}
              className="bg-blue-600 hover:bg-blue-700 disabled:bg-gray-300 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors"
            >
              {saving ? "Salvo..." : "Salva"}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
