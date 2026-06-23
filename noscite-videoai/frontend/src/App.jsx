import { useState, useCallback, useEffect } from "react";
import useIngest from "./hooks/useIngest";
import UploadPanel from "./components/UploadPanel";
import VideoPlayer from "./components/VideoPlayer";
import ChatPanel from "./components/ChatPanel";
import VideoLibrary from "./components/VideoLibrary";
import CrossVideoSearch from "./components/CrossVideoSearch";
import GraphView from "./components/GraphView";
import GlobalChatbot from "./components/GlobalChatbot";

export default function App() {
  const [videoId, setVideoId] = useState(null);
  const [videoFile, setVideoFile] = useState(null);
  const [seekTo, setSeekTo] = useState(null);
  const [currentTime, setCurrentTime] = useState(0);
  const [appState, setAppState] = useState("home");
  const [libraryChatReady, setLibraryChatReady] = useState(false);
  const [showCrossSearch, setShowCrossSearch] = useState(false);
  const [showGraph, setShowGraph] = useState(false);
  const [showGlobalChat, setShowGlobalChat] = useState(false);

  const ingestHook = useIngest();

  // On mount: restore from sessionStorage if needed
  useEffect(() => {
    const lastVideoId = sessionStorage.getItem("lastVideoId");
    const lastAppState = sessionStorage.getItem("lastAppState");
    if (lastAppState === "player" && lastVideoId) {
      setVideoId(lastVideoId);
      setLibraryChatReady(true);
      setAppState("player");
    }
  }, []);

  // Persist appState
  useEffect(() => {
    sessionStorage.setItem("lastAppState", appState);
  }, [appState]);

  const handleVideoReady = useCallback((vid) => {
    if (vid) {
      setVideoId(vid);
      sessionStorage.setItem("lastVideoId", vid);
    }
    setAppState("home");
    ingestHook.reset();
  }, [ingestHook]);

  const handleSelectVideo = useCallback((vid) => {
    setVideoId(vid);
    setVideoFile(null);
    setLibraryChatReady(true);
    setAppState("player");
    sessionStorage.setItem("lastVideoId", vid);
  }, []);

  const originalUpload = ingestHook.uploadVideo;
  const wrappedUpload = useCallback(
    (file) => {
      setVideoFile(file);
      sessionStorage.setItem("lastVideoName", file.name);
      return originalUpload(file);
    },
    [originalUpload]
  );

  useEffect(() => {
    if (videoId) sessionStorage.setItem("lastVideoId", videoId);
  }, [videoId]);

  const handleSeekTo = useCallback((seconds) => {
    setSeekTo({ time: seconds });
  }, []);

  const handleTimeUpdate = useCallback((time) => {
    setCurrentTime(time);
  }, []);

  const handleGoHome = useCallback(() => {
    setAppState("home");
    setVideoId(null);
    setVideoFile(null);
    setSeekTo(null);
    setCurrentTime(0);
    setLibraryChatReady(false);
    sessionStorage.removeItem("lastVideoName");
    sessionStorage.removeItem("lastVideoId");
    ingestHook.reset();
  }, [ingestHook]);

  const handleOpenVideoFromChat = useCallback((vid, timestampSeconds) => {
    setShowGlobalChat(false);
    handleSelectVideo(vid);
    if (timestampSeconds) {
      setTimeout(() => setSeekTo({ time: timestampSeconds }), 500);
    }
  }, [handleSelectVideo]);

  // Shared global chatbot FAB + panel
  const globalChatElements = (
    <>
      {!showGlobalChat && (
        <button
          onClick={() => setShowGlobalChat(true)}
          className="fixed bottom-6 right-6 z-40 w-14 h-14 bg-blue-600 hover:bg-blue-700 text-white rounded-full shadow-lg hover:shadow-xl transition-all flex items-center justify-center text-2xl"
          title="Cerca in tutti i video"
        >
          💬
        </button>
      )}
      {showGlobalChat && (
        <GlobalChatbot
          onClose={() => setShowGlobalChat(false)}
          onOpenVideo={handleOpenVideoFromChat}
          currentVideoId={videoId}
        />
      )}
    </>
  );

  // ─── HOME ────────────────────────────────────────
  if (appState === "home") {
    return (
      <div className="min-h-screen bg-gray-50">
        <header className="bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between">
          <h1 className="text-xl font-bold text-gray-800">Video Chatbot</h1>
          <div className="flex items-center gap-2">
            <button
              onClick={() => setShowCrossSearch(!showCrossSearch)}
              className={`text-sm font-medium px-4 py-2 rounded-lg transition-colors ${
                showCrossSearch
                  ? "bg-gray-200 text-gray-700"
                  : "bg-gray-100 hover:bg-gray-200 text-gray-600"
              }`}
            >
              Cerca in tutti i video
            </button>
            <button
              onClick={() => setShowGraph(true)}
              className="bg-gray-100 hover:bg-gray-200 text-gray-600 text-sm font-medium px-4 py-2 rounded-lg transition-colors"
            >
              Mappa
            </button>
            <button
              onClick={() => setAppState("upload")}
              className="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors"
            >
              + Nuovo video
            </button>
          </div>
        </header>
        {showCrossSearch && (
          <CrossVideoSearch
            onSelectVideo={(vid) => { setShowCrossSearch(false); handleSelectVideo(vid); }}
            onClose={() => setShowCrossSearch(false)}
          />
        )}
        <main className="max-w-6xl mx-auto p-6">
          <h2 className="text-lg font-semibold text-gray-700 mb-4">I tuoi video</h2>
          <VideoLibrary onSelectVideo={handleSelectVideo} />
        </main>
        {showGraph && (
          <GraphView
            onSelectVideo={(vid) => { setShowGraph(false); handleSelectVideo(vid); }}
            onClose={() => setShowGraph(false)}
          />
        )}
        {globalChatElements}
      </div>
    );
  }

  // ─── UPLOAD ──────────────────────────────────────
  if (appState === "upload") {
    return (
      <div className="min-h-screen bg-gray-50">
        <div className="max-w-lg mx-auto pt-4 px-4">
          <button
            onClick={() => setAppState("home")}
            className="text-sm text-gray-500 hover:text-gray-700 transition-colors mb-2"
          >
            ← Torna alla libreria
          </button>
        </div>
        <UploadPanel
          onVideoReady={handleVideoReady}
        />
        {globalChatElements}
      </div>
    );
  }

  // ─── PLAYER ──────────────────────────────────────
  const seekToValue = seekTo ? seekTo.time : null;

  return (
    <div className="min-h-screen bg-gray-50 flex flex-col">
      <header className="bg-white border-b border-gray-200 px-4 py-3 flex items-center justify-between shrink-0">
        <h1 className="text-lg font-bold text-gray-800">Video Chatbot</h1>
        <button
          onClick={handleGoHome}
          className="text-sm text-gray-500 hover:text-gray-700 transition-colors"
        >
          ← Libreria
        </button>
      </header>

      <div className="flex-1 flex flex-col md:flex-row overflow-hidden">
        <div className="w-full md:w-[60%] p-4 flex flex-col gap-3 overflow-y-auto">
          <VideoPlayer
            videoFile={videoFile}
            videoId={videoId}
            seekTo={seekToValue}
            onTimeUpdate={handleTimeUpdate}
          />

          {videoFile && (
            <div className="text-sm text-gray-500">
              <span className="font-medium text-gray-700">{videoFile.name}</span>
              <span className="ml-2">
                ({(videoFile.size / (1024 * 1024)).toFixed(1)} MB)
              </span>
            </div>
          )}

          {ingestHook.status === "processing" && (
            <div className="bg-white rounded-lg border border-gray-200 p-4">
              <div className="flex justify-between items-center mb-1">
                <span className="text-sm text-gray-600">{ingestHook.stepLabel}</span>
                <span className="text-sm font-medium text-gray-700">{ingestHook.progress}%</span>
              </div>
              <div className="w-full bg-gray-200 rounded-full h-2 overflow-hidden">
                <div
                  className={`h-full rounded-full transition-all duration-500 ease-out ${
                    ingestHook.canChat ? "bg-green-500" : "bg-blue-600"
                  }`}
                  style={{ width: `${ingestHook.progress}%` }}
                />
              </div>
            </div>
          )}
        </div>

        <div className="w-full md:w-[40%] p-4 pt-0 md:pt-4 flex flex-col min-h-[400px] md:min-h-0 md:h-[calc(100vh-57px)]">
          <ChatPanel
            videoId={videoId}
            canChat={libraryChatReady || ingestHook.canChat}
            isVideoReady={libraryChatReady || ingestHook.status === "ready"}
            onSeekTo={handleSeekTo}
            currentTime={currentTime}
          />
        </div>
      </div>
      {globalChatElements}
    </div>
  );
}
