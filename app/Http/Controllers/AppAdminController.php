<?php

namespace App\Http\Controllers;

use App\Models\User;
use Inertia\Inertia;

class AppAdminController extends Controller
{
    /**
     * A simple, read-only cross-property activity report - not scoped to
     * the current property like everything else in the app, since it's
     * meant to give a whole-app view. Deactivated accounts are excluded;
     * their historical records still count towards whoever else touched
     * them, just not listed as a row here.
     */
    public function index()
    {
        $users = User::where('deleted', false)
            ->withCount(['farmJobs', 'workSessions', 'assets', 'checklists', 'metrics', 'adminProperties'])
            ->orderBy('name')
            ->get();

        return Inertia::render('AppAdmin/Index', [
            'users' => $users,
        ]);
    }
}
