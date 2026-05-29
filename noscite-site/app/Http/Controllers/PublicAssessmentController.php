<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubmitAssessmentAiActRequest;
use App\Mail\AssessmentReportToLead;
use App\Mail\LeadFallbackToSales;
use App\Services\CrmLeadInboundService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PublicAssessmentController extends Controller
{
    public function show(): View
    {
        return view('public.assessment-ai-act');
    }

    public function submit(SubmitAssessmentAiActRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $scores = $data['scores'];
        $totalScore = $scores['tools'] + $scores['governance'] + $scores['skills']
                    + $scores['processes'] + $scores['compliance'];
        $recommendedCourse = $this->inferCourse($totalScore);

        $leadData = [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'company_name' => $data['company_name'],
            'role' => $data['role'] ?? null,
            'scores' => $scores,
            'notes' => $data['notes'] ?? null,
            'total_score' => $totalScore,
            'recommended_course' => $recommendedCourse,
        ];

        // 1. PDF generation (best-effort)
        $pdfPath = null;
        try {
            $pdf = Pdf::loadView('pdf.assessment-report', ['lead' => $leadData]);
            $tmpName = 'assessment_' . Str::uuid() . '.pdf';
            $pdfPath = storage_path('app/tmp/' . $tmpName);
            if (! is_dir(dirname($pdfPath))) {
                mkdir(dirname($pdfPath), 0775, true);
            }
            $pdf->save($pdfPath);
        } catch (\Throwable $e) {
            Log::error('[Assessment] PDF generation failed', ['error' => $e->getMessage()]);
            $pdfPath = null;
        }

        // 2. Email to lead (best-effort)
        try {
            if ($pdfPath) {
                Mail::to($data['email'])->send(new AssessmentReportToLead($leadData, $pdfPath));
            }
        } catch (\Throwable $e) {
            Log::error('[Assessment] Lead email failed', ['error' => $e->getMessage()]);
        }

        // 3. POST to CRM (best-effort, 2 retries)
        $crmPayload = [
            'first_name' => $leadData['first_name'],
            'last_name' => $leadData['last_name'],
            'email' => $leadData['email'],
            'phone' => $leadData['phone'],
            'company_name' => $leadData['company_name'],
            'role' => $leadData['role'],
            'source' => 'primus_canvas',
            'scores' => $leadData['scores'],
            'notes' => $leadData['notes'],
            'recommended_course' => $leadData['recommended_course'],
        ];

        $crmResult = CrmLeadInboundService::fromConfig()->sendLead($crmPayload);

        // 4. Fallback email if CRM is down — lead must not be lost
        if (! $crmResult['success']) {
            try {
                Mail::to(config('services.crm.fallback_email'))
                    ->send(new LeadFallbackToSales($leadData, $crmResult['error'] ?? 'unknown'));
            } catch (\Throwable $e) {
                Log::critical('[Assessment] FALLBACK EMAIL ALSO FAILED — LEAD DATA POTENTIALLY LOST', [
                    'lead' => $leadData,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // 5. Cleanup temp PDF
        if ($pdfPath && file_exists($pdfPath)) {
            @unlink($pdfPath);
        }

        return redirect()->route('assessment.thanks', [
            'lead' => $crmResult['lead_id'] ?? 'pending',
        ]);
    }

    public function thanks(Request $request): View
    {
        return view('public.assessment-thanks', [
            'leadRef' => $request->query('lead'),
        ]);
    }

    private function inferCourse(int $totalScore): string
    {
        if ($totalScore <= 9) {
            return 'PRIMUS';
        }
        if ($totalScore <= 14) {
            return 'CONSILIUM';
        }

        return 'INITIUM';
    }
}
