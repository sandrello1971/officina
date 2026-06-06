import { useRef, useEffect, useState, useMemo } from "react";

function formatTime(seconds) {
  const s = Math.max(0, Math.floor(seconds));
  const h = Math.floor(s / 3600);
  const m = Math.floor((s % 3600) / 60);
  const sec = s % 60;
  if (h > 0) {
    return `${String(h).padStart(2, "0")}:${String(m).padStart(2, "0")}:${String(sec).padStart(2, "0")}`;
  }
  return `${String(m).padStart(2, "0")}:${String(sec).padStart(2, "0")}`;
}

export default function VideoPlayer({ videoFile, videoId, seekTo, onTimeUpdate }) {
  const videoRef = useRef(null);
  const lastUpdateRef = useRef(0);
  const [currentTime, setCurrentTime] = useState(0);
  const [videoReady, setVideoReady] = useState(false);

  // Determine video source: local file takes priority, then backend stream
  const blobUrl = useMemo(() => {
    if (!videoFile) return null;
    return URL.createObjectURL(videoFile);
  }, [videoFile]);

  const videoSrc = blobUrl || (videoId ? `/api/videos/${videoId}/stream` : null);

  // Cleanup blob URL
  useEffect(() => {
    return () => {
      if (blobUrl) URL.revokeObjectURL(blobUrl);
    };
  }, [blobUrl]);

  // Reset ready state when source changes
  useEffect(() => {
    setVideoReady(false);
  }, [videoSrc]);

  // Seek when seekTo changes — only if video source is loaded
  useEffect(() => {
    if (seekTo === null || seekTo === undefined) return;
    if (!videoRef.current || !videoReady) return;

    console.log("[PLAYER] Seeking to:", seekTo);
    videoRef.current.currentTime = seekTo;
    videoRef.current.play().catch(() => {});
  }, [seekTo, videoReady]);

  const handleTimeUpdate = () => {
    const video = videoRef.current;
    if (!video) return;

    const now = Date.now();
    setCurrentTime(video.currentTime);

    if (now - lastUpdateRef.current >= 1000) {
      lastUpdateRef.current = now;
      onTimeUpdate?.(video.currentTime);
    }
  };

  if (!videoSrc) {
    return (
      <div className="w-full aspect-video bg-gray-200 rounded-lg flex flex-col items-center justify-center gap-2 p-4">
        <span className="text-2xl">🎬</span>
        <span className="text-gray-400 text-sm">Nessun video caricato</span>
      </div>
    );
  }

  return (
    <div>
      <video
        ref={videoRef}
        src={videoSrc}
        controls
        className="w-full rounded-lg shadow-sm"
        onLoadedData={() => setVideoReady(true)}
        onTimeUpdate={handleTimeUpdate}
      />
      <div className="mt-2 text-sm text-gray-500 font-mono">
        ▶ {formatTime(currentTime)}
      </div>
    </div>
  );
}
