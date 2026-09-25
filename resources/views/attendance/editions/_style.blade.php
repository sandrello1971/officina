{{-- Stili e comportamento comuni alle pagine edizioni (area admin e formatore). --}}
<style>
    .ed-wrap { max-width: 1100px; margin: 0 auto; }
    .ed-back { font-size:0.8rem; color:#55B1AE; text-decoration:none; }
    .ed-h2 { font-size:1.25rem; font-weight:700; color:#1A1F1F; margin:6px 0 2px; }
    .ed-meta { color:#6B7280; font-size:0.85rem; margin-bottom:16px; }
    .ed-card { background:white; border-radius:10px; padding:18px 20px; box-shadow:0 1px 4px rgba(0,0,0,0.06); margin-bottom:16px; }
    .ed-btn { display:inline-flex; align-items:center; gap:6px; padding:8px 14px; border-radius:6px; font-size:0.82rem; font-weight:600; text-decoration:none; border:1px solid #55B1AE; color:#55B1AE; background:white; cursor:pointer; }
    .ed-btn.primary { background:#55B1AE; color:white; }
    .ed-btn.danger { border-color:#B42318; color:#B42318; }
    .ed-btn:disabled { opacity:0.6; cursor:wait; }
    .ed-table { width:100%; border-collapse:collapse; font-size:0.86rem; }
    .ed-table th { text-align:left; color:#6B7280; font-weight:600; border-bottom:2px solid #E5E7EB; padding:8px; }
    .ed-table td { border-bottom:1px solid #F0F2F2; padding:8px; vertical-align:middle; }
    .ed-input { padding:7px 9px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.85rem; }
    .ed-label { display:block; font-size:0.75rem; color:#6B7280; margin-bottom:3px; }
    .ed-tabs { display:flex; gap:4px; border-bottom:2px solid #E5E7EB; margin-bottom:16px; }
    .ed-tab { padding:9px 16px; font-size:0.85rem; font-weight:600; color:#6B7280; text-decoration:none; border-bottom:2px solid transparent; margin-bottom:-2px; }
    .ed-tab.active { color:#3A8C89; border-bottom-color:#55B1AE; }
    .ed-flash { padding:10px 14px; border-radius:6px; font-size:0.85rem; margin-bottom:14px; }
    .ed-cell { text-align:center; font-weight:600; font-size:0.78rem; white-space:nowrap; }
    .ed-cell.P { color:#2B6F6C; } .ed-cell.A { color:#B42318; } .ed-cell.AG { color:#B4691A; } .ed-cell.RU { color:#A05A00; }
    .ed-spin { width:12px; height:12px; border:2px solid currentColor; border-right-color:transparent; border-radius:50%; display:inline-block; animation:ed-spin .7s linear infinite; }
    @keyframes ed-spin { to { transform:rotate(360deg); } }
</style>
<script>
    // Feedback immediato e anti doppio invio su ogni form marcato data-busy.
    document.addEventListener('submit', function (e) {
        const form = e.target.closest('form[data-busy]');
        if (!form) return;
        if (form.dataset.sent) { e.preventDefault(); return; }
        form.dataset.sent = '1';
        form.querySelectorAll('button[type=submit]').forEach(function (b) {
            b.disabled = true;
            b.innerHTML = '<span class="ed-spin"></span> ' + (form.dataset.busy || 'Salvataggio…');
        });
    });
</script>
@if(session('success'))
    <div class="ed-flash" style="background:#E6F4F3; color:#2B6F6C;">{{ session('success') }}</div>
@endif
@if($errors->any())
    <div class="ed-flash" style="background:#FDECEC; color:#8A1F1F;">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
@endif
