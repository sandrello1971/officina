<?php

namespace App\Http\Controllers;

use App\Models\IntranetTool;
use Illuminate\Http\Request;

class IntranetController extends Controller
{
    public function index()
    {
        $user = session('intranet_user');
        $tools = IntranetTool::where('type', 'tool')->where('active', true)->orderBy('sort_order')->get();
        $poc = IntranetTool::where('type', 'poc')->where('active', true)->orderBy('sort_order')->get();
        return view('intranet.dashboard', compact('user', 'tools', 'poc'));
    }

    public function tools()
    {
        $user = session('intranet_user');
        $tools = IntranetTool::where('type', 'tool')->where('active', true)->orderBy('sort_order')->get()->groupBy('section');
        return view('intranet.tools', compact('user', 'tools'));
    }

    public function poc()
    {
        $user = session('intranet_user');
        $poc = IntranetTool::where('type', 'poc')->where('active', true)->orderBy('sort_order')->get();
        return view('intranet.poc', compact('user', 'poc'));
    }

    public function manage()
    {
        $user = session('intranet_user');
        if (!($user['is_admin'] ?? false)) abort(403);
        $tools = IntranetTool::orderBy('type')->orderBy('sort_order')->get();
        return view('intranet.manage', compact('user', 'tools'));
    }

    public function store(Request $request)
    {
        $user = session('intranet_user');
        if (!($user['is_admin'] ?? false)) abort(403);

        $data = $request->validate([
            'type' => 'required|in:tool,poc',
            'section' => 'required|string|max:100',
            'icon' => 'nullable|string|max:10',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'url' => 'required|url|max:500',
            'label' => 'nullable|string|max:100',
            'credentials' => 'nullable|string|max:255',
            'status' => 'nullable|string|max:20',
        ]);
        $data['sort_order'] = (IntranetTool::max('sort_order') ?? 0) + 1;
        IntranetTool::create($data);
        return back()->with('success', 'Strumento aggiunto!');
    }

    public function destroy(IntranetTool $tool)
    {
        $user = session('intranet_user');
        if (!($user['is_admin'] ?? false)) abort(403);
        $tool->delete();
        return back()->with('success', 'Strumento rimosso.');
    }

    public function toggle(IntranetTool $tool)
    {
        $user = session('intranet_user');
        if (!($user['is_admin'] ?? false)) abort(403);
        $tool->update(['active' => !$tool->active]);
        return back();
    }
}
