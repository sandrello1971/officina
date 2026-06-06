export default function ProgressBar({ progress, label, canChat }) {
  const barColor = canChat ? "bg-green-500" : "bg-blue-600";

  return (
    <div className="w-full mt-4">
      <div className="flex justify-between items-center mb-1">
        <span className="text-sm text-gray-600">{label}</span>
        <span className="text-sm font-medium text-gray-700">{progress}%</span>
      </div>
      <div className="w-full bg-gray-200 rounded-full h-3 overflow-hidden">
        <div
          className={`h-full rounded-full transition-all duration-500 ease-out ${barColor}`}
          style={{ width: `${progress}%` }}
        />
      </div>
      {canChat && (
        <div className="mt-2 flex items-center gap-1.5">
          <span className="inline-block w-2 h-2 rounded-full bg-green-500 animate-pulse" />
          <span className="text-sm text-green-600 font-medium">
            Chat disponibile sull'audio
          </span>
        </div>
      )}
    </div>
  );
}
