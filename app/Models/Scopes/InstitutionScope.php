<?php

namespace App\Models\Scopes;

use App\Models\Institution;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class InstitutionScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();

        if (! $user) {
            return;
        }

        // Jika terautentikasi langsung sebagai Institution (Sanctum token ability emis:sync)
        if ($user instanceof Institution) {
            $builder->where($model->qualifyColumn('institution_id'), $user->id);

            return;
        }

        // Jika user memiliki kolom institution_id dan bukan super-admin
        if ($user instanceof User && isset($user->institution_id) && $user->institution_id !== null) {
            $builder->where($model->qualifyColumn('institution_id'), $user->institution_id);
        }
    }
}
