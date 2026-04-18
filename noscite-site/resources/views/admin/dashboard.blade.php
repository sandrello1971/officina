@extends('layouts.admin')
@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')

{{-- Stats --}}
<livewire:admin.dashboard-stats />

{{-- Tabs --}}
<div class="mt-8" x-data="{ activeTab: window.location.hash === '#blog' ? 'blog' : (window.location.hash === '#newsletter' ? 'newsletter' : 'blog') }">

    <div class="border-b border-gray-200 mb-6">
        <nav class="flex gap-6">
            <button @click="activeTab = 'blog'"
                    :class="activeTab === 'blog' ? 'border-primary-600 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    class="pb-3 border-b-2 text-sm font-medium transition-colors">
                Blog Manager
            </button>
            <button @click="activeTab = 'messages'"
                    :class="activeTab === 'messages' ? 'border-primary-600 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    class="pb-3 border-b-2 text-sm font-medium transition-colors">
                Messaggi recenti
            </button>
            <button @click="activeTab = 'newsletter'"
                    :class="activeTab === 'newsletter' ? 'border-primary-600 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    class="pb-3 border-b-2 text-sm font-medium transition-colors" id="newsletter">
                Newsletter
            </button>
        </nav>
    </div>

    {{-- Tab: Blog --}}
    <div x-show="activeTab === 'blog'" x-cloak id="blog">
        <livewire:admin.blog-manager />
    </div>

    {{-- Tab: Messaggi --}}
    <div x-show="activeTab === 'messages'" x-cloak>
        <livewire:admin.contacts-table />
    </div>

    {{-- Tab: Newsletter --}}
    <div x-show="activeTab === 'newsletter'" x-cloak>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Iscritti Newsletter</h3>
            <p class="text-sm text-gray-500">Gestione iscritti newsletter — funzionalita in fase di sviluppo.</p>
        </div>
    </div>
</div>

@endsection
