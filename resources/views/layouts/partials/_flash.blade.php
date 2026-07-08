{{-- Messaggi flash condivisi (success / error / warning). Prima duplicati nei
     tre layout; qui un solo punto di verità. --}}
@if(session('success'))
<div style="margin:16px 24px; padding:12px 16px; background:#E8F5F5; border-left:4px solid #55B1AE; border-radius:6px; color:#3A8C89; font-size:0.875rem;">
    &#10003; {{ session('success') }}
</div>
@endif
@if(session('error'))
<div style="margin:16px 24px; padding:12px 16px; background:#FDECE2; border-left:4px solid #E28A53; border-radius:6px; color:#A8521F; font-size:0.875rem;">
    {{ session('error') }}
</div>
@endif
@if(session('warning'))
<div style="margin:16px 24px; padding:12px 16px; background:#FBF3E2; border-left:4px solid #E2A653; border-radius:6px; color:#9A7B2E; font-size:0.875rem;">
    &#9888; {{ session('warning') }}
</div>
@endif
