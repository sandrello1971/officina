<?php

namespace App\Livewire\Admin;

use App\Models\ContactMessage;
use Illuminate\Support\Collection;
use Livewire\Component;

class ContactsTable extends Component
{
    public Collection $messages;
    public string $statusFilter = '';

    public function mount(): void
    {
        $this->loadMessages();
    }

    public function updatedStatusFilter(): void
    {
        $this->loadMessages();
    }

    private function loadMessages(): void
    {
        $query = ContactMessage::orderByDesc('created_at');

        if ($this->statusFilter !== '') {
            $query->where('status', $this->statusFilter);
        }

        $this->messages = $query->get();
    }

    public function markAsRead(string $id): void
    {
        ContactMessage::findOrFail($id)->update(['status' => 'read']);
        $this->loadMessages();
    }

    public function markAsReplied(string $id): void
    {
        ContactMessage::findOrFail($id)->update(['status' => 'replied']);
        $this->loadMessages();
    }

    public function render()
    {
        return view('livewire.admin.contacts-table');
    }
}
