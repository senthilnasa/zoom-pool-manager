<?php

namespace App\Domain\Auth\Traits;

use App\Domain\Users\Models\User;
use Illuminate\Database\Eloquent\Builder;

trait HasDepartmentScope
{
    /**
     * Scope query to the user's allowed department.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForDepartment(Builder $query, User $user): Builder
    {
        // Unrestricted organizational roles
        if ($user->hasRole(['super_admin', 'it_admin', 'meeting_admin', 'auditor'])) {
            return $query;
        }

        // Department Administrators are strictly scoped to their assigned department
        if ($user->hasRole('dept_admin')) {
            return $query->where('department_id', $user->department_id);
        }

        return $query->where('department_id', $user->department_id);
    }
}
