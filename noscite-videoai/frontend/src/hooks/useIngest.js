import { useState, useEffect, useRef, useCallback } from "react";
import api from "../services/api";

export default function useIngest() {
  const [status, setStatus] = useState("idle");
  const [progress, setProgress] = useState(0);
  const [step, setStep] = useState("");
  const [stepLabel, setStepLabel] = useState("");
  const [canChat, setCanChat] = useState(false);
  const [videoId, setVideoId] = useState(null);
  const [error, setError] = useState(null);
  const [skipped, setSkipped] = useState(false);

  const intervalRef = useRef(null);

  const stopPolling = useCallback(() => {
    if (intervalRef.current) {
      clearInterval(intervalRef.current);
      intervalRef.current = null;
    }
  }, []);

  const startPolling = useCallback(
    (vid) => {
      stopPolling();
      intervalRef.current = setInterval(async () => {
        try {
          const data = await api.getStatus(vid);
          setProgress(data.progress ?? 0);
          setStep(data.step ?? "");
          setStepLabel(data.step_label ?? "");
          setCanChat(data.can_chat ?? false);

          if (data.status === "ready") {
            setStatus("ready");
            setProgress(100);
            setCanChat(true);
            stopPolling();
          } else if (data.status === "error") {
            setStatus("error");
            setError(data.error || "Errore durante l'elaborazione");
            stopPolling();
          } else {
            setStatus("processing");
          }
        } catch (err) {
          console.error("[useIngest] Polling error:", err);
        }
      }, 2000);
    },
    [stopPolling]
  );

  const uploadVideo = useCallback(
    async (file) => {
      setStatus("uploading");
      setProgress(0);
      setError(null);
      setSkipped(false);

      try {
        const data = await api.ingestVideo(file);
        setVideoId(data.video_id);

        if (data.skipped) {
          setSkipped(true);
          setStatus("ready");
          setCanChat(true);
          setProgress(100);
          setStepLabel("Pronto!");
        } else if (data.status === "processing") {
          setStatus("processing");
          setStepLabel("Analisi avviata...");
          startPolling(data.video_id);
        }
      } catch (err) {
        setStatus("error");
        setError(err.message);
      }
    },
    [startPolling]
  );

  const reset = useCallback(() => {
    stopPolling();
    setStatus("idle");
    setProgress(0);
    setStep("");
    setStepLabel("");
    setCanChat(false);
    setVideoId(null);
    setError(null);
    setSkipped(false);
  }, [stopPolling]);

  useEffect(() => {
    return () => stopPolling();
  }, [stopPolling]);

  return {
    status,
    progress,
    step,
    stepLabel,
    canChat,
    videoId,
    error,
    skipped,
    uploadVideo,
    startPolling,
    stopPolling,
    reset,
  };
}
