<div class="p-4 rounded-xl shadow-sm" style="background:linear-gradient(135deg,#1A1F1F,#3A8C89);color:white">

    <div class="flex items-center gap-2 mb-4">
        <span class="text-lg">&#10022;</span>
        <h3 class="font-bold text-sm">Genera con Claude AI</h3>
        <span class="text-xs px-2 py-0.5 rounded-full ml-auto" style="background:rgba(255,255,255,0.15)">Claude Sonnet</span>
    </div>

    @if($generated)
    <div class="p-3 rounded-lg mb-4 text-sm" style="background:rgba(85,177,174,0.3)">
        &#10003; Contenuto generato e inserito nell'editor!
    </div>
    @endif

    @if($error)
    <div class="p-3 rounded-lg mb-4 text-sm" style="background:rgba(255,100,100,0.3)">
        {{ $error }}
    </div>
    @endif

    {{-- PROMPT --}}
    <div class="mb-3">
        <label class="text-xs font-medium mb-1 block" style="color:rgba(255,255,255,0.7)">
            Di cosa vuoi scrivere?
        </label>
        <textarea wire:model="prompt"
                  rows="3"
                  placeholder="Es: Come le PMI italiane possono usare l'AI per ridurre il tempo nelle riunioni e prendere decisioni piu veloci..."
                  class="w-full px-3 py-2 text-sm rounded-lg border-none outline-none resize-none"
                  style="background:rgba(255,255,255,0.1);color:white"
                  @if($generating) disabled @endif></textarea>
        @error('prompt')
        <p class="text-xs mt-1" style="color:#fca5a5">{{ $message }}</p>
        @enderror
    </div>

    {{-- OPZIONI --}}
    <div class="grid grid-cols-3 gap-2 mb-4">
        <div>
            <label class="text-xs mb-1 block" style="color:rgba(255,255,255,0.7)">Tono</label>
            <select wire:model="tone" class="w-full px-2 py-1 text-xs rounded border-none outline-none" style="background:rgba(255,255,255,0.15);color:white" @if($generating) disabled @endif>
                <option value="professionale">Professionale</option>
                <option value="divulgativo">Divulgativo</option>
                <option value="critico">Critico</option>
                <option value="pratico">Pratico</option>
                <option value="visionario">Visionario</option>
            </select>
        </div>
        <div>
            <label class="text-xs mb-1 block" style="color:rgba(255,255,255,0.7)">Lunghezza</label>
            <select wire:model="length" class="w-full px-2 py-1 text-xs rounded border-none outline-none" style="background:rgba(255,255,255,0.15);color:white" @if($generating) disabled @endif>
                <option value="breve">Breve (~500 p.)</option>
                <option value="medio">Medio (~1000 p.)</option>
                <option value="lungo">Lungo (~1800 p.)</option>
            </select>
        </div>
        <div>
            <label class="text-xs mb-1 block" style="color:rgba(255,255,255,0.7)">Categoria</label>
            <select wire:model="category" class="w-full px-2 py-1 text-xs rounded border-none outline-none" style="background:rgba(255,255,255,0.15);color:white" @if($generating) disabled @endif>
                <option value="Visione">&#10022; Visione</option>
                <option value="Pratica">&#9881; Pratica</option>
            </select>
        </div>
    </div>

    <button wire:click="generate"
            class="w-full py-2 rounded-lg text-sm font-bold transition-all"
            style="background:#E28A53;color:white"
            @if($generating) disabled @endif>
        @if($generating)
        <span class="flex items-center justify-center gap-2">
            <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
            Claude sta scrivendo...
        </span>
        @else
        &#10022; Genera articolo
        @endif
    </button>

    <p class="text-xs mt-2 text-center" style="color:rgba(255,255,255,0.4)">
        Il contenuto generato e modificabile nell'editor
    </p>
</div>
