<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(): View
    {
        return view('audit.index');
    }
}
