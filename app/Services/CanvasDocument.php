<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Canvas HTML caricati come materiali (file_type=canvas): li adatta al
 * momento del serving e ne ricava le etichette dei campi.
 *
 * I canvas sono file statici scritti a mano/da AI: molti hanno cablato
 * l'endpoint pre-split sottodomini (/learn/canvas/{mid}/data), nessuno
 * espone il token CSRF e non c'è modo di tenerne una copia. Correggere qui
 * vale per tutti i canvas, presenti e futuri, senza toccare i file.
 */
class CanvasDocument
{
    public function prepareForStudent(string $html, string $csrfToken): string
    {
        $legacyMaterialPath = $this->hasLegacyMaterialPath($html);
        $html = $this->rewriteDataEndpoint($html);

        $meta = '<meta name="csrf-token" content="' . e($csrfToken) . '">';
        // il JS dei canvas cita "meta[name=csrf-token]": serve un vero tag <meta>, non la stringa
        if (!preg_match('/<meta[^>]+name=["\']?csrf-token/i', $html)) {
            $html = preg_match('/<head[^>]*>/i', $html)
                ? preg_replace('/<head([^>]*)>/i', '<head$1>' . $meta, $html, 1)
                : $meta . $html;
        }

        // Canvas che ricavavano l'id dal path /learn/material/{id}/canvas: finora
        // salvavano solo nel localStorage del browser. Al primo accesso il
        // lavoro locale va mostrato e portato sul server, non perso.
        if ($legacyMaterialPath && ($keys = $this->localStorageKeys($html))) {
            $shim = $this->localToServerShim($keys);
            $html = preg_replace('/(<meta name="csrf-token"[^>]*>)/i', '$1' . $shim, $html, 1, $count);
            if (!$count) {
                $html = $shim . $html;
            }
        }

        if ($this->hasFormControls($html)) {
            // i canvas che hanno già una propria stampa ricevono solo "Scarica"
            $toolbar = $this->toolbar(!str_contains($html, 'window.print'));
            $html = str_contains($html, '</body>')
                ? str_replace('</body>', $toolbar . '</body>', $html)
                : $html . $toolbar;
        }

        return $html;
    }

    /**
     * Porta gli URL cablati nei canvas (pre-split sottodomini, prefisso /learn)
     * sui path reali: l'endpoint dati e il path da cui alcuni canvas ricavano
     * l'id del materiale (regex JS /\/learn\/material\/([^/]+)\/canvas/).
     */
    public function rewriteDataEndpoint(string $html): string
    {
        $path = parse_url(route('student.canvas.get', ['material' => '__MID__']), PHP_URL_PATH) ?: '/canvas/__MID__/data';
        $prefix = substr($path, 0, strpos($path, '__MID__'));
        if ($prefix !== '/learn/canvas/') {
            $html = str_replace('/learn/canvas/', $prefix, $html);
        }

        $materialPath = parse_url(route('student.material.canvas', ['material' => '__MID__']), PHP_URL_PATH) ?: '/material/__MID__/canvas';
        $materialPrefix = substr($materialPath, 0, strpos($materialPath, '__MID__'));
        if ($materialPrefix !== '/learn/material/') {
            $html = str_replace('\\/learn\\/material\\/', str_replace('/', '\\/', $materialPrefix), $html);
        }

        return $html;
    }

    /** Campi compilabili: data-field, oppure input/textarea/select identificati. */
    public function hasFormControls(string $html): bool
    {
        return str_contains($html, 'data-field')
            || (bool) preg_match('/<(?:textarea|select)\b|<input\b(?![^>]*type=["\']?(?:hidden|button|submit|reset|file|image)\b)/i', $html);
    }

    private function hasLegacyMaterialPath(string $html): bool
    {
        return str_contains($html, '\\/learn\\/material\\/');
    }

    /** Chiavi localStorage usate dal canvas (letterali stringa). */
    private function localStorageKeys(string $html): array
    {
        preg_match_all('/localStorage\.(?:getItem|setItem)\(\s*[\'"]([^\'"]+)[\'"]/', $html, $direct);
        preg_match_all('/\b(?:const|let|var)\s+[A-Z_]*KEY\s*=\s*[\'"]([^\'"]+)[\'"]/', $html, $consts);

        return array_values(array_unique(array_merge($direct[1], $consts[1])));
    }

    /**
     * Intercetta la GET dei dati del canvas: se il server non ha nulla ma il
     * browser ha una copia locale, la restituisce al canvas e la salva sul
     * server (una volta, poi il server non è più vuoto).
     */
    private function localToServerShim(array $keys): string
    {
        $json = json_encode($keys, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

        return <<<HTML
<script>
(function(keys){
  var of=window.fetch; if(!of) return;
  function localCopy(){
    for(var i=0;i<keys.length;i++){
      try{var v=JSON.parse(localStorage.getItem(keys[i])||'null');if(v&&typeof v==='object'&&Object.keys(v).length)return v;}catch(e){}
    }
    return null;
  }
  window.fetch=function(input,init){
    var url=typeof input==='string'?input:(input&&input.url)||'';
    var method=((init&&init.method)||'GET').toUpperCase();
    var p=of.apply(this,arguments);
    if(method!=='GET'||!/\/canvas\/[^\/?#]+\/data(?:[?#]|$)/.test(url))return p;
    return p.then(function(r){
      if(!r.ok)return r;
      return r.clone().json().then(function(j){
        var d=j&&j.data;
        if(d&&typeof d==='object'&&Object.keys(d).length)return r;
        var local=localCopy(); if(!local)return r;
        var t=(document.querySelector('meta[name=csrf-token]')||{}).content||'';
        of(url,{method:'PATCH',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':t},body:JSON.stringify({data:local})}).catch(function(){});
        return new Response(JSON.stringify({data:local}),{status:200,headers:{'Content-Type':'application/json'}});
      }).catch(function(){return r;});
    });
  };
})({$json});
</script>
HTML;
    }

    /**
     * Etichetta leggibile per ogni data-field, nell'ordine del documento:
     * aria-label, <label for>, <label> adiacente, poi il primo titolo del
     * contenitore più vicino, infine la chiave umanizzata.
     *
     * @return array<string,string>
     */
    public function fieldLabels(string $html): array
    {
        if (!$this->hasFormControls($html)) {
            return [];
        }

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?>' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);

        // I canvas salvano per data-field, data-k, id o name: l'etichetta si
        // registra sotto ciascuna chiave possibile, la vista usa quella salvata.
        $labels = [];
        $query = '//*[@data-field] | //*[@data-k] | //input[@id or @name] | //textarea[@id or @name] | //select[@id or @name]';
        foreach ($xpath->query($query) as $el) {
            /** @var DOMElement $el */
            if (in_array(strtolower($el->getAttribute('type')), ['hidden', 'button', 'submit', 'reset', 'file', 'image'], true)) {
                continue;
            }
            $keys = array_filter([$el->getAttribute('data-field'), $el->getAttribute('data-k'), $el->getAttribute('id'), $el->getAttribute('name')]);
            if (!$keys) {
                continue;
            }
            $label = $this->labelFor($el, $xpath);
            foreach ($keys as $key) {
                $labels[$key] ??= $label ?? $this->humanize($key);
            }
        }

        return $labels;
    }

    /**
     * Intestazioni delle tabelle compilabili (thead), senza le colonne vuote
     * dei pulsanti: servono a dare un nome alle colonne delle righe salvate
     * come array (inventari, matrici, registri).
     *
     * @return array<int, array{title:?string, headers:array<int,string>}>
     */
    public function tableHeaders(string $html): array
    {
        if (!str_contains($html, '<thead')) {
            return [];
        }

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?>' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);

        $tables = [];
        foreach ($xpath->query('//table[thead]') as $table) {
            /** @var DOMElement $table */
            $headers = [];
            foreach ($xpath->query('./thead//th', $table) as $th) {
                $headers[] = $this->clean($th->textContent);
            }
            while ($headers && end($headers) === '') {
                array_pop($headers);
            }
            if ($headers) {
                $tables[] = ['title' => $this->labelFor($table, $xpath), 'headers' => $headers];
            }
        }

        return $tables;
    }

    /**
     * Ordine di colonna delle chiavi di una riga salvata. Il DB (jsonb) non
     * conserva l'ordine delle chiavi, quindi lo si ricava dal sorgente del
     * canvas: la prima occorrenza di ciascuna chiave come data-field/data-f/
     * data-k (anche a suffisso, es. data-field="r1_rep") o come proprietà
     * in un oggetto letterale (collect/seed) segue l'ordine delle colonne.
     * Null se anche una sola chiave non si trova.
     */
    public function columnOrder(array $keys, string $html): ?array
    {
        // Prima il modello di riga (attributi): i dati di esempio/seed in un
        // oggetto letterale possono comparire prima e con meno colonne.
        $attr = [
            '/data-(?:field|f|k)\s*=\s*["\']?(?:[\w$\{\}-]*_)?%s["\'\s>\]]/',
            '/\[data-(?:field|f|k)\$?=["\']?_?%s["\']?\]/',
        ];
        $literal = ['/[\{,]\s*["\']?%s["\']?\s*:/'];

        foreach ([$attr, $literal] as $patterns) {
            $pos = [];
            foreach ($keys as $key) {
                $k = preg_quote((string) $key, '/');
                foreach ($patterns as $re) {
                    if (preg_match(sprintf($re, $k), $html, $m, PREG_OFFSET_CAPTURE)) {
                        $pos[$key] = min($pos[$key] ?? PHP_INT_MAX, $m[0][1]);
                    }
                }
            }
            if (count($pos) === count($keys)) {
                asort($pos);

                return array_keys($pos);
            }
        }

        return null;
    }

    private function labelFor(DOMElement $el, DOMXPath $xpath): ?string
    {
        if ($el->hasAttribute('aria-label')) {
            return $this->clean($el->getAttribute('aria-label'));
        }

        if ($id = $el->getAttribute('id')) {
            $label = $xpath->query('//label[@for="' . $id . '"]')->item(0);
            if ($label) {
                return $this->clean($label->textContent);
            }
        }

        // label immediatamente precedente
        $prev = $el->previousSibling;
        while ($prev && !($prev instanceof DOMElement)) {
            $prev = $prev->previousSibling;
        }
        if ($prev instanceof DOMElement && strtolower($prev->nodeName) === 'label') {
            return $this->clean($prev->textContent);
        }

        // titolo della card/sezione che contiene il campo
        for ($node = $el->parentNode; $node instanceof DOMElement; $node = $node->parentNode) {
            $heading = $xpath->query('./h1|./h2|./h3|./h4', $node)->item(0);
            if ($heading) {
                return $this->clean($heading->textContent);
            }
            if (in_array(strtolower($node->nodeName), ['body', 'form'], true)) {
                break;
            }
        }

        return null;
    }

    private function clean(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    private function humanize(string $key): string
    {
        return ucfirst(str_replace(['_json', '_', '-'], ['', ' ', ' '], $key));
    }

    /** Barra "Scarica / Stampa" iniettata nei canvas compilabili. */
    private function toolbar(bool $withPrint = true): string
    {
        $print = $withPrint
            ? '<button type="button" data-officina="print" style="background:#fff;color:#1a1a1a;border:1px solid #ccc;border-radius:6px;padding:6px 10px;font-size:13px;cursor:pointer">🖨 Stampa</button>'
            : '';

        return <<<HTML
<div id="officina-canvas-toolbar" style="position:fixed;top:12px;right:12px;display:flex;gap:6px;z-index:9999;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif">
  <button type="button" data-officina="download" style="background:#fff;color:#1a1a1a;border:1px solid #ccc;border-radius:6px;padding:6px 10px;font-size:13px;cursor:pointer">⬇ Scarica</button>
  {$print}
</div>
HTML . <<<'HTML'

<style>@media print{#officina-canvas-toolbar,.status{display:none!important}textarea{overflow:visible!important}}</style>
<script>
(function(){
  function clean(t){return (t||'').replace(/\s+/g,' ').trim();}
  function labelOf(el){
    if(el.getAttribute('aria-label'))return clean(el.getAttribute('aria-label'));
    if(el.id){var l=document.querySelector('label[for="'+el.id+'"]');if(l)return clean(l.textContent);}
    var p=el.previousElementSibling;if(p&&p.tagName==='LABEL')return clean(p.textContent);
    for(var n=el.parentElement;n&&n!==document.body;n=n.parentElement){var h=n.querySelector(':scope > h1, :scope > h2, :scope > h3, :scope > h4');if(h)return clean(h.textContent);}
    if(el.placeholder)return clean(el.placeholder);
    return el.getAttribute('data-field')||el.getAttribute('data-k')||el.id||el.name||'Campo';
  }
  function valueOf(el){
    if(el.type==='checkbox')return el.checked?'sì':'no';
    if('value' in el)return el.value;
    return el.textContent;
  }
  function download(){
    var title=clean((document.querySelector('h1')||{}).textContent)||document.title||'Canvas';
    var out=['# '+title,'','_Esportato il '+new Date().toLocaleString('it-IT')+'_',''];
    var last=null, done=new Set();
    function cellValue(td){
      var c=td.querySelectorAll('input,textarea,select');
      if(!c.length)return clean(td.textContent);
      return Array.prototype.map.call(c,function(el){done.add(el);
        if(el.type==='checkbox')return el.checked?'sì':'no';
        if(el.tagName==='SELECT'&&el.selectedIndex>=0)return clean(el.options[el.selectedIndex].text);
        return (el.value||'').replace(/\s*\n\s*/g,' ');}).join(' ');
    }
    function tableTitle(t){
      for(var n=t;n&&n!==document.body;n=n.parentElement){
        for(var p=n.previousElementSibling;p;p=p.previousElementSibling){
          if(p.tagName==='HEADER'||p.querySelector('h1')||p.id==='officina-canvas-toolbar')continue;
          if(/^H[2-4]$/.test(p.tagName)||/(^|[-_ ])(head|title|heading)([-_ ]|$)/i.test(p.className||''))
            return clean((p.firstElementChild||p).textContent).slice(0,90);
        }
      }
      return null;
    }
    var fillTables=Array.prototype.filter.call(document.querySelectorAll('table'),function(t){return t.querySelector('tbody input, tbody textarea, tbody select');});
    fillTables.forEach(function(t,ti){
      var hs=Array.prototype.map.call(t.querySelectorAll('thead th'),function(th){return clean(th.textContent);});
      var keep=hs.map(function(h,i){return h!=='';});
      var rows=Array.prototype.map.call(t.querySelectorAll('tbody tr'),function(tr){
        return Array.prototype.map.call(tr.children,cellValue).filter(function(v,i){return keep[i]!==false;});});
      var head=hs.filter(function(h){return h!=='';});
      var title=tableTitle(t)||('Tabella compilata'+(fillTables.length>1?' '+(ti+1):''));
      out.push('## '+title,'');
      if(head.length){out.push('| '+head.join(' | ')+' |','|'+head.map(function(){return ' --- ';}).join('|')+'|');}
      rows.forEach(function(r){out.push('| '+r.map(function(v){return (v||'—').replace(/\|/g,'/');}).join(' | ')+' |');});
      out.push('');last=title;
    });
    var sel=document.querySelector('[data-field]')?'[data-field]':'input,textarea,select';
    document.querySelectorAll(sel).forEach(function(el){
      if(done.has(el)||el.closest('#officina-canvas-toolbar'))return;
      if(/^(hidden|button|submit|reset|file|image)$/i.test(el.type||''))return;
      if(el.type==='radio'&&!el.checked)return;
      var label=labelOf(el);
      if(label!==last){out.push('## '+label,'');last=label;}
      out.push(valueOf(el)||'—','');
    });
    var blob=new Blob([out.join('\n')],{type:'text/markdown;charset=utf-8'});
    var a=document.createElement('a');a.href=URL.createObjectURL(blob);
    a.download=title.replace(/[^\wÀ-ſ -]+/g,'').trim().replace(/\s+/g,'-')+'.md';
    document.body.appendChild(a);a.click();setTimeout(function(){URL.revokeObjectURL(a.href);a.remove();},0);
  }
  function fitTextareas(){document.querySelectorAll('textarea').forEach(function(t){t.style.height='auto';t.style.height=(t.scrollHeight+4)+'px';});}
  window.addEventListener('beforeprint',fitTextareas);
  document.querySelector('[data-officina=download]').addEventListener('click',download);
  var pb=document.querySelector('[data-officina=print]');
  if(pb)pb.addEventListener('click',function(){fitTextareas();window.print();});
})();
</script>
HTML;
    }
}
