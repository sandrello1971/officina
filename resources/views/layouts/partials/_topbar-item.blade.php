{{--
    Voce nav della topbar (link con testo). Parametri:
      $href, $label (testo visibile), $icon (opzionale), $active (bool),
      $badgeId (string, opzionale — id preservato per lo script Reverb), $badgeCount (int).
--}}
<a href="{{ $href }}" class="topbar-item {{ ($active ?? false) ? 'active' : '' }}">
    @isset($icon)
        @include('layouts.partials._icon', ['name' => $icon, 'size' => 18])
    @endisset
    <span>{{ $label }}</span>
    @if(!empty($badgeId))
        <span id="{{ $badgeId }}" class="topbar-badge"
              style="display:{{ ($badgeCount ?? 0) > 0 ? 'inline-flex' : 'none' }};">{{ $badgeCount ?? 0 }}</span>
    @endif
</a>
