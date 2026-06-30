<?php

namespace App\Http\Middleware;

use App\Models\Property;
use App\Models\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePropertyRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $propertyId = session('current_property_id');

        if (!$propertyId) {
            return redirect()->route('properties.index')
                ->with('error', 'Please select a property first.');
        }

        $property = Property::find($propertyId);

        if (!$property) {
            return redirect()->route('properties.index')
                ->with('error', 'Property not found.');
        }

        $userRole = $request->user()->roleOn($property);

        if (!in_array($userRole, $roles)) {
            abort(403, 'You do not have permission to perform this action.');
        }

        return $next($request);
    }
}