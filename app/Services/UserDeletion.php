<?php

namespace App\Services;

use App\Models\Property;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * App-admin removal of an account, either way:
 *
 * - deactivate(): as when users delete their own account (ProfileController):
 *   login blocked, email freed up, everything they did kept.
 * - delete(): really gone. The users foreign keys cascade to everything the
 *   user created (a deleted job takes everyone's sessions, notes and expenses
 *   on it with it), so it's only allowed when none of that is on a property
 *   anyone else belongs to. Properties they're the only member of go too.
 *   Meant for test accounts.
 */
class UserDeletion
{
    /**
     * What a user created that the database deletes with them, as label =>
     * [table, user column, parent table it gets its property from (null when
     * it has its own property_id), that parent's key column].
     */
    private const CREATED = [
        'Jobs' => ['farm_jobs', 'user_id', null, null],
        'Recurring jobs' => ['recurring_jobs', 'created_by', null, null],
        'Work sessions' => ['work_sessions', 'user_id', null, null],
        'Invitations sent' => ['invitations', 'invited_by', null, null],
        'Assets' => ['assets', 'created_by', null, null],
        'Maintenance items' => ['maintenance_items', 'created_by', 'assets', 'asset_id'],
        'Notes' => ['notes', 'created_by', null, null],
        'Expenses' => ['expenses', 'created_by', 'farm_jobs', 'farm_job_id'],
        'Quotes' => ['quotes', 'created_by', 'farm_jobs', 'farm_job_id'],
        'Metrics' => ['metrics', 'created_by', null, null],
        'Checklist templates' => ['checklist_templates', 'created_by', null, null],
        'Checklists' => ['checklists', 'created_by', 'farm_jobs', 'farm_job_id'],
        'Mobs' => ['mobs', 'created_by', null, null],
        'Livestock' => ['livestock', 'created_by', null, null],
    ];

    /**
     * What delete() would remove, or why it can't:
     * {properties: [{id, name}] it deletes, records: {label: count} deleted
     * with the user, blocked_by: [{id, name, records: {label: count}}]
     * shared properties holding their records}.
     */
    public function preview(User $user): array
    {
        $propertyIds = Role::where('user_id', $user->id)->pluck('property_id')->unique();
        $created = $this->createdByProperty($user);

        // Properties someone else belongs to - including ones the user has
        // since left but still has records on.
        $shared = Role::whereIn('property_id', $propertyIds->merge($created->keys()))
            ->where('user_id', '!=', $user->id)
            ->pluck('property_id')
            ->unique();
        $solo = $propertyIds->diff($shared);
        $blocking = $created->filter(fn ($records, $propertyId) => $shared->contains($propertyId));
        $names = Property::whereIn('id', $propertyIds->merge($blocking->keys()))->pluck('name', 'id');

        $records = [];
        foreach ($created as $byLabel) {
            foreach ($byLabel as $label => $count) {
                $records[$label] = ($records[$label] ?? 0) + $count;
            }
        }

        return [
            'properties' => $solo->map(fn ($id) => ['id' => $id, 'name' => $names[$id] ?? "Property {$id}"])->values()->all(),
            'records' => $records,
            'blocked_by' => $blocking->map(fn ($records, $id) => [
                'id' => $id,
                'name' => $names[$id] ?? "Property {$id}",
                'records' => $records,
            ])->values()->all(),
        ];
    }

    /**
     * Deletes the user, their properties no one else belongs to, and their
     * logins. Returns false (and deletes nothing) if it's blocked.
     */
    public function delete(User $user): bool
    {
        $preview = $this->preview($user);
        if ($preview['blocked_by'] !== []) {
            return false;
        }

        DB::transaction(function () use ($user, $preview) {
            $this->logOut($user);
            $avatar = $user->avatar;

            // The user first: users.current_property_id points at these.
            $user->delete();
            Property::whereIn('id', array_column($preview['properties'], 'id'))->get()->each->delete();

            if ($avatar) {
                DB::afterCommit(fn () => Storage::disk(config('filesystems.default'))->delete($avatar));
            }
        });

        return true;
    }

    public function deactivate(User $user): void
    {
        $user->update([
            'email' => $user->email && ! str_ends_with($user->email, '.deleted') ? $user->email.'.deleted' : $user->email,
            'deleted' => true,
        ]);
        $this->logOut($user);
    }

    /**
     * Ends their browser sessions and the mobile app's tokens, neither of
     * which has a foreign key to cascade.
     */
    private function logOut(User $user): void
    {
        $user->tokens()->delete();
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }
    }

    /**
     * @return Collection<int, array<string, int>> property id => {label: count}
     */
    private function createdByProperty(User $user): Collection
    {
        $byProperty = [];

        foreach (self::CREATED as $label => [$table, $column, $parent, $parentKey]) {
            $propertyColumn = $parent ? "{$parent}.property_id" : "{$table}.property_id";

            DB::table($table)
                ->when($parent, fn ($query) => $query->join($parent, "{$parent}.id", '=', "{$table}.{$parentKey}"))
                ->where("{$table}.{$column}", $user->id)
                ->groupBy($propertyColumn)
                ->selectRaw("{$propertyColumn} as property_id, count(*) as aggregate")
                ->get()
                ->each(function ($row) use (&$byProperty, $label) {
                    $byProperty[$row->property_id][$label] = (int) $row->aggregate;
                });
        }

        return collect($byProperty);
    }
}
