import { useState, useRef, useEffect } from "react";
import useVideoChat from "../hooks/useVideoChat";
import ChatMessage from "./ChatMessage";
import TranscriptPanel from "./TranscriptPanel";

export default function ChatPanel({ videoId, canChat, isVideoReady, onSeekTo, currentTime }) {
  const [inputText, setInputText] = useState("");
  const [activeTab, setActiveTab] = useState("chat");
  const { messages, isLoading, error, sendMessage, clearMessages } =
    useVideoChat();
  const messagesEndRef = useRef(null);

  // Auto-scroll to bottom on new messages
  useEffect(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: "smooth" });
  }, [messages]);

  const handleSend = () => {
    const text = inputText.trim();
    if (!text || !canChat || isLoading) return;
    setInputText("");
    sendMessage(videoId, text);
  };

  const handleKeyDown = (e) => {
    if (e.key === "Enter" && !e.shiftKey) {
      e.preventDefault();
      handleSend();
    }
  };

  return (
    <div className="flex flex-col h-full bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
      {/* Tabs */}
      <div className="flex border-b border-gray-200 shrink-0">
        <button
          onClick={() => setActiveTab("chat")}
          className={`flex-1 px-4 py-2.5 text-sm font-medium transition-colors ${
            activeTab === "chat"
              ? "text-blue-600 border-b-2 border-blue-600"
              : "text-gray-500 hover:text-gray-700"
          }`}
        >
          Chat
        </button>
        <button
          onClick={() => setActiveTab("transcript")}
          className={`flex-1 px-4 py-2.5 text-sm font-medium transition-colors ${
            activeTab === "transcript"
              ? "text-blue-600 border-b-2 border-blue-600"
              : "text-gray-500 hover:text-gray-700"
          }`}
        >
          Trascrizione
        </button>
      </div>

      {/* Status badges */}
      {activeTab === "chat" && (!canChat || (canChat && !isVideoReady)) && (
        <div className="px-4 py-2 border-b border-gray-200 shrink-0">
          {!canChat && (
            <span className="text-xs bg-yellow-100 text-yellow-700 px-2 py-1 rounded-full">
              In attesa della trascrizione...
            </span>
          )}
          {canChat && !isVideoReady && (
            <span className="text-xs bg-blue-100 text-blue-700 px-2 py-1 rounded-full">
              Solo audio disponibile
            </span>
          )}
        </div>
      )}

      {/* Transcript tab */}
      {activeTab === "transcript" && (
        <TranscriptPanel
          videoId={videoId}
          currentTime={currentTime || 0}
          onSeekTo={onSeekTo}
        />
      )}

      {/* Chat tab */}
      {activeTab === "chat" && (
        <>
          {/* Messages area */}
          <div className="flex-1 overflow-y-auto px-4 py-3 relative">
            {!canChat && (
              <div className="absolute inset-0 bg-white/70 backdrop-blur-sm flex items-center justify-center z-10">
                <p className="text-gray-500 text-center px-4">
                  Attendi il completamento della trascrizione...
                </p>
              </div>
            )}

            {messages.length === 0 && (
              <div className="h-full flex items-center justify-center">
                <p className="text-gray-400 text-sm">
                  Fai una domanda sul video...
                </p>
              </div>
            )}

            {messages.map((msg) => (
              <ChatMessage
                key={msg.id}
                message={msg}
                onTimestampClick={onSeekTo}
              />
            ))}
            <div ref={messagesEndRef} />
          </div>

          {/* Error */}
          {error && (
            <div className="px-4 py-2 bg-red-50 border-t border-red-200">
              <p className="text-red-600 text-xs">{error}</p>
            </div>
          )}

          {/* Input footer */}
          <div className="px-4 py-3 border-t border-gray-200 shrink-0">
            <div className="flex gap-2">
              <input
                type="text"
                value={inputText}
                onChange={(e) => setInputText(e.target.value)}
                onKeyDown={handleKeyDown}
                placeholder={
                  canChat ? "Scrivi una domanda..." : "In attesa..."
                }
                disabled={!canChat}
                className="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:bg-gray-100 disabled:text-gray-400"
              />
              <button
                onClick={handleSend}
                disabled={!canChat || isLoading || !inputText.trim()}
                className="bg-blue-600 hover:bg-blue-700 disabled:bg-gray-300 text-white font-medium px-4 py-2 rounded-lg text-sm transition-all duration-200 shrink-0"
              >
                Invia
              </button>
            </div>
          </div>
        </>
      )}
    </div>
  );
}
