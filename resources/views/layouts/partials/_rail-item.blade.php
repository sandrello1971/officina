{{--
    Voce nav del rail (link). Parametri:
      $href, $icon, $title (tooltip nativo, il testo non è più visibile),
      $active (bool), $teaching (bool), $badgeId (string, opzionale), $badgeCount (int).
--}}
<a href="{{ $href }}" title="{{ $title }}"
   class="rail-item {{ ($active ?? false) ? 'active' : '' }} {{ ($teaching ?? false) ? 'teaching' : '' }}">
    @include('layouts.partials._icon', ['name' => $icon])
    @if(!empty($badgeId))
        <span id="{{ $badgeId }}" class="rail-badge-count"
              style="display:{{ ($badgeCount ?? 0) > 0 ? 'inline-block' : 'none' }};">{{ $badgeCount ?? 0 }}</span>
    @endif
</a>
