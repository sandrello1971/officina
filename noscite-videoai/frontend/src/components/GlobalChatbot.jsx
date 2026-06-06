import { useState, useRef, useEffect, useCallback } from "react";
import api from "../services/api";

let msgId = 0;

const SUGGESTIONS = [
  "In quali video si parla di intelligenza artificiale?",
  "Dove viene spiegato come funziona la blockchain?",
  "Quali video trattano di design thinking?",
  "Riassumi i temi principali dei video",
];

function parseTimestamp(str) {
  const parts = str.split(":").map(Number);
  if (parts.length === 3) return parts[0] * 3600 + parts[1] * 60 + parts[2];
  return parts[0] * 60 + parts[1];
}

function VideoRefCard({ ref: vref, onOpenVideo }) {
  return (
    <div className="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 flex items-center justify-between gap-2">
      <div className="min-w-0 flex-1">
        <p className="text-xs font-medium text-gray-700 truncate">{vref.title}</p>
        <button
          onClick={() => onOpenVideo(vref.video_id, vref.timestamp_seconds)}
          className="text-xs text-blue-600 hover:text-blue-800 font-mono mt-0.5"
        >
          ▶ {vref.timestamp_str}
        </button>
      </div>
      <button
        onClick={() => onOpenVideo(vref.video_id, vref.timestamp_seconds)}
        className="text-[10px] bg-blue-600 hover:bg-blue-700 text-white px-2 py-1 rounded transition-colors shrink-0"
      >
        Apri
      </button>
    </div>
  );
}

function Message({ msg, onOpenVideo }) {
  const isUser = msg.role === "user";

  if (isUser) {
    return (
      <div className="flex justify-end mb-3">
        <div className="bg-blue-600 text-white rounded-2xl rounded-br-md px-4 py-2.5 max-w-[85%] shadow-sm">
          <p className="text-sm whitespace-pre-wrap">{msg.text}</p>
        </div>
      </div>
    );
  }

  return (
    <div className="flex justify-start mb-3">
      <div className="bg-gray-100 text-gray-800 rounded-2xl rounded-bl-md px-4 py-2.5 max-w-[90%] shadow-sm">
        {msg.isLoading ? (
          <div className="flex items-center gap-2 py-1">
            <div className="flex gap-1">
              <span className="w-2 h-2 bg-gray-400 rounded-full animate-bounce [animation-delay:-0.3s]" />
              <span className="w-2 h-2 bg-gray-400 rounded-full animate-bounce [animation-delay:-0.15s]" />
              <span className="w-2 h-2 bg-gray-400 rounded-full animate-bounce" />
            </div>
            {msg.loadingText && (
              <span className="text-xs text-gray-500">{msg.loadingText}</span>
            )}
          </div>
        ) : (
          <>
            <div className="text-sm whitespace-pre-wrap leading-relaxed">
              {msg.text}
            </div>

            {/* Video references */}
            {msg.videoRefs?.length > 0 && (
              <div className="mt-3 pt-2 border-t border-gray-200 space-y-1.5">
                {msg.videoRefs.map((vr, i) => (
                  <VideoRefCard key={i} ref={vr} onOpenVideo={onOpenVideo} />
                ))}
              </div>
            )}

            {/* Sources count */}
            {msg.sourcesCount > 0 && (
              <div className="mt-2">
                <span className="text-[10px] bg-gray-200 text-gray-600 rounded-full px-2 py-0.5">
                  Consultati: {msg.sourcesCount} video
                </span>
              </div>
            )}
          </>
        )}
      </div>
    </div>
  );
}

export default function GlobalChatbot({ onClose, onOpenVideo, currentVideoId }) {
  const [messages, setMessages] = useState([]);
  const [inputText, setInputText] = useState("");
  const [isLoading, setIsLoading] = useState(false);
  const [videoCount, setVideoCount] = useState(0);
  const messagesEndRef = useRef(null);

  useEffect(() => {
    api.listVideos().then((vids) => setVideoCount(vids.length)).catch(() => {});
  }, []);

  useEffect(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: "smooth" });
  }, [messages]);

  const sendMessage = useCallback(async (question) => {
    if (!question.trim() || isLoading) return;

    const userMsg = { id: ++msgId, role: "user", text: question };
    const loadingMsg = {
      id: ++msgId,
      role: "assistant",
      text: "",
      isLoading: true,
      loadingText: `Sto consultando ${videoCount} video...`,
    };

    setMessages((prev) => [...prev, userMsg, loadingMsg]);
    setIsLoading(true);

    try {
      const history = messages
        .filter((m) => !m.isLoading)
        .slice(-6)
        .map((m) => ({ role: m.role, content: m.text }));

      const data = await api.globalChat(question, history);

      setMessages((prev) =>
        prev.map((m) =>
          m.id === loadingMsg.id
            ? {
                ...m,
                text: data.answer,
                isLoading: false,
                videoRefs: data.video_references,
                sourcesCount: data.sources_count,
              }
            : m
        )
      );
    } catch (err) {
      setMessages((prev) =>
        prev.map((m) =>
          m.id === loadingMsg.id
            ? { ...m, text: `Errore: ${err.message}`, isLoading: false }
            : m
        )
      );
    } finally {
      setIsLoading(false);
    }
  }, [isLoading, messages, videoCount]);

  const handleSend = () => {
    const text = inputText.trim();
    if (!text) return;
    setInputText("");
    sendMessage(text);
  };

  const handleKeyDown = (e) => {
    if (e.key === "Enter" && !e.shiftKey) {
      e.preventDefault();
      handleSend();
    }
  };

  return (
    <div className="fixed inset-y-0 right-0 w-full sm:w-[480px] bg-white border-l border-gray-200 shadow-xl z-50 flex flex-col">
      {/* Header */}
      <div className="px-4 py-3 border-b border-gray-200 flex items-center justify-between shrink-0">
        <div>
          <h2 className="font-semibold text-gray-800 text-sm">Chatbot Videoteca</h2>
          <p className="text-xs text-gray-500">Cerca in {videoCount} video</p>
        </div>
        <button
          onClick={onClose}
          className="text-gray-400 hover:text-gray-600 text-xl transition-colors"
        >
          ×
        </button>
      </div>

      {/* Messages */}
      <div className="flex-1 overflow-y-auto px-4 py-3">
        {messages.length === 0 && (
          <div className="h-full flex flex-col items-center justify-center gap-4">
            <span className="text-3xl">🎬</span>
            <p className="text-gray-400 text-sm text-center">
              Fai una domanda su qualsiasi argomento trattato nei tuoi video
            </p>
            <div className="grid grid-cols-1 gap-2 w-full max-w-sm">
              {SUGGESTIONS.map((s) => (
                <button
                  key={s}
                  onClick={() => sendMessage(s)}
                  className="text-left text-xs bg-gray-50 hover:bg-blue-50 hover:text-blue-700 text-gray-600 border border-gray-200 hover:border-blue-200 rounded-lg px-3 py-2 transition-colors"
                >
                  {s}
                </button>
              ))}
            </div>
          </div>
        )}

        {messages.map((msg) => (
          <Message key={msg.id} msg={msg} onOpenVideo={onOpenVideo} />
        ))}
        <div ref={messagesEndRef} />
      </div>

      {/* Input */}
      <div className="px-4 py-3 border-t border-gray-200 shrink-0">
        <div className="flex gap-2">
          <input
            type="text"
            value={inputText}
            onChange={(e) => setInputText(e.target.value)}
            onKeyDown={handleKeyDown}
            placeholder="Cerca in tutti i video..."
            className="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
          />
          <button
            onClick={handleSend}
            disabled={isLoading || !inputText.trim()}
            className="bg-blue-600 hover:bg-blue-700 disabled:bg-gray-300 text-white font-medium px-4 py-2 rounded-lg text-sm transition-all shrink-0"
          >
            Invia
          </button>
        </div>
      </div>
    </div>
  );
}
