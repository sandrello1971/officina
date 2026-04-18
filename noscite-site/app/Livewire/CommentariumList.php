<?php

namespace App\Livewire;

use App\Models\BlogPost;
use Livewire\Component;

class CommentariumList extends Component
{
    public string $search = '';
    public string $category = '';

    public function render()
    {
        $query = BlogPost::published()->orderBy('published_at', 'desc');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('title', 'ilike', '%' . $this->search . '%')
                  ->orWhere('excerpt', 'ilike', '%' . $this->search . '%');
            });
        }

        if ($this->category) {
            $query->where('category', $this->category);
        }

        return view('livewire.commentarium-list', [
            'posts' => $query->get(),
        ]);
    }
}
