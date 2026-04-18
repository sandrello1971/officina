@extends('layouts.admin')
@section('title', 'Editor Blog')
@section('page-title', $postId ?? null ? 'Modifica articolo' : 'Nuovo articolo')

@section('content')
<livewire:admin.blog-editor :postId="$postId ?? null" />
@endsection
