<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Certificate extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'student_id',
        'course_id',
        'quiz_attempt_id',
        'code',
        'score',
        'issued_at',
        'certification_name',
        'metadata',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function quizAttempt()
    {
        return $this->belongsTo(QuizAttempt::class, 'quiz_attempt_id');
    }

    /**
     * Genera un codice univoco formato ATH-XXXX-XXXX-XXXX.
     * 80 bit di entropia (base32-friendly), retry su collisione.
     */
    public static function generateCode(): string
    {
        for ($i = 0; $i < 5; $i++) {
            $raw = random_bytes(10);
            $b32 = strtoupper(str_replace(['+', '/', '='], '', base64_encode($raw)));
            $b32 = substr($b32, 0, 12);
            $code = 'ATH-' . substr($b32, 0, 4) . '-' . substr($b32, 4, 4) . '-' . substr($b32, 8, 4);
            if (!self::where('code', $code)->exists()) {
                return $code;
            }
        }
        throw new \RuntimeException('Impossibile generare un codice certificato univoco dopo 5 tentativi.');
    }
}
