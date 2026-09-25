<?php

namespace App\Support;

use App\Models\Course;

/**
 * URL delle pagine edizioni per l'area corrente (admin o formatore): le viste
 * sono condivise e non devono sapere quale prefisso di rotta o quale chiave
 * del corso (id o slug) usa l'area.
 */
class EditionRoutes
{
    public function __construct(
        private string $prefix,
        private Course $course,
        private bool $bySlug,
    ) {}

    public function url(string $action, mixed ...$params): string
    {
        return route("{$this->prefix}.{$action}", [$this->bySlug ? $this->course->slug : $this->course, ...$params]);
    }

    /** Pagina del corso da cui si arriva (per il link "indietro"). */
    public function courseUrl(): string
    {
        return $this->bySlug
            ? route('student.course.show', $this->course->slug)
            : route('admin.courses.show', $this->course);
    }
}
