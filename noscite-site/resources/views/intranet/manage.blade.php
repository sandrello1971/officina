@extends('layouts.intranet')
@section('title', 'Gestione Strumenti')
@section('content')
<div style="max-width:900px;">
    <h1 style="font-size:1.25rem; font-weight:700; color:#1A1F1F; margin-bottom:24px;">⚙ Gestione Strumenti</h1>

    <div style="background:white; border-radius:12px; padding:24px; margin-bottom:24px;">
        <h3 style="font-weight:700; color:#1A1F1F; margin-bottom:16px;">+ Aggiungi strumento o POC</h3>

        @if(session('success'))
        <div style="padding:10px 14px; background:#E8F5F5; border-radius:8px; color:#3A8C89; font-size:0.875rem; margin-bottom:16px;">
            ✓ {{ session('success') }}
        </div>
        @endif

        <form method="POST" action="/intranet/manage">
            @csrf
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px;">
                <div>
                    <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:4px;">Tipo *</label>
                    <select name="type" required style="width:100%; padding:8px 12px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; outline:none;">
                        <option value="tool">🔧 Strumento</option>
                        <option value="poc">🧪 POC</option>
                        <option value="demo">🎬 Demo</option>
                        <option value="servizio">🏢 Servizio</option>
                        <option value="mvp">🚀 MVP</option>
                    </select>
                </div>
                <div>
                    <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:4px;">Sezione *</label>
                    <input type="text" name="section" placeholder="Es: AI Tools, Microsoft 365..."
                           list="sections-list" required
                           style="width:100%; padding:8px 12px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; outline:none;">
                    <datalist id="sections-list">
                        <option value="AI Tools">
                        <option value="Microsoft 365">
                        <option value="Strumenti Noscite">
                        <option value="POC">
                        <option value="Servizi">
                        <option value="Demo">
                        <option value="MVP">
                    </datalist>
                </div>
                <div>
                    <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:4px;">Icona (emoji)</label>
                    <input type="text" name="icon" placeholder="🔧" value="🔧"
                           style="width:100%; padding:8px 12px; border:1px solid #C8D0D0; border-radius:8px; font-size:1.2rem; outline:none; text-align:center;">
                </div>
                <div>
                    <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:4px;">Nome *</label>
                    <input type="text" name="name" placeholder="Es: Notion" required
                           style="width:100%; padding:8px 12px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; outline:none;">
                </div>
                <div style="grid-column:1/-1;">
                    <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:4px;">Descrizione</label>
                    <textarea name="description" rows="2" placeholder="A cosa serve, quando usarlo..."
                              style="width:100%; padding:8px 12px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; outline:none; resize:none;"></textarea>
                </div>
                <div>
                    <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:4px;">URL *</label>
                    <input type="url" name="url" placeholder="https://..." required
                           style="width:100%; padding:8px 12px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; outline:none;">
                </div>
                <div>
                    <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:4px;">Label link</label>
                    <input type="text" name="label" placeholder="Es: notion.so"
                           style="width:100%; padding:8px 12px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; outline:none;">
                </div>
                <div>
                    <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:4px;">Credenziali (solo POC)</label>
                    <input type="text" name="credentials" placeholder="email · password"
                           style="width:100%; padding:8px 12px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; outline:none;">
                </div>
                <div>
                    <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:4px;">Status (solo POC)</label>
                    <input type="text" name="status" placeholder="LIVE, WIP, BETA..."
                           style="width:100%; padding:8px 12px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; outline:none;">
                </div>
            </div>
            <button type="submit"
                    style="padding:10px 24px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.875rem; font-weight:700; cursor:pointer;">
                + Aggiungi
            </button>
        </form>
    </div>

    <div style="background:white; border-radius:12px; overflow:hidden;">
        <table style="width:100%; border-collapse:collapse;">
            <thead>
                <tr style="background:#F5F7F7;">
                    <th style="padding:10px 14px; text-align:left; font-size:0.75rem; color:#8A9696; text-transform:uppercase;">Strumento</th>
                    <th style="padding:10px 14px; text-align:left; font-size:0.75rem; color:#8A9696; text-transform:uppercase;">Sezione</th>
                    <th style="padding:10px 14px; text-align:left; font-size:0.75rem; color:#8A9696; text-transform:uppercase;">Tipo</th>
                    <th style="padding:10px 14px; text-align:left; font-size:0.75rem; color:#8A9696; text-transform:uppercase;">Stato</th>
                    <th style="padding:10px 14px; text-align:left; font-size:0.75rem; color:#8A9696; text-transform:uppercase;">Azioni</th>
                </tr>
            </thead>
            <tbody>
                @php
                $typeLabels = [
                    'tool'     => ['emoji'=>'🔧', 'label'=>'Strumenti', 'bg'=>'#E8F5F5', 'color'=>'#3A8C89'],
                    'poc'      => ['emoji'=>'🧪', 'label'=>'POC',       'bg'=>'#fff3ec', 'color'=>'#c97a45'],
                    'demo'     => ['emoji'=>'🎬', 'label'=>'Demo',      'bg'=>'#f0e8f5', 'color'=>'#7c3aed'],
                    'servizio' => ['emoji'=>'🏢', 'label'=>'Servizi',   'bg'=>'#e8f0f5', 'color'=>'#1d4ed8'],
                    'mvp'      => ['emoji'=>'🚀', 'label'=>'MVP',       'bg'=>'#f5f0e8', 'color'=>'#b45309'],
                ];
                @endphp
                @foreach($tools as $type => $typeItems)
                @php $tl = $typeLabels[$type] ?? ['emoji'=>'📦','label'=>ucfirst($type),'bg'=>'#F5F7F7','color'=>'#8A9696']; @endphp
                <tr style="background:#F5F7F7;">
                    <td colspan="5" style="padding:10px 16px; font-size:0.8rem; font-weight:700; color:{{ $tl['color'] }};">
                        {{ $tl['emoji'] }} {{ $tl['label'] }} ({{ $typeItems->count() }})
                    </td>
                </tr>
                @foreach($typeItems as $tool)
                <tr style="border-bottom:1px solid #F5F7F7; opacity:{{ $tool->active ? '1' : '0.5' }};">
                    <td style="padding:12px 14px;">
                        <div style="display:flex; align-items:center; gap:8px;">
                            <span>{{ $tool->icon }}</span>
                            <div>
                                <div style="font-weight:600; color:#1A1F1F; font-size:0.875rem;">{{ $tool->name }}</div>
                                <div style="font-size:0.75rem; color:#8A9696;">{{ $tool->url }}</div>
                            </div>
                        </div>
                    </td>
                    <td style="padding:12px 14px; font-size:0.8rem; color:#4A5252;">{{ $tool->section }}</td>
                    <td style="padding:12px 14px;">
                        <span style="font-size:0.75rem; padding:2px 8px; border-radius:4px; font-weight:700;
                            background:{{ $tl['bg'] }}; color:{{ $tl['color'] }};">
                            {{ $tl['emoji'] }} {{ $tl['label'] }}
                        </span>
                    </td>
                    <td style="padding:12px 14px;">
                        <form method="POST" action="/intranet/manage/{{ $tool->id }}/toggle">
                            @csrf
                            <button type="submit" style="padding:3px 10px; border-radius:6px; font-size:0.75rem; cursor:pointer; border:1px solid;
                                background:{{ $tool->active ? '#E8F5F5' : '#F5F7F7' }};
                                color:{{ $tool->active ? '#3A8C89' : '#8A9696' }};
                                border-color:{{ $tool->active ? '#55B1AE' : '#C8D0D0' }};">
                                {{ $tool->active ? '✓ Attivo' : '○ Inattivo' }}
                            </button>
                        </form>
                    </td>
                    <td style="padding:12px 14px;">
                        <div style="display:flex; gap:6px;">
                            <button onclick="const el=document.getElementById('edit-tool-{{ $tool->id }}'); el.style.display=el.style.display==='none'||el.style.display===''?'table-row':'none'"
                                    style="padding:4px 10px; background:#E8F5F5; color:#55B1AE; border:1px solid #55B1AE; border-radius:6px; font-size:0.75rem; cursor:pointer;">
                                ✏ Modifica
                            </button>
                            <form method="POST" action="/intranet/manage/{{ $tool->id }}" onsubmit="return confirm('Eliminare {{ $tool->name }}?')">
                                @csrf @method('DELETE')
                                <button type="submit" style="padding:4px 10px; background:#fff3ec; color:#E28A53; border:1px solid #E28A53; border-radius:6px; font-size:0.75rem; cursor:pointer;">
                                    Elimina
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <tr id="edit-tool-{{ $tool->id }}" style="display:none; background:#F5F7F7;">
                    <td colspan="5" style="padding:16px;">
                        <form method="POST" action="/intranet/manage/{{ $tool->id }}/edit">
                            @csrf @method('PATCH')
                            <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:10px; margin-bottom:12px;">
                                <div>
                                    <label style="font-size:0.75rem; font-weight:600; color:#4A5252; display:block; margin-bottom:4px;">Icona</label>
                                    <input type="text" name="icon" value="{{ $tool->icon }}"
                                           style="width:100%; padding:7px 10px; border:1px solid #C8D0D0; border-radius:6px; font-size:1rem; outline:none; text-align:center;">
                                </div>
                                <div>
                                    <label style="font-size:0.75rem; font-weight:600; color:#4A5252; display:block; margin-bottom:4px;">Nome</label>
                                    <input type="text" name="name" value="{{ $tool->name }}" required
                                           style="width:100%; padding:7px 10px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.8rem; outline:none;">
                                </div>
                                <div>
                                    <label style="font-size:0.75rem; font-weight:600; color:#4A5252; display:block; margin-bottom:4px;">Sezione</label>
                                    <input type="text" name="section" value="{{ $tool->section }}"
                                           list="sections-list"
                                           style="width:100%; padding:7px 10px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.8rem; outline:none;">
                                </div>
                                <div>
                                    <label style="font-size:0.75rem; font-weight:600; color:#4A5252; display:block; margin-bottom:4px;">Tipo</label>
                                    <select name="type" style="width:100%; padding:7px 10px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.8rem; outline:none;">
                                        <option value="tool" {{ $tool->type==='tool'?'selected':'' }}>🔧 Strumento</option>
                                        <option value="poc" {{ $tool->type==='poc'?'selected':'' }}>🧪 POC</option>
                                        <option value="demo" {{ $tool->type==='demo'?'selected':'' }}>🎬 Demo</option>
                                        <option value="servizio" {{ $tool->type==='servizio'?'selected':'' }}>🏢 Servizio</option>
                                        <option value="mvp" {{ $tool->type==='mvp'?'selected':'' }}>🚀 MVP</option>
                                    </select>
                                </div>
                                <div style="grid-column:1/-1;">
                                    <label style="font-size:0.75rem; font-weight:600; color:#4A5252; display:block; margin-bottom:4px;">Descrizione</label>
                                    <textarea name="description" rows="2"
                                              style="width:100%; padding:7px 10px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.8rem; outline:none; resize:none;">{{ $tool->description }}</textarea>
                                </div>
                                <div style="grid-column:span 2;">
                                    <label style="font-size:0.75rem; font-weight:600; color:#4A5252; display:block; margin-bottom:4px;">URL</label>
                                    <input type="url" name="url" value="{{ $tool->url }}" required
                                           style="width:100%; padding:7px 10px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.8rem; outline:none;">
                                </div>
                                <div>
                                    <label style="font-size:0.75rem; font-weight:600; color:#4A5252; display:block; margin-bottom:4px;">Label link</label>
                                    <input type="text" name="label" value="{{ $tool->label }}"
                                           style="width:100%; padding:7px 10px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.8rem; outline:none;">
                                </div>
                                <div>
                                    <label style="font-size:0.75rem; font-weight:600; color:#4A5252; display:block; margin-bottom:4px;">Status (POC)</label>
                                    <input type="text" name="status" value="{{ $tool->status }}" placeholder="LIVE, WIP..."
                                           style="width:100%; padding:7px 10px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.8rem; outline:none;">
                                </div>
                                <div style="grid-column:1/-1;">
                                    <label style="font-size:0.75rem; font-weight:600; color:#4A5252; display:block; margin-bottom:4px;">Credenziali (POC)</label>
                                    <input type="text" name="credentials" value="{{ $tool->credentials }}" placeholder="email · password"
                                           style="width:100%; padding:7px 10px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.8rem; outline:none;">
                                </div>
                            </div>
                            <div style="display:flex; gap:8px;">
                                <button type="submit"
                                        style="padding:7px 20px; background:#55B1AE; color:white; border:none; border-radius:6px; font-size:0.8rem; font-weight:700; cursor:pointer;">
                                    Salva
                                </button>
                                <button type="button"
                                        onclick="document.getElementById('edit-tool-{{ $tool->id }}').style.display='none'"
                                        style="padding:7px 14px; border:1px solid #C8D0D0; color:#4A5252; background:white; border-radius:6px; font-size:0.8rem; cursor:pointer;">
                                    Annulla
                                </button>
                            </div>
                        </form>
                    </td>
                </tr>
                @endforeach
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
