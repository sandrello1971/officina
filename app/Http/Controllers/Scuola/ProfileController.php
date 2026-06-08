<?php

namespace App\Http\Controllers\Scuola;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Scuola\Concerns\ResolvesSchoolAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

// Anagrafica scuola (nome/tipo/città) + branding white-label (settings json:
// assistant_name, instance_name, logo). Sempre scoped sulla PROPRIA scuola.
class ProfileController extends Controller
{
    use ResolvesSchoolAccess;

    public function edit(): View
    {
        return view('scuola.anagrafica', ['school' => $this->currentSchool()]);
    }

    public function update(Request $request)
    {
        $school = $this->currentSchool();

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:liceo,istituto_tecnico,altro',
            'city' => 'nullable|string|max:255',
            'assistant_name' => 'nullable|string|max:60',
            'instance_name' => 'nullable|string|max:120',
            'logo' => 'nullable|image|mimes:png,jpg,jpeg,svg,webp|max:2048',
            'remove_logo' => 'sometimes|boolean',
        ]);

        // Anagrafica
        $school->name = $data['name'];
        $school->type = $data['type'];
        $school->city = $data['city'] ?? null;

        // Branding (settings json): valore vuoto = eredita il default piattaforma.
        $settings = $school->settings ?? [];
        $settings['assistant_name'] = ($data['assistant_name'] ?? '') ?: null;
        $settings['instance_name'] = ($data['instance_name'] ?? '') ?: null;

        // Logo in storage PRIVATO (servito solo via controller).
        if ($request->hasFile('logo')) {
            if (!empty($settings['logo_path'])) {
                Storage::disk('local')->delete($settings['logo_path']);
            }
            $ext = $request->file('logo')->getClientOriginalExtension() ?: 'png';
            $settings['logo_path'] = $request->file('logo')->storeAs(
                'school-logos/' . $school->id, 'logo.' . $ext, 'local'
            );
        } elseif ($request->boolean('remove_logo') && !empty($settings['logo_path'])) {
            Storage::disk('local')->delete($settings['logo_path']);
            $settings['logo_path'] = null;
        }

        $school->settings = array_filter($settings, fn ($v) => $v !== null);
        $school->save();

        return redirect()->route('scuola.anagrafica.edit')
            ->with('success', 'Anagrafica e branding aggiornati.');
    }
}
