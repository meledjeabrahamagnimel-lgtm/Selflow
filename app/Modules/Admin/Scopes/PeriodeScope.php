<?php

namespace App\Modules\Admin\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class PeriodeScope implements Scope
{
    protected $dateColumn;

    public function __construct(string $dateColumn)
    {
        $this->dateColumn = $dateColumn;
    }

    /**
     * Appliquer le scope pour filtrer par la période active.
     */
    public function apply(Builder $builder, Model $model)
    {
        if (session()->has('active_periode_debut') && session()->has('active_periode_fin')) {
            $builder->whereBetween(
                $model->getTable() . '.' . $this->dateColumn,
                [session('active_periode_debut'), session('active_periode_fin')]
            );
        }
    }
}
