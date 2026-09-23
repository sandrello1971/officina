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
        $html = $this->rewriteDataEndpoint($html);

        $meta = '<meta name="csrf-token" content="' . e($csrfToken) . '">';
        // il JS dei canvas cita "meta[name=csrf-token]": serve un vero tag <meta>, non la stringa
        if (!preg_match('/<meta[^>]+name=["\']?csrf-token/i', $html)) {
            $html = preg_match('/<head[^>]*>/i', $html)
                ? preg_replace('/<head([^>]*)>/i', '<head$1>' . $meta, $html, 1)
                : $meta . $html;
        }

        if (str_contains($html, 'data-field')) {
            $html = str_contains($html, '</body>')
                ? str_replace('</body>', $this->toolbar() . '</body>', $html)
                : $html . $this->toolbar();
        }

        return $html;
    }

    /** Porta l'endpoint cablato nei canvas sul path reale della rotta student.canvas.get. */
    public function rewriteDataEndpoint(string $html): string
    {
        $path = parse_url(route('student.canvas.get', ['material' => '__MID__']), PHP_URL_PATH) ?: '/canvas/__MID__/data';
        $prefix = substr($path, 0, strpos($path, '__MID__'));

        return $prefix === '/learn/canvas/' ? $html : str_replace('/learn/canvas/', $prefix, $html);
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
        if (!str_contains($html, 'data-field')) {
            return [];
        }

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?>' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);

        $labels = [];
        foreach ($xpath->query('//*[@data-field]') as $el) {
            /** @var DOMElement $el */
            $key = $el->getAttribute('data-field');
            if ($key === '' || isset($labels[$key])) {
                continue;
            }
            $labels[$key] = $this->labelFor($el, $xpath) ?? $this->humanize($key);
        }

        return $labels;
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
    private function toolbar(): string
    {
        return <<<'HTML'
<div id="officina-canvas-toolbar" style="position:fixed;top:12px;right:12px;display:flex;gap:6px;z-index:9999;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif">
  <button type="button" data-officina="download" style="background:#fff;color:#1a1a1a;border:1px solid #ccc;border-radius:6px;padding:6px 10px;font-size:13px;cursor:pointer">⬇ Scarica</button>
  <button type="button" data-officina="print" style="background:#fff;color:#1a1a1a;border:1px solid #ccc;border-radius:6px;padding:6px 10px;font-size:13px;cursor:pointer">🖨 Stampa</button>
</div>
<style>@media print{#officina-canvas-toolbar,.status{display:none!important}textarea{overflow:visible!important}}</style>
<script>
(function(){
  function clean(t){return (t||'').replace(/\s+/g,' ').trim();}
  function labelOf(el){
    if(el.getAttribute('aria-label'))return clean(el.getAttribute('aria-label'));
    if(el.id){var l=document.querySelector('label[for="'+el.id+'"]');if(l)return clean(l.textContent);}
    var p=el.previousElementSibling;if(p&&p.tagName==='LABEL')return clean(p.textContent);
    for(var n=el.parentElement;n&&n!==document.body;n=n.parentElement){var h=n.querySelector(':scope > h1, :scope > h2, :scope > h3, :scope > h4');if(h)return clean(h.textContent);}
    return el.getAttribute('data-field');
  }
  function valueOf(el){
    if(el.type==='checkbox')return el.checked?'sì':'no';
    if('value' in el)return el.value;
    return el.textContent;
  }
  function download(){
    var title=clean((document.querySelector('h1')||{}).textContent)||document.title||'Canvas';
    var out=['# '+title,'','_Esportato il '+new Date().toLocaleString('it-IT')+'_',''];
    var last=null;
    document.querySelectorAll('[data-field]').forEach(function(el){
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
  document.querySelector('[data-officina=print]').addEventListener('click',function(){fitTextareas();window.print();});
})();
</script>
HTML;
    }
}
