import { useState, useRef, useCallback } from "react";
import api from "../services/api";

function FileRow({ entry }) {
  const sizeMB = (entry.file.size / (1024 * 1024)).toFixed(1);
  const statusColors = {
    idle: "bg-gray-100 text-gray-500",
    uploading: "bg-blue-100 text-blue-600",
    processing: "bg-yellow-100 text-yellow-700",
    ready: "bg-green-100 text-green-700",
    error: "bg-red-100 text-red-600",
  };
  const statusLabels = {
    idle: "In attesa",
    uploading: "Caricamento...",
    processing: entry.stepLabel || "Elaborazione...",
    ready: entry.skipped ? "Già analizzato" : "Completato",
    error: "Errore",
  };

  return (
    <div className="bg-gray-50 rounded-lg px-4 py-3">
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-2 min-w-0 flex-1">
          <span className="text-lg shrink-0">
            {entry.status === "ready" ? "✅" : entry.status === "error" ? "❌" : "📄"}
          </span>
          <span className="text-sm text-gray-700 truncate">{entry.file.name}</span>
        </div>
        <div className="flex items-center gap-2 shrink-0 ml-2">
          <span className="text-xs text-gray-400">{sizeMB} MB</span>
          <span className={`text-[10px] font-medium px-2 py-0.5 rounded-full ${statusColors[entry.status]}`}>
            {statusLabels[entry.status]}
          </span>
        </div>
      </div>
      {(entry.status === "uploading" || entry.status === "processing") && (
        <div className="mt-2 w-full bg-gray-200 rounded-full h-1.5 overflow-hidden">
          <div
            className="h-full rounded-full bg-blue-500 transition-all duration-500 ease-out"
            style={{ width: `${entry.progress}%` }}
          />
        </div>
      )}
      {entry.status === "error" && entry.error && (
        <p className="text-xs text-red-500 mt-1">{entry.error}</p>
      )}
    </div>
  );
}

export default function UploadPanel({ onVideoReady, ingestHook }) {
  const [selectedFiles, setSelectedFiles] = useState([]);
  const [fileEntries, setFileEntries] = useState([]);
  const [isDragging, setIsDragging] = useState(false);
  const [isUploading, setIsUploading] = useState(false);
  const [completedCount, setCompletedCount] = useState(0);
  const [allDone, setAllDone] = useState(false);
  const fileInputRef = useRef(null);

  const handleFileSelect = (e) => {
    const files = Array.from(e.target.files).filter((f) =>
      f.type.startsWith("video/")
    );
    if (files.length > 0) setSelectedFiles(files);
    // Reset input so same files can be re-selected
    e.target.value = "";
  };

  const handleDrop = (e) => {
    e.preventDefault();
    setIsDragging(false);
    const files = Array.from(e.dataTransfer.files).filter((f) =>
      f.type.startsWith("video/")
    );
    if (files.length > 0) setSelectedFiles(files);
  };

  const handleDragOver = (e) => {
    e.preventDefault();
    setIsDragging(true);
  };

  const handleDragLeave = () => setIsDragging(false);

  const updateEntry = (idx, updates) => {
    setFileEntries((prev) =>
      prev.map((e, i) => (i === idx ? { ...e, ...updates } : e))
    );
  };

  const pollStatus = useCallback((videoId, idx) => {
    return new Promise((resolve) => {
      const interval = setInterval(async () => {
        try {
          const data = await api.getStatus(videoId);
          updateEntry(idx, {
            progress: data.progress ?? 0,
            stepLabel: data.step_label || "Elaborazione...",
            status:
              data.status === "ready"
                ? "ready"
                : data.status === "error"
                ? "error"
                : "processing",
          });
          if (data.status === "ready") {
            clearInterval(interval);
            resolve("ready");
          } else if (data.status === "error") {
            clearInterval(interval);
            updateEntry(idx, { error: data.error || "Errore sconosciuto" });
            resolve("error");
          }
        } catch {
          // Keep polling
        }
      }, 2000);
    });
  }, []);

  const uploadSingleFile = useCallback(
    async (file, idx) => {
      updateEntry(idx, { status: "uploading", progress: 10 });
      try {
        const data = await api.ingestVideo(file);
        if (data.skipped) {
          updateEntry(idx, {
            status: "ready",
            progress: 100,
            skipped: true,
            videoId: data.video_id,
          });
          return;
        }
        updateEntry(idx, {
          status: "processing",
          progress: 20,
          videoId: data.video_id,
        });
        await pollStatus(data.video_id, idx);
      } catch (err) {
        updateEntry(idx, {
          status: "error",
          error: err.message,
        });
      }
    },
    [pollStatus]
  );

  const handleStartUpload = async () => {
    if (selectedFiles.length === 0) return;

    // Initialize entries
    const entries = selectedFiles.map((file) => ({
      file,
      status: "idle",
      progress: 0,
      stepLabel: "",
      error: null,
      videoId: null,
      skipped: false,
    }));
    setFileEntries(entries);
    setIsUploading(true);
    setCompletedCount(0);
    setAllDone(false);

    // Sequential upload
    let done = 0;
    for (let i = 0; i < selectedFiles.length; i++) {
      await uploadSingleFile(selectedFiles[i], i);
      done++;
      setCompletedCount(done);
    }
    setAllDone(true);
    setIsUploading(false);
  };

  const handleReset = () => {
    setSelectedFiles([]);
    setFileEntries([]);
    setIsUploading(false);
    setCompletedCount(0);
    setAllDone(false);
  };

  const totalSize = selectedFiles
    .reduce((sum, f) => sum + f.size, 0) / (1024 * 1024);

  return (
    <div className="flex items-center justify-center p-4 pb-16">
      <div className="bg-white rounded-xl shadow-md p-8 w-full max-w-lg">
        <h1 className="text-3xl font-bold text-gray-900 text-center mb-1">
          Video Chatbot
        </h1>
        <p className="text-gray-500 text-center mb-8">
          Carica uno o più video per iniziare
        </p>

        {/* Drop zone — only when no upload in progress */}
        {!isUploading && !allDone && (
          <>
            <div
              className={`border-2 border-dashed rounded-xl p-10 text-center cursor-pointer transition-all duration-200 ${
                isDragging
                  ? "border-blue-500 bg-blue-50"
                  : "border-gray-300 hover:border-blue-400 hover:bg-gray-50"
              }`}
              onDrop={handleDrop}
              onDragOver={handleDragOver}
              onDragLeave={handleDragLeave}
              onClick={() => fileInputRef.current?.click()}
            >
              <input
                type="file"
                multiple
                accept="video/*"
                ref={fileInputRef}
                onChange={handleFileSelect}
                style={{ display: "none" }}
              />
              <div className="text-4xl mb-3">🎥</div>
              <p className="text-gray-600 font-medium">
                Trascina i video qui o clicca per selezionare
              </p>
              <p className="text-gray-400 text-sm mt-2">
                Supportati: MP4, MOV, AVI, WebM — Selezione multipla
              </p>
            </div>

            {/* Selected files list */}
            {selectedFiles.length > 0 && (
              <div className="mt-4 space-y-2">
                {selectedFiles.map((f, i) => (
                  <div
                    key={i}
                    className="flex items-center justify-between bg-gray-50 rounded-lg px-4 py-2"
                  >
                    <div className="flex items-center gap-2 min-w-0">
                      <span className="text-lg">📄</span>
                      <span className="text-sm text-gray-700 truncate">
                        {f.name}
                      </span>
                    </div>
                    <span className="text-xs text-gray-400 shrink-0 ml-2">
                      {(f.size / (1024 * 1024)).toFixed(1)} MB
                    </span>
                  </div>
                ))}
                <div className="flex items-center justify-between text-xs text-gray-500 px-1">
                  <span>
                    {selectedFiles.length} video selezionat{selectedFiles.length === 1 ? "o" : "i"}
                  </span>
                  <span>{totalSize.toFixed(1)} MB totali</span>
                </div>
              </div>
            )}

            {/* Upload button */}
            {selectedFiles.length > 0 && (
              <button
                onClick={handleStartUpload}
                className="w-full mt-4 bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 px-4 rounded-lg transition-all duration-200"
              >
                Analizza {selectedFiles.length} video
              </button>
            )}
          </>
        )}

        {/* Upload in progress */}
        {(isUploading || allDone) && fileEntries.length > 0 && (
          <div className="space-y-3">
            {/* Global progress */}
            <div className="flex items-center justify-between text-sm mb-2">
              <span className="text-gray-600 font-medium">
                {completedCount} / {fileEntries.length} video completati
              </span>
              {isUploading && (
                <div className="animate-spin rounded-full h-4 w-4 border-2 border-blue-600 border-t-transparent" />
              )}
            </div>

            {/* File list with status */}
            <div className="space-y-2 max-h-[350px] overflow-y-auto">
              {fileEntries.map((entry, i) => (
                <FileRow key={i} entry={entry} />
              ))}
            </div>

            {/* All done */}
            {allDone && (
              <div className="mt-4 space-y-3">
                <div className="bg-green-50 border border-green-200 rounded-lg p-4 text-center">
                  <span className="text-green-600 font-medium">
                    Elaborazione completata!
                  </span>
                </div>
                <div className="flex gap-2">
                  <button
                    onClick={handleReset}
                    className="flex-1 text-sm text-gray-500 hover:text-gray-700 py-2 border border-gray-300 rounded-lg transition-colors"
                  >
                    Carica altri video
                  </button>
                  <button
                    onClick={() => onVideoReady(null)}
                    className="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 rounded-lg transition-colors text-sm"
                  >
                    Vai alla libreria
                  </button>
                </div>
              </div>
            )}
          </div>
        )}
      </div>
    </div>
  );
}
