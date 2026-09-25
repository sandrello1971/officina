@php $t = $tenant ?? null; $selected = old('modules', $t?->licensed_modules ?? $defaults ?? []); @endphp
<div class="grid gap-4 sm:grid-cols-2">
    <label class="block text-sm">Nome ente
        <input name="name" value="{{ old('name', $t?->name) }}" required class="mt-1 w-full border rounded px-3 py-2">
    </label>
    <label class="block text-sm">Host base
        <input name="base_host" value="{{ old('base_host', $t?->base_host) }}" required placeholder="ente.officina.effettoglitch.it" class="mt-1 w-full border rounded px-3 py-2 font-mono">
        <span class="text-xs" style="color:#8A9696;">Servito su admin.&lt;host&gt; e learn.&lt;host&gt;. Anche un dominio dell'ente.</span>
    </label>
</div>
<fieldset class="mt-5">
    <legend class="text-sm font-semibold mb-2">Moduli</legend>
    <div class="grid gap-2 sm:grid-cols-2">
        @foreach($modules as $key => $label)
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="modules[]" value="{{ $key }}" @checked(in_array($key, $selected, true))> {{ $label }}
            </label>
        @endforeach
    </div>
</fieldset>
<div class="grid gap-4 sm:grid-cols-2 mt-5">
    <label class="block text-sm">Chiave AI
        <select name="ai_key_mode" class="mt-1 w-full border rounded px-3 py-2">
            @foreach(['platform' => 'Solo chiave di piattaforma', 'both' => "Chiave dell'ente se presente, altrimenti piattaforma", 'tenant' => "Solo chiave dell'ente (BYOK)"] as $v => $l)
                <option value="{{ $v }}" @selected(old('ai_key_mode', $t?->ai_key_mode ?? 'platform') === $v)>{{ $l }}</option>
            @endforeach
        </select>
    </label>
    <label class="block text-sm">Budget mensile chiave piattaforma (USD)
        <input name="ai_monthly_budget_usd" type="number" step="0.01" min="0" value="{{ old('ai_monthly_budget_usd', $t?->ai_monthly_budget_usd) }}" placeholder="nessun limite" class="mt-1 w-full border rounded px-3 py-2">
    </label>
</div>
