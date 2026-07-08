{{--
    Campi tassonomia per create/edit corso.
    Parametri: $categories, $tags, $selectedCat (uuid|null), $selectedTagIds (array).
--}}
@php $selectedTagIds = collect($selectedTagIds ?? [])->map(fn ($v) => (string) $v)->all(); @endphp

<div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
    <div>
        <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:6px;">Categoria</label>
        <select name="course_category_id"
                style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; outline:none; background:white;">
            <option value="">— Nessuna —</option>
            @foreach($categories as $category)
                <option value="{{ $category->id }}" {{ (string) $selectedCat === (string) $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
            @endforeach
        </select>
        @if($categories->isEmpty())
            <p style="color:#8A9696; font-size:0.72rem; margin-top:4px;">Nessuna categoria: creale in <a href="{{ route('admin.course-categories.index') }}" style="color:#55B1AE;">Categorie corsi</a>.</p>
        @endif
    </div>
    <div>
        <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:6px;">Tag</label>
        @if($tags->isEmpty())
            <p style="color:#8A9696; font-size:0.72rem; margin-top:8px;">Nessun tag: creali in <a href="{{ route('admin.course-tags.index') }}" style="color:#55B1AE;">Tag corsi</a>.</p>
        @else
            <div style="display:flex; flex-wrap:wrap; gap:8px; padding:8px 0;">
                @foreach($tags as $tag)
                    <label style="display:inline-flex; align-items:center; gap:6px; padding:5px 10px; border:1px solid #C8D0D0; border-radius:16px; font-size:0.8rem; color:#4A5252; cursor:pointer; background:{{ in_array((string) $tag->id, $selectedTagIds, true) ? 'rgba(85,177,174,0.12)' : 'white' }};">
                        <input type="checkbox" name="tags[]" value="{{ $tag->id }}" {{ in_array((string) $tag->id, $selectedTagIds, true) ? 'checked' : '' }}>
                        {{ $tag->name }}
                    </label>
                @endforeach
            </div>
        @endif
    </div>
</div>
