<?php

namespace App\Http\Middleware;

use App\Models\Appointment;
use App\Models\Child;
use App\Models\Guardian;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCentreChildAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $child = $request->route('child');

        if (is_numeric($child)) {
            $child = Child::query()->findOrFail($child);
        }

        if ($child instanceof Child && ! $user->canAccessChild($child)) {
            abort(404);
        }

        $guardian = $request->route('guardian');
        if (is_numeric($guardian)) {
            $guardian = Guardian::query()->findOrFail($guardian);
        }
        $guardianRouteIsProtected = $request->routeIs('guardians.edit', 'guardians.update') && ! $user->hasPermission('children.manage');
        if ($guardian instanceof Guardian && $user->hasPermission('children.view') && ! $guardianRouteIsProtected
            && ! $guardian->children()->accessibleTo($user)->exists()) {
            abort(404);
        }

        $appointment = $request->route('appointment');

        if (is_numeric($appointment)) {
            $appointment = Appointment::query()->findOrFail($appointment);
        }

        if ($appointment instanceof Appointment && ! $user->canAccessChild($appointment->child)) {
            abort(404);
        }

        return $next($request);
    }
}
