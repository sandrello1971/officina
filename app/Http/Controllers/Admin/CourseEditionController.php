<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Attendance\EditionController;
use App\Models\Course;
use App\Support\EditionRoutes;

/** Edizioni e registro presenze nell'area admin (già protetta da admin.auth). */
class CourseEditionController extends EditionController
{
    protected function authorizeCourse(Course $course): void
    {
    }

    protected function layout(): string
    {
        return 'layouts.admin';
    }

    protected function routes(Course $course): EditionRoutes
    {
        return new EditionRoutes('admin.courses.editions', $course, bySlug: false);
    }

    protected function actor(): ?string
    {
        return session('admin_email');
    }
}
