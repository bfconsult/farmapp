<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\UserDeletion;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AppAdminController extends Controller
{
    /**
     * A simple cross-property activity report - not scoped to the current
     * property like everything else in the app, since it's meant to give a
     * whole-app view. Deactivated accounts are excluded; their historical
     * records still count towards whoever else touched them, just not
     * listed as a row here. Each row can be deactivated or deleted (see
     * UserDeletion). ?deactivated=1 lists the deactivated ones instead,
     * to look at only.
     */
    public function index(Request $request)
    {
        $deactivated = $request->boolean('deactivated');

        $users = User::where('deleted', $deactivated)
            ->withCount(['farmJobs', 'workSessions', 'assets', 'checklists', 'metrics', 'adminProperties'])
            ->orderBy('name')
            ->get();

        return Inertia::render('AppAdmin/Index', [
            'users' => $users,
            'deactivated' => $deactivated,
            'deactivatedCount' => User::where('deleted', true)->count(),
        ]);
    }

    /**
     * What deleting this user would remove, or why it's not allowed.
     */
    public function deletePreview(Request $request, User $user, UserDeletion $deletion)
    {
        abort_if($user->is($request->user()), 403);

        return response()->json($deletion->preview($user));
    }

    public function deactivate(Request $request, User $user, UserDeletion $deletion)
    {
        abort_if($user->is($request->user()), 403);

        $deletion->deactivate($user);

        return redirect()->route('app-admin.report')->with('success', "{$user->name} has been deactivated.");
    }

    public function destroy(Request $request, User $user, UserDeletion $deletion)
    {
        abort_if($user->is($request->user()), 403);

        $request->validate(['confirm_name' => ['required', 'string']]);
        if (trim($request->input('confirm_name')) !== trim($user->name)) {
            return back()->withErrors(['confirm_name' => 'Type the user\'s name exactly to confirm.']);
        }

        $name = $user->name;
        if (! $deletion->delete($user)) {
            return back()->withErrors(['confirm_name' => "{$name} has records on properties other people belong to. Deactivate them instead."]);
        }

        return redirect()->route('app-admin.report')->with('success', "{$name} has been deleted.");
    }
}
