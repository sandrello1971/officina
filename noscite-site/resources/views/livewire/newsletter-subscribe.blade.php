<div>
    @if($subscribed)
        <div class="flex items-center justify-center gap-2 py-3 px-4 bg-teal-light border border-teal rounded-lg">
            <svg class="h-5 w-5 text-teal" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            <span class="text-sm font-medium text-teal-dark">Iscrizione confermata!</span>
        </div>
    @else
        <form wire:submit.prevent="subscribe" class="flex flex-col sm:flex-row gap-3">
            <div class="flex-1">
                <input wire:model="email" type="email"
                       placeholder="La tua email"
                       class="w-full px-4 py-2.5 bg-[#2a2f2f] border border-mid rounded-lg text-white placeholder-muted focus:ring-2 focus:ring-teal focus:border-teal transition-colors text-body">
                @error('email') <p class="mt-1 text-sm text-orange">{{ $message }}</p> @enderror
            </div>
            <button type="submit"
                    wire:loading.attr="disabled"
                    class="px-6 py-2.5 text-sm font-semibold text-white bg-orange rounded-lg hover:brightness-110 disabled:opacity-50 transition-all whitespace-nowrap">
                <span wire:loading.remove>Iscriviti</span>
                <span wire:loading>...</span>
            </button>
        </form>
    @endif
</div>
