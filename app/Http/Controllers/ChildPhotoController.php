<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Child;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChildPhotoController extends Controller
{
    public function show(Request $request, Child $child): StreamedResponse
    {
        abort_unless($child->photo_path && Storage::disk('local')->exists($child->photo_path), 404);
        AuditLog::record($request, 'child.photo_viewed', $child);

        return Storage::disk('local')->response($child->photo_path, 'child-photo', [
            'Content-Disposition' => 'inline; filename="child-photo"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
