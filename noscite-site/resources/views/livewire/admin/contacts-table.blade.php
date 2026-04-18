<div>
    {{-- Header + Filtro --}}
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
        <h2 class="text-lg font-semibold text-gray-900">Messaggi di contatto</h2>
        <div class="flex items-center gap-2">
            <label class="text-sm text-gray-500">Filtra:</label>
            <select wire:model.live="statusFilter"
                    class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                <option value="">Tutti</option>
                <option value="new">Nuovi</option>
                <option value="read">Letti</option>
                <option value="replied">Risposti</option>
            </select>
        </div>
    </div>

    {{-- Tabella --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        @if($messages->isEmpty())
            <div class="p-12 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                <p class="mt-3 text-sm text-gray-500">Nessun messaggio{{ $statusFilter ? ' con questo filtro' : '' }}.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-gray-600">Nome</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-600 hidden md:table-cell">Email</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-600 hidden lg:table-cell">Azienda</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-600 hidden lg:table-cell">Data</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-600">Stato</th>
                            <th class="px-4 py-3 text-right font-medium text-gray-600">Azioni</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($messages as $msg)
                            <tr class="hover:bg-gray-50 transition-colors {{ $msg->status === 'new' ? 'bg-primary-50/30' : '' }}">
                                <td class="px-4 py-3">
                                    <div>
                                        <p class="font-medium text-gray-900 {{ $msg->status === 'new' ? 'font-semibold' : '' }}">{{ $msg->name }}</p>
                                        <p class="text-xs text-gray-400 mt-0.5 truncate max-w-[200px] md:hidden">{{ $msg->email }}</p>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-gray-600 hidden md:table-cell">
                                    <a href="mailto:{{ $msg->email }}" class="hover:text-primary-600 transition-colors">{{ $msg->email }}</a>
                                </td>
                                <td class="px-4 py-3 text-gray-500 hidden lg:table-cell">
                                    {{ $msg->company ?? '—' }}
                                </td>
                                <td class="px-4 py-3 text-gray-500 hidden lg:table-cell">
                                    {{ $msg->created_at->format('d/m/Y H:i') }}
                                </td>
                                <td class="px-4 py-3">
                                    @if($msg->status === 'new')
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-semibold text-blue-700 bg-blue-100 rounded-full">
                                            <span class="w-1.5 h-1.5 bg-blue-500 rounded-full"></span>
                                            Nuovo
                                        </span>
                                    @elseif($msg->status === 'read')
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-semibold text-yellow-700 bg-yellow-100 rounded-full">
                                            <span class="w-1.5 h-1.5 bg-yellow-500 rounded-full"></span>
                                            Letto
                                        </span>
                                    @elseif($msg->status === 'replied')
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-semibold text-green-700 bg-green-100 rounded-full">
                                            <span class="w-1.5 h-1.5 bg-green-500 rounded-full"></span>
                                            Risposto
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-1">
                                        {{-- Dettaglio messaggio (tooltip) --}}
                                        <div x-data="{ open: false }" class="relative">
                                            <button @click="open = !open"
                                                    class="p-1.5 text-gray-400 hover:text-primary-600 rounded-lg hover:bg-primary-50 transition-colors" title="Vedi messaggio">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                            </button>
                                            <div x-show="open" x-cloak @click.outside="open = false"
                                                 class="absolute right-0 top-full mt-1 w-80 bg-white rounded-xl shadow-xl border border-gray-200 p-4 z-50">
                                                <p class="text-xs text-gray-500 mb-1">Messaggio da <strong>{{ $msg->name }}</strong></p>
                                                @if($msg->phone) <p class="text-xs text-gray-500 mb-1">Tel: {{ $msg->phone }}</p> @endif
                                                <hr class="my-2">
                                                <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ $msg->message }}</p>
                                                <hr class="my-2">
                                                <p class="text-xs text-gray-400">IP: {{ $msg->ip_address }} &middot; {{ $msg->created_at->format('d/m/Y H:i') }}</p>
                                            </div>
                                        </div>

                                        @if($msg->status === 'new')
                                            <button wire:click="markAsRead('{{ $msg->id }}')"
                                                    class="p-1.5 text-gray-400 hover:text-yellow-600 rounded-lg hover:bg-yellow-50 transition-colors" title="Segna come letto">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 19v-8.93a2 2 0 01.89-1.664l7-4.666a2 2 0 012.22 0l7 4.666A2 2 0 0121 10.07V19M3 19a2 2 0 002 2h14a2 2 0 002-2M3 19l6.75-4.5M21 19l-6.75-4.5M3 10l6.75 4.5M21 10l-6.75 4.5m0 0l-1.14.76a2 2 0 01-2.22 0l-1.14-.76"/></svg>
                                            </button>
                                        @endif

                                        @if($msg->status !== 'replied')
                                            <button wire:click="markAsReplied('{{ $msg->id }}')"
                                                    class="p-1.5 text-gray-400 hover:text-green-600 rounded-lg hover:bg-green-50 transition-colors" title="Segna come risposto">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
