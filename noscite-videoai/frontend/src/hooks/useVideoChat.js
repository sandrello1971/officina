import { useState, useCallback } from "react";
import api from "../services/api";

let msgCounter = 0;

export default function useVideoChat() {
  const [messages, setMessages] = useState([]);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState(null);

  const sendMessage = useCallback(
    async (videoId, question) => {
      if (!question.trim() || !videoId) return;

      setError(null);
      const userMsg = {
        id: ++msgCounter,
        role: "user",
        text: question,
      };

      const loadingMsg = {
        id: ++msgCounter,
        role: "assistant",
        text: "",
        isLoading: true,
      };

      setMessages((prev) => [...prev, userMsg, loadingMsg]);
      setIsLoading(true);

      try {
        // Build history from last 6 messages (user + assistant pairs)
        const history = messages
          .filter((m) => !m.isLoading)
          .slice(-6)
          .map((m) => ({ role: m.role, content: m.text }));

        const data = await api.chat(videoId, question, history);

        setMessages((prev) =>
          prev.map((m) =>
            m.id === loadingMsg.id
              ? {
                  ...m,
                  text: data.answer,
                  timestamps: data.timestamps,
                  sources: data.sources,
                  isLoading: false,
                }
              : m
          )
        );
      } catch (err) {
        setError(err.message);
        setMessages((prev) =>
          prev.map((m) =>
            m.id === loadingMsg.id
              ? {
                  ...m,
                  text: `Errore: ${err.message}`,
                  isLoading: false,
                }
              : m
          )
        );
      } finally {
        setIsLoading(false);
      }
    },
    [messages]
  );

  const clearMessages = useCallback(() => {
    setMessages([]);
    setError(null);
  }, []);

  return { messages, isLoading, error, sendMessage, clearMessages };
}
