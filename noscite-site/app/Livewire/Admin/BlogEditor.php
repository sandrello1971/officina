<?php

namespace App\Livewire\Admin;

use App\Models\BlogPost;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;

class BlogEditor extends Component
{
    use WithFileUploads;

    public ?string $postId = null;
    public string $title = '';
    public string $slug = '';
    public string $content = '';
    public string $excerpt = '';
    public string $category = '';
    public string $meta_title = '';
    public string $meta_description = '';
    public bool $published = false;
    public ?string $published_at = null;
    public string $cover_image_url = '';
    public $cover_image = null;
    public bool $saved = false;

    protected $rules = [
        'title' => 'required|min:3|max:255',
        'slug' => 'required|max:255',
        'content' => 'required',
        'excerpt' => 'nullable|max:500',
        'category' => 'nullable|max:100',
        'meta_title' => 'nullable|max:255',
        'meta_description' => 'nullable|max:300',
        'cover_image' => 'nullable|image|max:5120',
    ];

    public function mount(?string $postId = null)
    {
        if ($postId) {
            $post = BlogPost::findOrFail($postId);
            $this->postId = $postId;
            $this->title = $post->title;
            $this->slug = $post->slug;
            $this->content = $post->content;
            $this->excerpt = $post->excerpt ?? '';
            $this->category = $post->category ?? '';
            $this->meta_title = $post->meta_title ?? '';
            $this->meta_description = $post->meta_description ?? '';
            $this->published = $post->published;
            $this->published_at = $post->published_at?->format('Y-m-d\TH:i');
            $this->cover_image_url = $post->cover_image_url ?? '';
        }
    }

    public function updatedTitle()
    {
        if (!$this->postId) {
            $this->slug = Str::slug($this->title);
        }
    }

    public function updatedCoverImage()
    {
        $this->validate(['cover_image' => 'image|max:5120']);
    }

    public function save()
    {
        $this->validate();

        if ($this->cover_image) {
            $path = $this->cover_image->store('blog', 'public');
            $this->cover_image_url = '/storage/' . $path;
        }

        $data = [
            'title' => $this->title,
            'slug' => $this->slug,
            'content' => $this->content,
            'excerpt' => $this->excerpt ?: null,
            'category' => $this->category ?: null,
            'meta_title' => $this->meta_title ?: null,
            'meta_description' => $this->meta_description ?: null,
            'published' => $this->published,
            'published_at' => $this->published ? ($this->published_at ?: now()) : null,
            'cover_image_url' => $this->cover_image_url ?: null,
            'author_name' => auth()->user()->name,
            'author_id' => auth()->id(),
        ];

        if ($this->postId) {
            BlogPost::findOrFail($this->postId)->update($data);
        } else {
            $post = BlogPost::create($data);
            $this->postId = $post->id;
        }

        $this->saved = true;
        $this->dispatch('post-saved');
    }

    public function render()
    {
        return view('livewire.admin.blog-editor');
    }
}
