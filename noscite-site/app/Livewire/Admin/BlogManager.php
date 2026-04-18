<?php

namespace App\Livewire\Admin;

use App\Models\BlogPost;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Component;

class BlogManager extends Component
{
    public Collection $posts;
    public bool $showForm = false;
    public bool $editing = false;

    public ?string $postId = null;
    public string $title = '';
    public string $slug = '';
    public string $content = '';
    public string $excerpt = '';
    public string $cover_image_url = '';
    public string $category = '';
    public string $meta_title = '';
    public string $meta_description = '';
    public string $author_name = '';
    public array $tags = [];
    public bool $published = false;

    protected function rules(): array
    {
        $slugUnique = 'unique:blog_posts,slug';
        if ($this->postId) {
            $slugUnique .= ',' . $this->postId;
        }

        return [
            'title' => 'required|min:3|max:255',
            'slug' => ['required', 'max:255', $slugUnique],
            'content' => 'required|min:10',
            'excerpt' => 'nullable|max:500',
            'cover_image_url' => 'nullable|url|max:255',
            'category' => 'nullable|max:100',
            'meta_title' => 'nullable|max:255',
            'meta_description' => 'nullable|max:300',
            'author_name' => 'nullable|max:255',
        ];
    }

    public function mount(): void
    {
        $this->loadPosts();
    }

    private function loadPosts(): void
    {
        $this->posts = BlogPost::orderByDesc('created_at')->get();
    }

    public function updatedTitle(): void
    {
        if (!$this->editing) {
            $this->slug = Str::slug($this->title);
        }
    }

    public function create(): void
    {
        $this->resetForm();
        $this->showForm = true;
        $this->editing = false;
    }

    public function newPost()
    {
        return redirect()->route('admin.blog.new');
    }

    public function editPost(string $postId)
    {
        return redirect()->to("/nosciteadmin/blog/{$postId}/edit");
    }

    public function edit(string $id): void
    {
        $post = BlogPost::findOrFail($id);

        $this->postId = $post->id;
        $this->title = $post->title;
        $this->slug = $post->slug;
        $this->content = $post->content;
        $this->excerpt = $post->excerpt ?? '';
        $this->cover_image_url = $post->cover_image_url ?? '';
        $this->category = $post->category ?? '';
        $this->meta_title = $post->meta_title ?? '';
        $this->meta_description = $post->meta_description ?? '';
        $this->author_name = $post->author_name ?? '';
        $this->tags = $post->tags ?? [];
        $this->published = $post->published;

        $this->showForm = true;
        $this->editing = true;
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'title' => $this->title,
            'slug' => $this->slug,
            'content' => $this->content,
            'excerpt' => $this->excerpt ?: null,
            'cover_image_url' => $this->cover_image_url ?: null,
            'category' => $this->category ?: null,
            'meta_title' => $this->meta_title ?: null,
            'meta_description' => $this->meta_description ?: null,
            'author_name' => $this->author_name ?: null,
            'tags' => $this->tags ?: null,
            'published' => $this->published,
            'published_at' => $this->published ? now() : null,
            'author_id' => auth()->id(),
        ];

        if ($this->postId) {
            $post = BlogPost::findOrFail($this->postId);
            if ($post->published && $this->published) {
                unset($data['published_at']);
            }
            $post->update($data);
        } else {
            BlogPost::create($data);
        }

        $this->resetForm();
        $this->showForm = false;
        $this->loadPosts();
    }

    public function togglePublished(string $id): void
    {
        $post = BlogPost::findOrFail($id);
        $post->update([
            'published' => !$post->published,
            'published_at' => !$post->published ? now() : null,
        ]);
        $this->loadPosts();
    }

    public function delete(string $id): void
    {
        BlogPost::findOrFail($id)->delete();
        $this->loadPosts();
    }

    public function cancel(): void
    {
        $this->resetForm();
        $this->showForm = false;
    }

    private function resetForm(): void
    {
        $this->postId = null;
        $this->title = '';
        $this->slug = '';
        $this->content = '';
        $this->excerpt = '';
        $this->cover_image_url = '';
        $this->category = '';
        $this->meta_title = '';
        $this->meta_description = '';
        $this->author_name = '';
        $this->tags = [];
        $this->published = false;
    }

    public function render()
    {
        return view('livewire.admin.blog-manager');
    }
}
