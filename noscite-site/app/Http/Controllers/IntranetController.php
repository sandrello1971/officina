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
        $poc = IntranetTool::whereIn('type', ['poc', 'demo', 'mvp'])->where('active', true)->orderBy('sort_order')->get();
        $services = IntranetTool::where('type', 'servizio')->where('active', true)->orderBy('sort_order')->get();
        $servers = \App\Models\IntranetServer::orderBy('sort_order')->get();
        return view('intranet.dashboard', compact('user', 'tools', 'poc', 'services', 'servers'));
    }

    public function services()
    {
        $user = session('intranet_user');
        $services = IntranetTool::where('type', 'servizio')
            ->where('active', true)
            ->orderBy('sort_order')
            ->get();
        return view('intranet.services', compact('user', 'services'));
    }

    public function tools()
    {
        $user = session('intranet_user');
        $tools = IntranetTool::where('type', 'tool')
            ->where('active', true)
            ->orderBy('sort_order')
            ->get()
            ->groupBy('section');
        return view('intranet.tools', compact('user', 'tools'));
    }

    public function poc()
    {
        $user = session('intranet_user');
        $items = IntranetTool::whereIn('type', ['poc', 'demo', 'mvp'])
            ->where('active', true)
            ->orderBy('type')
            ->orderBy('sort_order')
            ->get()
            ->groupBy('type');
        return view('intranet.poc', compact('user', 'items'));
    }

    public function manage()
    {
        $user = session('intranet_user');
        if (!($user['is_admin'] ?? false)) abort(403);
        $tools = IntranetTool::orderBy('type')
            ->orderBy('sort_order')
            ->get()
            ->groupBy('type');
        return view('intranet.manage', compact('user', 'tools'));
    }

    public function store(Request $request)
    {
        $user = session('intranet_user');
        if (!($user['is_admin'] ?? false)) abort(403);

        $data = $request->validate([
            'type' => 'required|in:tool,poc,demo,servizio,mvp',
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

    public function update(Request $request, IntranetTool $tool)
    {
        $user = session('intranet_user');
        if (!($user['is_admin'] ?? false)) abort(403);

        $data = $request->validate([
            'type' => 'required|in:tool,poc,demo,servizio,mvp',
            'section' => 'required|string|max:100',
            'icon' => 'nullable|string|max:10',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'url' => 'required|url|max:500',
            'label' => 'nullable|string|max:100',
            'credentials' => 'nullable|string|max:255',
            'status' => 'nullable|string|max:20',
        ]);

        $tool->update($data);
        return back()->with('success', 'Strumento aggiornato!');
    }

    public function servers()
    {
        $user = session('intranet_user');
        $servers = \App\Models\IntranetServer::orderBy('sort_order')->get();
        return view('intranet.servers', compact('user', 'servers'));
    }

    public function storeServer(Request $request)
    {
        $user = session('intranet_user');
        if (!($user['is_admin'] ?? false)) abort(403);

        $data = $request->validate([
            'name' => 'required|string|max:100',
            'hostname' => 'nullable|string|max:255',
            'ip_address' => 'nullable|string|max:50',
            'url' => 'nullable|url|max:255',
            'provider' => 'nullable|string|max:50',
            'github_url' => 'nullable|url|max:255',
            'service' => 'nullable|string|max:255',
            'os' => 'nullable|string|max:100',
            'specs' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'status' => 'nullable|in:active,maintenance,offline',
        ]);
        $data['sort_order'] = (\App\Models\IntranetServer::max('sort_order') ?? 0) + 1;
        \App\Models\IntranetServer::create($data);
        return back()->with('success', 'Server aggiunto!');
    }

    public function destroyServer(\App\Models\IntranetServer $server)
    {
        $user = session('intranet_user');
        if (!($user['is_admin'] ?? false)) abort(403);
        $server->delete();
        return back()->with('success', 'Server rimosso.');
    }

    public function updateServer(Request $request, \App\Models\IntranetServer $server)
    {
        $user = session('intranet_user');
        if (!($user['is_admin'] ?? false)) abort(403);

        $data = $request->validate([
            'name' => 'nullable|string|max:100',
            'hostname' => 'nullable|string|max:255',
            'ip_address' => 'nullable|string|max:50',
            'url' => 'nullable|url|max:255',
            'github_url' => 'nullable|url|max:255',
            'provider' => 'nullable|string|max:50',
            'service' => 'nullable|string|max:255',
            'os' => 'nullable|string|max:100',
            'specs' => 'nullable|string|max:255',
            'status' => 'nullable|in:active,maintenance,offline',
            'notes' => 'nullable|string',
        ]);
        $server->update($data);
        return back()->with('success', 'Server aggiornato.');
    }
}
