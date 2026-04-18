<?php

namespace App\Livewire\Admin;

use App\Models\BlogPost;
use App\Models\BusinessCard;
use App\Models\ContactMessage;
use App\Models\NewsletterSubscription;
use Livewire\Component;

class DashboardStats extends Component
{
    public int $newMessagesCount = 0;
    public int $blogPostsCount = 0;
    public int $activeSubscribersCount = 0;
    public int $businessCardsCount = 0;

    public function mount(): void
    {
        $this->newMessagesCount = ContactMessage::new()->count();
        $this->blogPostsCount = BlogPost::count();
        $this->activeSubscribersCount = NewsletterSubscription::where('active', true)->count();
        $this->businessCardsCount = BusinessCard::count();
    }

    public function render()
    {
        return view('livewire.admin.dashboard-stats');
    }
}
