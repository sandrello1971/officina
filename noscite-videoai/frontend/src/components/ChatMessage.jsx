function parseTimestamp(str) {
  const parts = str.split(":").map(Number);
  if (parts.length === 3) {
    return parts[0] * 3600 + parts[1] * 60 + parts[2];
  }
  return parts[0] * 60 + parts[1];
}

function renderTextWithTimestamps(text, onTimestampClick) {
  // Split on timestamp patterns like [01:23] or [AUDIO 01:23] or [FRAME 01:23:45]
  const regex = /(\[(?:AUDIO |FRAME )?\d{1,2}:\d{2}(?::\d{2})?\])/g;
  const parts = text.split(regex);

  return parts.map((part, i) => {
    const match = part.match(
      /^\[(?:AUDIO |FRAME )?(\d{1,2}:\d{2}(?::\d{2})?)\]$/
    );
    if (match) {
      const ts = match[1];
      const seconds = parseTimestamp(ts);
      return (
        <button
          key={i}
          onClick={() => onTimestampClick(seconds)}
          className="inline-flex items-center gap-0.5 text-blue-600 font-mono text-sm underline cursor-pointer hover:text-blue-800 transition-colors mx-0.5"
        >
          ▶ {ts}
        </button>
      );
    }
    return <span key={i}>{part}</span>;
  });
}

export default function ChatMessage({ message, onTimestampClick }) {
  const isUser = message.role === "user";

  if (isUser) {
    return (
      <div className="flex justify-end mb-3">
        <div className="bg-blue-600 text-white rounded-2xl rounded-br-md px-4 py-2.5 max-w-[80%] shadow-sm">
          <p className="text-sm whitespace-pre-wrap">{message.text}</p>
        </div>
      </div>
    );
  }

  // Assistant message
  return (
    <div className="flex justify-start mb-3">
      <div className="bg-gray-100 text-gray-800 rounded-2xl rounded-bl-md px-4 py-2.5 max-w-[85%] shadow-sm">
        {message.isLoading ? (
          <div className="flex gap-1 py-1">
            <span className="w-2 h-2 bg-gray-400 rounded-full animate-bounce [animation-delay:-0.3s]" />
            <span className="w-2 h-2 bg-gray-400 rounded-full animate-bounce [animation-delay:-0.15s]" />
            <span className="w-2 h-2 bg-gray-400 rounded-full animate-bounce" />
          </div>
        ) : (
          <>
            <div className="text-sm whitespace-pre-wrap leading-relaxed">
              {renderTextWithTimestamps(message.text, onTimestampClick)}
            </div>
            {message.sources?.length > 0 && (
              <div className="mt-2 pt-2 border-t border-gray-200">
                <span className="text-xs text-gray-500">Fonti:</span>
                <div className="flex flex-wrap gap-1 mt-1">
                  {message.sources.map((src, i) => (
                    <button
                      key={i}
                      onClick={() =>
                        onTimestampClick(parseTimestamp(src.timestamp_str))
                      }
                      className="text-xs bg-gray-200 hover:bg-gray-300 text-gray-600 rounded-full px-2 py-0.5 transition-colors cursor-pointer"
                    >
                      {src.type === "transcript" ? "🎤" : "🖼️"}{" "}
                      {src.timestamp_str}
                    </button>
                  ))}
                </div>
              </div>
            )}
          </>
        )}
      </div>
    </div>
  );
}
