<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\Student;
use App\Models\StudentNote;
use Illuminate\Http\Request;

class NoteController extends Controller
{
    public function save(Request $request, Module $module)
    {
        $data = $request->validate([
            'content' => 'nullable|string|max:5000',
            'anchor'  => 'nullable|string|max:50',
        ]);

        $student = Student::find(session('student_id'));
        if ($student && $student->is_demo) {
            return response()->json(['success' => true, 'demo' => true]);
        }

        $anchor = empty($data['anchor']) ? null : $data['anchor'];
        $content = $data['content'] ?? '';

        // Se content vuoto e anchor presente → cancella la nota ancorata (toggle off)
        if ($anchor !== null && trim($content) === '') {
            StudentNote::where('student_id', session('student_id'))
                ->where('module_id', $module->id)
                ->where('anchor', $anchor)
                ->delete();
            return response()->json(['success' => true, 'deleted' => true]);
        }

        $query = StudentNote::where('student_id', session('student_id'))
            ->where('module_id', $module->id);
        if ($anchor === null) {
            $query->whereNull('anchor');
        } else {
            $query->where('anchor', $anchor);
        }
        $note = $query->first();

        if ($note) {
            $note->update(['content' => $content]);
        } else {
            $note = StudentNote::create([
                'student_id' => session('student_id'),
                'module_id'  => $module->id,
                'anchor'     => $anchor,
                'content'    => $content,
            ]);
        }

        return response()->json([
            'success' => true,
            'note' => [
                'id'      => $note->id,
                'anchor'  => $note->anchor,
                'content' => $note->content,
            ],
        ]);
    }

    public function list(Module $module)
    {
        $notes = StudentNote::where('student_id', session('student_id'))
            ->where('module_id', $module->id)
            ->orderByRaw('CASE WHEN anchor IS NULL THEN 0 ELSE 1 END')
            ->orderBy('anchor')
            ->get(['id', 'anchor', 'content', 'updated_at']);

        return response()->json([
            'notes' => $notes->map(fn($n) => [
                'id'         => $n->id,
                'anchor'     => $n->anchor,
                'content'    => $n->content,
                'updated_at' => $n->updated_at?->toIso8601String(),
            ]),
        ]);
    }

    public function delete(StudentNote $note)
    {
        abort_unless($note->student_id === session('student_id'), 403);

        $note->delete();
        return response()->json(['success' => true]);
    }
}
