<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">

    {{-- Messaggi nuovi --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-500">Messaggi nuovi</p>
                <p class="mt-1 text-3xl font-bold text-gray-900">{{ $newMessagesCount }}</p>
            </div>
            <div class="relative">
                <div class="w-12 h-12 bg-red-100 text-red-600 rounded-xl flex items-center justify-center">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                </div>
                @if($newMessagesCount > 0)
                    <span class="absolute -top-1 -right-1 w-5 h-5 bg-red-500 text-white text-xs font-bold rounded-full flex items-center justify-center">
                        {{ $newMessagesCount > 9 ? '9+' : $newMessagesCount }}
                    </span>
                @endif
            </div>
        </div>
    </div>

    {{-- Post pubblicati --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-500">Post pubblicati</p>
                <p class="mt-1 text-3xl font-bold text-gray-900">{{ $blogPostsCount }}</p>
            </div>
            <div class="w-12 h-12 bg-primary-100 text-primary-600 rounded-xl flex items-center justify-center">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/></svg>
            </div>
        </div>
    </div>

    {{-- Iscritti newsletter --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-500">Iscritti newsletter</p>
                <p class="mt-1 text-3xl font-bold text-gray-900">{{ $activeSubscribersCount }}</p>
            </div>
            <div class="w-12 h-12 bg-secondary-100 text-secondary-600 rounded-xl flex items-center justify-center">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
            </div>
        </div>
    </div>

    {{-- Business card attive --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-500">Business card</p>
                <p class="mt-1 text-3xl font-bold text-gray-900">{{ $businessCardsCount }}</p>
            </div>
            <div class="w-12 h-12 bg-purple-100 text-purple-600 rounded-xl flex items-center justify-center">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"/></svg>
            </div>
        </div>
    </div>

</div>
