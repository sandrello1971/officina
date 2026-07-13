<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use App\Models\Student;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Registra su audit_logs ogni richiesta MUTANTE (non-GET) nelle aree /admin e
 * /docente: chi (attore da sessione), cosa (route/metodo/path), su cosa (parametri
 * di rotta), esito (status), IP, user-agent. NON registra MAI il body della
 * richiesta (niente password/token/PII). Registrato una sola volta in
 * bootstrap/app.php (web append); si auto-filtra, così non tocca i gruppi rotte.
 */
class AuditTrail
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Non deve MAI far fallire la richiesta.
        try {
            $this->record($request, $response);
        } catch (\Throwable $e) {
            Log::warning('AuditTrail: registrazione fallita: ' . $e->getMessage());
        }

        return $response;
    }

    private function record(Request $request, Response $response): void
    {
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return;
        }

        $path = $request->path();
        $area = $this->area($path);
        if ($area === null) {
            return;
        }

        [$actorType, $actorId, $actorLabel] = $this->actor($area);
        if ($actorType === null) {
            return; // non autenticato (es. POST /admin/login fallito): niente attore, si salta
        }

        $params = $this->scalarParams($request);
        $subjectType = array_key_first($params);
        $subjectId = $subjectType !== null ? (string) $params[$subjectType] : null;

        AuditLog::create([
            'area'         => $area,
            'actor_type'   => $actorType,
            'actor_id'     => $actorId,
            'actor_label'  => $actorLabel,
            'action'       => $request->route()?->getName() ?? strtolower($request->method()) . ' /' . $path,
            'method'       => $request->method(),
            'path'         => '/' . $path,
            'status'       => $response->getStatusCode(),
            'subject_type' => $subjectType,
            'subject_id'   => $subjectId,
            'ip'           => $request->ip(),
            'user_agent'   => mb_substr((string) $request->userAgent(), 0, 255) ?: null,
            'meta'         => $params ?: null,
            'created_at'   => now(),
        ]);
    }

    private function area(string $path): ?string
    {
        if ($path === 'admin' || str_starts_with($path, 'admin/')) {
            return 'admin';
        }
        if ($path === 'docente' || str_starts_with($path, 'docente/')) {
            return 'docente';
        }

        return null;
    }

    /** @return array{0:?string,1:?string,2:?string} [actor_type, actor_id, actor_label] */
    private function actor(string $area): array
    {
        if ($area === 'admin' && session('admin_logged_in')) {
            return ['admin', null, session('admin_email')];
        }

        $studentId = session('student_id');
        if ($studentId) {
            $email = Student::where('id', $studentId)->value('email');
            return ['student', $studentId, $email];
        }

        return [null, null, null];
    }

    /**
     * Solo i parametri di ROTTA (id), scalarizzati. Mai il body della richiesta:
     * evita di persistere password, token o PII.
     *
     * @return array<string, string>
     */
    private function scalarParams(Request $request): array
    {
        $out = [];
        foreach ($request->route()?->parameters() ?? [] as $name => $value) {
            if ($value instanceof Model) {
                $out[$name] = (string) $value->getKey();
            } elseif (is_scalar($value)) {
                $out[$name] = (string) $value;
            }
        }

        return $out;
    }
}
