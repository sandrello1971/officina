<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Tenant;
use App\Services\Tenancy\TenantAiUsage;
use App\Services\Tenancy\TenantProvisioner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Gestione degli enti dalla console di piattaforma. */
class TenantController extends Controller
{
    public function index(TenantAiUsage $usage)
    {
        return view('platform.tenants.index', [
            'rows' => $usage->summary(now()->startOfMonth()),
        ]);
    }

    public function create()
    {
        return view('platform.tenants.create', [
            'modules' => $this->selectableModules(false),
            'defaults' => config('modules.default'),
        ]);
    }

    public function store(Request $request, TenantProvisioner $provisioner)
    {
        $data = $this->validated($request, creating: true);

        try {
            $result = $provisioner->provision([
                'name' => $data['name'],
                'base_host' => $data['base_host'],
                'admin_email' => $data['admin_email'],
                'admin_name' => $data['admin_name'] ?? null,
                'modules' => $data['modules'] ?? [],
                'ai_key_mode' => $data['ai_key_mode'],
                'ai_monthly_budget_usd' => $data['ai_monthly_budget_usd'] ?? null,
            ]);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['name' => $e->getMessage()])->withInput();
        }

        Log::info('[platform] ente creato', ['tenant' => $result['tenant']->slug, 'by' => $this->operator($request)]);

        $tenant = $result['tenant'];

        return redirect()->route('platform.tenants.edit', $tenant)
            ->with('credentials', ['email' => $data['admin_email'], 'password' => $result['temp_password']])
            ->with('success', "Ente creato. Ora: record DNS A per {$tenant->adminHost()} e {$tenant->learnHost()} → IP del server, "
                . "poi da root scripts/tenant-host.sh {$tenant->base_host} (nginx + certificato).");
    }

    public function edit(Tenant $tenant)
    {
        return view('platform.tenants.edit', [
            'tenant' => $tenant,
            'modules' => $this->selectableModules($tenant->isPrimary()),
            'admins' => $tenant->run(fn () => Admin::query()->orderBy('name')->get(['id', 'name', 'email', 'is_active'])),
        ]);
    }

    public function update(Request $request, Tenant $tenant, TenantProvisioner $provisioner)
    {
        $data = $this->validated($request, creating: false);

        try {
            $host = TenantProvisioner::normalizeHost($data['base_host']);
            $provisioner->assertAvailable($tenant->slug, $host, $tenant->id);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['base_host' => $e->getMessage()])->withInput();
        }

        if ($tenant->isPrimary() && $data['status'] !== 'active') {
            return back()->withErrors(['status' => "L'ente primario non si sospende dalla console."]);
        }

        $hostChanged = $host !== $tenant->base_host;
        $tenant->update([
            'name' => $data['name'],
            'status' => $data['status'],
            'base_host' => $host,
            'licensed_modules' => TenantProvisioner::sanitizeModules($data['modules'] ?? [], $tenant->isPrimary()),
            'ai_key_mode' => $data['ai_key_mode'],
            'ai_monthly_budget_usd' => $data['ai_monthly_budget_usd'] ?? null,
        ]);
        $tenant->syncDomains();

        Log::info('[platform] ente aggiornato', ['tenant' => $tenant->slug, 'by' => $this->operator($request)]);

        return back()->with('success', 'Ente aggiornato.' . ($hostChanged
            ? " Host cambiato: servono DNS e certificato per {$tenant->adminHost()} e {$tenant->learnHost()}."
            : ''));
    }

    /** Nuova password temporanea per un admin dell'ente (mostrata una sola volta). */
    public function resetAdminPassword(Request $request, Tenant $tenant, string $admin)
    {
        $password = Str::password(16);
        $email = $tenant->run(function () use ($admin, $password) {
            $model = Admin::findOrFail($admin);
            $model->update(['password' => $password, 'is_active' => true]);

            return $model->email;
        });

        Log::warning('[platform] reset password admin ente', ['tenant' => $tenant->slug, 'admin' => $email, 'by' => $this->operator($request)]);

        return back()->with('credentials', ['email' => $email, 'password' => $password]);
    }

    public function destroy(Request $request, Tenant $tenant)
    {
        abort_if($tenant->isPrimary(), 403, "L'ente primario non si elimina.");
        $request->validate(['confirm_slug' => ['required', Rule::in([$tenant->slug])]], [
            'confirm_slug.in' => "Scrivi esattamente lo slug dell'ente per confermare.",
        ]);

        $slug = $tenant->slug;
        $tenant->delete(); // TenantDeleted → DeleteTenantDatabase (drop del DB clonato)
        Log::warning('[platform] ente eliminato', ['tenant' => $slug, 'by' => $this->operator($request)]);

        return redirect()->route('platform.tenants.index')->with('success', "Ente {$slug} eliminato con il suo database.");
    }

    private function validated(Request $request, bool $creating): array
    {
        return $request->validate(array_filter([
            'name' => 'required|string|max:120',
            'base_host' => 'required|string|max:200',
            'admin_email' => $creating ? 'required|email|max:190' : null,
            'admin_name' => $creating ? 'nullable|string|max:120' : null,
            'status' => $creating ? null : ['required', Rule::in(['active', 'suspended'])],
            'modules' => 'array',
            'modules.*' => ['string', Rule::in(array_keys(config('modules.modules')))],
            'ai_key_mode' => ['required', Rule::in([Tenant::AI_KEY_PLATFORM, Tenant::AI_KEY_TENANT, Tenant::AI_KEY_BOTH])],
            'ai_monthly_budget_usd' => 'nullable|numeric|min:0|max:100000',
        ]));
    }

    /** @return array<string,string> */
    private function selectableModules(bool $primary): array
    {
        $modules = config('modules.modules');

        return $primary ? $modules : array_diff_key($modules, array_flip(config('modules.primary_only')));
    }

    private function operator(Request $request): ?string
    {
        return $request->attributes->get('platform_user')?->email;
    }
}
