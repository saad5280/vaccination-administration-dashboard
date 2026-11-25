<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class DueRegisterController extends Controller
{
    public function __invoke(): View
    {
        return view('due-register.index');
    }
}
