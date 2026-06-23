import { useState, useEffect, useRef, useCallback } from "react";
import CytoscapeComponent from "react-cytoscapejs";
import cytoscape from "cytoscape";
import fcose from "cytoscape-fcose";
import api from "../services/api";

cytoscape.use(fcose);

const SK = {
  positions: "graph_positions",
  minScore: "graph_min_score",
  mode: "graph_mode",
  collection: "graph_filter_collection",
  language: "graph_filter_language",
  tags: "graph_filter_tags",
  sidebar: "graph_sidebar",
};

function ls(key, fallback) {
  try {
    const v = localStorage.getItem(key);
    return v !== null ? v : fallback;
  } catch {
    return fallback;
  }
}

const STYLESHEET = [
  {
    selector: "node",
    style: {
      width: "mapData(degree, 1, 10, 30, 70)",
      height: "mapData(degree, 1, 10, 30, 70)",
      "background-color": "#534AB7",
      label: "data(label)",
      color: "#fff",
      "font-size": "10px",
      "text-valign": "center",
      "text-halign": "center",
      "text-wrap": "wrap",
      "text-max-width": "60px",
      "border-width": 0,
      "transition-property": "background-color, border-width, opacity",
      "transition-duration": "0.3s",
    },
  },
  { selector: 'node[collection = "Corsi e formazione"]', style: { "background-color": "#3C3489" } },
  { selector: 'node[collection = "Tutorial tecnico"]', style: { "background-color": "#085041" } },
  { selector: 'node[collection = "Interviste"]', style: { "background-color": "#993C1D" } },
  { selector: 'node[collection = "Presentazioni"]', style: { "background-color": "#633806" } },
  { selector: 'node[collection = "Marketing e vendite"]', style: { "background-color": "#7C2D8B" } },
  { selector: 'node[collection = "Tecnologia"]', style: { "background-color": "#1A6B5A" } },
  { selector: "node:selected", style: { "border-width": 3, "border-color": "#EF9F27", "background-color": "#BA7517" } },
  { selector: "node.highlighted", style: { "border-width": 2, "border-color": "#5DCAA5" } },
  { selector: "edge", style: { width: "mapData(score, 0.1, 1, 1, 4)", "line-color": "#7F77DD", opacity: 0.7, "curve-style": "bezier" } },
  { selector: "edge:selected", style: { "line-color": "#EF9F27", opacity: 1, width: 3 } },
];

const FCOSE = {
  name: "fcose", quality: "default", randomize: false, animate: true,
  animationDuration: 800, nodeRepulsion: 4500, idealEdgeLength: 100,
  edgeElasticity: 0.45, nestingFactor: 0.1, gravity: 0.25, numIter: 2500,
  tile: true, tilingPaddingVertical: 10, tilingPaddingHorizontal: 10,
};

const CC = {
  "Corsi e formazione": "#3C3489", "Tutorial tecnico": "#085041",
  Interviste: "#993C1D", Presentazioni: "#633806",
  "Marketing e vendite": "#7C2D8B", Tecnologia: "#1A6B5A",
};

const LANG_FLAGS = { Italian: "🇮🇹 Italiano", English: "🇬🇧 English", French: "🇫🇷 Français", Spanish: "🇪🇸 Español", German: "🇩🇪 Deutsch" };
const MODE_LABELS = { semantic_rules: "Semantica + Regole", semantic: "Solo contenuto", rules: "Solo categorie" };

function savePositions(cy) {
  const p = {};
  cy.nodes().forEach((n) => { p[n.id()] = n.position(); });
  localStorage.setItem(SK.positions, JSON.stringify(p));
}
function getSavedPositions() {
  try { return JSON.parse(localStorage.getItem(SK.positions)); } catch { return null; }
}

export default function GraphView({ onSelectVideo, onClose }) {
  const [elements, setElements] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [selectedNode, setSelectedNode] = useState(null);
  const [tooltip, setTooltip] = useState(null);
  const [edgeTooltip, setEdgeTooltip] = useState(null);
  const [nodeCount, setNodeCount] = useState(0);
  const [edgeCount, setEdgeCount] = useState(0);
  const [graphCollections, setGraphCollections] = useState([]);

  // Persisted state
  const [minScore, setMinScore] = useState(() => parseFloat(ls(SK.minScore, "0.15")));
  const [graphMode, setGraphMode] = useState(() => ls(SK.mode, "semantic_rules"));
  const [filterCollection, setFilterCollection] = useState(() => ls(SK.collection, ""));
  const [filterLanguage, setFilterLanguage] = useState(() => ls(SK.language, ""));
  const [filterTags, setFilterTags] = useState(() => {
    try { return JSON.parse(ls(SK.tags, "[]")); } catch { return []; }
  });
  const [showSidebar, setShowSidebar] = useState(() => ls(SK.sidebar, "true") === "true");

  // Sidebar data
  const [allFilters, setAllFilters] = useState({ collections: [], languages: [], tags: [] });
  const [tagSearch, setTagSearch] = useState("");

  const cyRef = useRef(null);
  const initRef = useRef(false);

  // Persist settings
  useEffect(() => { localStorage.setItem(SK.minScore, String(minScore)); }, [minScore]);
  useEffect(() => { localStorage.setItem(SK.mode, graphMode); }, [graphMode]);
  useEffect(() => { localStorage.setItem(SK.collection, filterCollection); }, [filterCollection]);
  useEffect(() => { localStorage.setItem(SK.language, filterLanguage); }, [filterLanguage]);
  useEffect(() => { localStorage.setItem(SK.tags, JSON.stringify(filterTags)); }, [filterTags]);
  useEffect(() => { localStorage.setItem(SK.sidebar, String(showSidebar)); }, [showSidebar]);

  // Fetch filters from backend
  useEffect(() => {
    api.getFilters().then(setAllFilters).catch(() => {});
  }, []);

  const fetchGraph = useCallback(async (score, mode) => {
    setLoading(true);
    setError(null);
    initRef.current = false;
    try {
      const data = await api.getGraph({ min_score: score, mode });
      setElements([...data.nodes, ...data.edges]);
      setNodeCount(data.nodes.length);
      setEdgeCount(data.edges.length);
      setGraphCollections([...new Set(data.nodes.map((n) => n.data.collection).filter(Boolean))]);
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { fetchGraph(minScore, graphMode); }, [fetchGraph, minScore, graphMode]);

  // Apply visual filters on cytoscape nodes (no backend reload)
  const applyFilters = useCallback((cy) => {
    if (!cy) return;
    console.log("[GRAPH] Edges:", cy.edges().length, "Nodes:", cy.nodes().length);

    // No filters active — restore everything to default
    if (!filterCollection && !filterLanguage && filterTags.length === 0) {
      cy.nodes().style({ opacity: 1 });
      cy.edges().style({ opacity: 0.7 });
      return;
    }

    cy.nodes().forEach((node) => {
      const d = node.data();
      let visible = true;
      if (filterCollection && d.collection !== filterCollection) visible = false;
      if (filterLanguage && d.language !== filterLanguage) visible = false;
      if (filterTags.length > 0) {
        const nt = d.tags || [];
        if (!filterTags.some((t) => nt.includes(t))) visible = false;
      }
      node.style("opacity", visible ? 1 : 0.1);
    });

    cy.edges().forEach((edge) => {
      const src = cy.getElementById(edge.data("source"));
      const tgt = cy.getElementById(edge.data("target"));
      const bothVisible = parseFloat(src.style("opacity")) > 0.5 && parseFloat(tgt.style("opacity")) > 0.5;
      edge.style("opacity", bothVisible ? 0.7 : 0.05);
    });
  }, [filterCollection, filterLanguage, filterTags]);

  useEffect(() => { if (cyRef.current) applyFilters(cyRef.current); }, [applyFilters]);

  const handleCyReady = useCallback((cy) => {
    if (cyRef.current === cy && initRef.current) return;
    cyRef.current = cy;
    initRef.current = true;

    const saved = getSavedPositions();
    if (saved) {
      let hasNew = false;
      cy.nodes().forEach((n) => { if (saved[n.id()]) n.position(saved[n.id()]); else hasNew = true; });
      if (hasNew) cy.layout({ ...FCOSE, randomize: false }).run();
      cy.fit(undefined, 40);
    } else {
      const layout = cy.layout(FCOSE);
      layout.on("layoutstop", () => savePositions(cy));
      layout.run();
    }

    cy.on("dragfree", "node", () => savePositions(cy));
    cy.on("tap", "node", (e) => {
      setSelectedNode(e.target.data());
      cy.nodes().removeClass("highlighted");
      e.target.neighborhood("node").addClass("highlighted");
    });
    cy.on("tap", (e) => { if (e.target === cy) { setSelectedNode(null); cy.nodes().removeClass("highlighted"); } });
    cy.on("mouseover", "node", (e) => {
      const d = e.target.data();
      setTooltip({ x: e.renderedPosition.x, y: e.renderedPosition.y - 10, label: d.title || d.label, collection: d.collection, tags: d.tags || [] });
    });
    cy.on("mouseout", "node", () => setTooltip(null));
    cy.on("mouseover", "edge", (e) => {
      const d = e.target.data();
      setEdgeTooltip({ x: e.renderedPosition.x, y: e.renderedPosition.y, score: d.score, reasons: d.reasons || [] });
    });
    cy.on("mouseout", "edge", () => setEdgeTooltip(null));

    applyFilters(cy);
  }, [applyFilters]);

  const handleRecalc = () => {
    const cy = cyRef.current;
    if (!cy) return;
    localStorage.removeItem(SK.positions);
    const layout = cy.layout(FCOSE);
    layout.on("layoutstop", () => savePositions(cy));
    layout.run();
  };

  const resetFilters = () => { setFilterCollection(""); setFilterLanguage(""); setFilterTags([]); };
  const hasActiveFilters = filterCollection || filterLanguage || filterTags.length > 0;

  // Compute tag counts from current graph nodes
  const tagCounts = {};
  elements.forEach((el) => {
    if (el.data.tags && !el.data.source) {
      (el.data.tags || []).forEach((t) => { tagCounts[t] = (tagCounts[t] || 0) + 1; });
    }
  });
  const sortedTags = Object.entries(tagCounts).sort((a, b) => b[1] - a[1]);
  const filteredTagList = tagSearch
    ? sortedTags.filter(([t]) => t.toLowerCase().includes(tagSearch.toLowerCase()))
    : sortedTags;

  // Collection counts
  const colCounts = {};
  elements.forEach((el) => { if (el.data.collection && !el.data.source) colCounts[el.data.collection] = (colCounts[el.data.collection] || 0) + 1; });

  // Language counts
  const langCounts = {};
  elements.forEach((el) => { if (el.data.language && !el.data.source) langCounts[el.data.language] = (langCounts[el.data.language] || 0) + 1; });

  // Connected videos for node sidebar
  const connectedVideos = selectedNode
    ? elements
        .filter((el) => el.data.source === selectedNode.id || el.data.target === selectedNode.id)
        .map((edge) => {
          const oid = edge.data.source === selectedNode.id ? edge.data.target : edge.data.source;
          const on = elements.find((el) => el.data.id === oid && !el.data.source);
          return { id: oid, label: on?.data?.title || on?.data?.label || oid.slice(0, 8), score: edge.data.score };
        })
        .sort((a, b) => b.score - a.score)
    : [];

  function reasonToItalian(r) {
    if (r.startsWith("tag:")) return `Tag condiviso: ${r.slice(4)}`;
    if (r.startsWith("collezione:")) return `Stessa collezione: ${r.slice(11)}`;
    if (r.startsWith("lingua:")) return `Stessa lingua: ${r.slice(7)}`;
    if (r.startsWith("semantica:")) return `Similarità contenuto: ${r.slice(10)}`;
    return r;
  }

  return (
    <div className="fixed inset-0 z-50 bg-[#0f1117] flex flex-col">
      {/* Header */}
      <div className="bg-[#181924] border-b border-gray-800 px-4 py-2.5 shrink-0">
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-3">
            <h2 className="text-white font-semibold text-sm">Mappa correlazioni</h2>
            <span className="text-[10px] text-gray-500">{nodeCount} video, {edgeCount} connessioni</span>
            <span className="text-[10px] bg-purple-900/50 text-purple-300 rounded-full px-2 py-0.5">
              {MODE_LABELS[graphMode] || graphMode}
            </span>
          </div>
          <div className="flex items-center gap-2">
            <select
              value={graphMode}
              onChange={(e) => setGraphMode(e.target.value)}
              className="text-[11px] bg-gray-800 border border-gray-700 text-gray-300 rounded px-2 py-1 focus:outline-none"
            >
              <option value="semantic_rules">Semantica + Regole</option>
              <option value="semantic">Solo contenuto</option>
              <option value="rules">Solo categorie</option>
            </select>
            <div className="flex items-center gap-1.5">
              <span className="text-[11px] text-gray-500">Soglia:</span>
              <input type="range" min="0.05" max="0.9" step="0.05" value={minScore}
                onChange={(e) => setMinScore(parseFloat(e.target.value))}
                className="w-20 accent-purple-500" />
              <span className="text-[11px] text-gray-300 font-mono w-7">{minScore.toFixed(2)}</span>
            </div>
            <button onClick={() => cyRef.current?.fit(undefined, 40)}
              className="text-[11px] text-gray-400 hover:text-white px-2 py-1 border border-gray-700 rounded transition-colors">
              Reset vista
            </button>
            <button onClick={handleRecalc}
              className="text-[11px] text-gray-400 hover:text-white px-2 py-1 border border-gray-700 rounded transition-colors">
              Ricalcola
            </button>
            <button onClick={() => setShowSidebar(!showSidebar)}
              className="text-[11px] text-gray-400 hover:text-white px-2 py-1 border border-gray-700 rounded transition-colors">
              {showSidebar ? "◀ Filtri" : "Filtri ▶"}
            </button>
            <button onClick={onClose} className="text-gray-400 hover:text-white text-lg ml-1">×</button>
          </div>
        </div>
        {/* Active filter badges */}
        {hasActiveFilters && (
          <div className="flex items-center gap-1.5 mt-1.5">
            <span className="text-[10px] text-gray-500">Filtri:</span>
            {filterCollection && (
              <button onClick={() => setFilterCollection("")}
                className="text-[10px] bg-blue-900/40 text-blue-300 rounded-full px-2 py-0.5 hover:bg-red-900/40 hover:text-red-300 transition-colors">
                {filterCollection} ×
              </button>
            )}
            {filterLanguage && (
              <button onClick={() => setFilterLanguage("")}
                className="text-[10px] bg-green-900/40 text-green-300 rounded-full px-2 py-0.5 hover:bg-red-900/40 hover:text-red-300 transition-colors">
                {LANG_FLAGS[filterLanguage] || filterLanguage} ×
              </button>
            )}
            {filterTags.map((t) => (
              <button key={t} onClick={() => setFilterTags((p) => p.filter((x) => x !== t))}
                className="text-[10px] bg-purple-900/40 text-purple-300 rounded-full px-2 py-0.5 hover:bg-red-900/40 hover:text-red-300 transition-colors">
                {t} ×
              </button>
            ))}
          </div>
        )}
      </div>

      {/* Body */}
      <div className="flex-1 flex overflow-hidden relative">
        {/* Graph area */}
        <div className="flex-1 relative">
          {loading && (
            <div className="absolute inset-0 flex items-center justify-center z-20">
              <div className="flex items-center gap-3">
                <div className="animate-spin rounded-full h-6 w-6 border-2 border-purple-500 border-t-transparent" />
                <span className="text-gray-400 text-sm">Caricamento mappa...</span>
              </div>
            </div>
          )}
          {error && <div className="absolute inset-0 flex items-center justify-center z-20"><p className="text-red-400 text-sm">{error}</p></div>}
          {!loading && elements.length === 0 && (
            <div className="absolute inset-0 flex items-center justify-center z-20 text-center">
              <div><span className="text-4xl block mb-3 opacity-40">🕸</span><p className="text-gray-500 text-sm">Carica almeno 2 video per vedere le correlazioni</p></div>
            </div>
          )}
          {elements.length > 0 && (
            <CytoscapeComponent elements={elements} stylesheet={STYLESHEET} layout={{ name: "preset" }}
              style={{ width: "100%", height: "100%" }} cy={(cy) => handleCyReady(cy)} />
          )}

          {/* Node tooltip */}
          {tooltip && (
            <div className="absolute z-30 bg-gray-900 border border-gray-700 rounded-lg px-3 py-2 pointer-events-none shadow-lg"
              style={{ left: tooltip.x + 10, top: tooltip.y - 60, maxWidth: 220 }}>
              <p className="text-white text-xs font-medium truncate">{tooltip.label}</p>
              {tooltip.collection && <p className="text-gray-400 text-[10px] mt-0.5">{tooltip.collection}</p>}
              {tooltip.tags?.length > 0 && (
                <div className="flex flex-wrap gap-1 mt-1">
                  {tooltip.tags.slice(0, 4).map((t) => (
                    <span key={t} className="text-[9px] bg-purple-900/50 text-purple-300 rounded px-1 py-0.5">{t}</span>
                  ))}
                </div>
              )}
            </div>
          )}

          {/* Edge tooltip */}
          {edgeTooltip && (
            <div className="absolute z-30 bg-gray-900 border border-gray-700 rounded-lg px-3 py-2 pointer-events-none shadow-lg"
              style={{ left: edgeTooltip.x + 10, top: edgeTooltip.y - 40, maxWidth: 250 }}>
              <p className="text-white text-xs font-medium">Correlazione: {edgeTooltip.score.toFixed(2)}</p>
              <div className="mt-1 space-y-0.5">
                {edgeTooltip.reasons.map((r, i) => (
                  <p key={i} className="text-[10px] text-gray-400">{reasonToItalian(r)}</p>
                ))}
              </div>
            </div>
          )}

          {/* Legend */}
          <div className="absolute bottom-4 left-4 bg-gray-900/80 border border-gray-800 rounded-lg px-3 py-2 z-20">
            <p className="text-[10px] text-gray-500 mb-1.5 font-medium">Collezioni</p>
            <div className="space-y-1">
              {graphCollections.map((c) => (
                <div key={c} className="flex items-center gap-1.5">
                  <span className="w-2.5 h-2.5 rounded-full shrink-0" style={{ backgroundColor: CC[c] || "#534AB7" }} />
                  <span className="text-[10px] text-gray-400">{c}</span>
                </div>
              ))}
            </div>
          </div>

          {/* Node info sidebar */}
          {selectedNode && (
            <div className="absolute top-0 right-0 w-64 h-full bg-[#181924] border-l border-gray-800 overflow-y-auto z-20">
              <div className="p-4 space-y-3">
                {selectedNode.has_thumbnail && (
                  <img src={`/api/videos/${selectedNode.id}/thumbnail`} className="w-full aspect-video object-cover rounded-lg" alt="" />
                )}
                <h3 className="text-white font-medium text-sm">{selectedNode.title || selectedNode.label}</h3>
                <div className="flex flex-wrap gap-1.5">
                  {selectedNode.collection && (
                    <span className="text-[10px] text-white rounded-full px-2 py-0.5" style={{ backgroundColor: CC[selectedNode.collection] || "#534AB7" }}>
                      {selectedNode.collection}
                    </span>
                  )}
                  {selectedNode.language && <span className="text-[10px] bg-gray-700 text-gray-300 rounded-full px-2 py-0.5">{selectedNode.language}</span>}
                </div>
                {selectedNode.tags?.length > 0 && (
                  <div className="flex flex-wrap gap-1">
                    {selectedNode.tags.map((t) => (
                      <span key={t} className="text-[10px] bg-purple-900/40 text-purple-300 rounded-full px-1.5 py-0.5">{t}</span>
                    ))}
                  </div>
                )}
                {selectedNode.summary && <p className="text-xs text-gray-400 leading-relaxed line-clamp-3">{selectedNode.summary}</p>}
                <div className="text-[10px] text-gray-600 flex gap-3">
                  <span>{selectedNode.duration_str}</span>
                  <span>{selectedNode.chunks_count} chunk</span>
                </div>
                {connectedVideos.length > 0 && (
                  <div>
                    <p className="text-[10px] text-gray-500 font-medium mb-1.5">Connessioni ({connectedVideos.length})</p>
                    <div className="space-y-1">
                      {connectedVideos.map((cv) => (
                        <button key={cv.id} onClick={() => {
                          const cy = cyRef.current;
                          if (cy) { cy.nodes().removeClass("highlighted"); const n = cy.getElementById(cv.id); if (n.length) { n.select(); setSelectedNode(n.data()); n.neighborhood("node").addClass("highlighted"); } }
                        }}
                          className="w-full flex items-center justify-between text-left px-2 py-1 rounded hover:bg-gray-800 transition-colors">
                          <span className="text-xs text-gray-300 truncate">{cv.label}</span>
                          <span className="text-[10px] text-gray-600 font-mono shrink-0 ml-1">{cv.score.toFixed(2)}</span>
                        </button>
                      ))}
                    </div>
                  </div>
                )}
                <button onClick={() => onSelectVideo(selectedNode.id)}
                  className="w-full bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium py-2 rounded-lg transition-colors mt-2">
                  Apri video
                </button>
              </div>
            </div>
          )}
        </div>

        {/* Filter sidebar */}
        {showSidebar && (
          <div className="w-[220px] bg-[#181924] border-l border-gray-800 overflow-y-auto shrink-0">
            <div className="p-3">
              <div className="flex items-center justify-between mb-3">
                <span className="text-xs font-semibold text-gray-300">Filtri</span>
                <div className="flex items-center gap-1">
                  {hasActiveFilters && (
                    <button onClick={resetFilters} className="text-[10px] text-red-400 hover:text-red-300 transition-colors">Reset</button>
                  )}
                  <button onClick={() => setShowSidebar(false)} className="text-gray-500 hover:text-white text-sm ml-1">×</button>
                </div>
              </div>

              {/* Collections */}
              <div className="mb-4">
                <p className="text-[10px] uppercase tracking-wider text-gray-500 font-medium mb-1.5">Collezioni</p>
                <div className="space-y-0.5">
                  {Object.entries(colCounts).sort((a, b) => b[1] - a[1]).map(([col, count]) => (
                    <button key={col} onClick={() => setFilterCollection(filterCollection === col ? "" : col)}
                      className={`w-full flex items-center gap-1.5 px-2 py-1.5 rounded text-left transition-colors ${
                        filterCollection === col ? "bg-blue-900/30" : "hover:bg-gray-800"
                      }`}>
                      <span className="w-2 h-2 rounded-full shrink-0" style={{ backgroundColor: CC[col] || "#534AB7" }} />
                      <span className="text-[11px] text-gray-300 truncate flex-1">{col}</span>
                      <span className="text-[10px] text-gray-600">({count})</span>
                      {filterCollection === col && <span className="text-blue-400 text-[10px]">✓</span>}
                    </button>
                  ))}
                </div>
              </div>

              {/* Languages */}
              {Object.keys(langCounts).length > 0 && (
                <div className="mb-4">
                  <p className="text-[10px] uppercase tracking-wider text-gray-500 font-medium mb-1.5">Lingue</p>
                  <div className="space-y-0.5">
                    {Object.entries(langCounts).sort((a, b) => b[1] - a[1]).map(([lang, count]) => (
                      <button key={lang} onClick={() => setFilterLanguage(filterLanguage === lang ? "" : lang)}
                        className={`w-full flex items-center gap-1.5 px-2 py-1.5 rounded text-left transition-colors ${
                          filterLanguage === lang ? "bg-green-900/30" : "hover:bg-gray-800"
                        }`}>
                        <span className="text-[11px] text-gray-300 flex-1">{LANG_FLAGS[lang] || lang}</span>
                        <span className="text-[10px] text-gray-600">({count})</span>
                        {filterLanguage === lang && <span className="text-green-400 text-[10px]">✓</span>}
                      </button>
                    ))}
                  </div>
                </div>
              )}

              {/* Tags */}
              {sortedTags.length > 0 && (
                <div>
                  <p className="text-[10px] uppercase tracking-wider text-gray-500 font-medium mb-1.5">Tag</p>
                  <input type="text" value={tagSearch} onChange={(e) => setTagSearch(e.target.value)}
                    placeholder="Cerca tag..." className="w-full text-[11px] bg-gray-800 border border-gray-700 text-gray-300 rounded px-2 py-1 mb-2 focus:outline-none focus:border-purple-500" />
                  <div className="flex flex-wrap gap-1 max-h-[200px] overflow-y-auto">
                    {filteredTagList.map(([tag, count]) => {
                      const active = filterTags.includes(tag);
                      return (
                        <button key={tag}
                          onClick={() => setFilterTags((prev) => active ? prev.filter((t) => t !== tag) : [...prev, tag])}
                          className={`text-[10px] rounded-full px-2 py-0.5 transition-colors ${
                            active ? "bg-purple-600 text-white" : "bg-gray-800 text-gray-400 hover:bg-gray-700"
                          }`}>
                          {tag} <span className="opacity-60">({count})</span>
                        </button>
                      );
                    })}
                  </div>
                </div>
              )}
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
