<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use Illuminate\View\View;

class BlogController extends Controller
{
    public function index(): View
    {
        $posts = BlogPost::published()
            ->orderByDesc('published_at')
            ->paginate(12);

        return view('commentarium.index', compact('posts'));
    }

    public function show(BlogPost $post): View
    {
        $post->increment('views_count');

        return view('commentarium.show', compact('post'));
    }
}
