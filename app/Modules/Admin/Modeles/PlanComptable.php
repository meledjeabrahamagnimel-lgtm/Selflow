<?php

namespace App\Modules\Admin\Modeles;

use Illuminate\Database\Eloquent\Model;

class PlanComptable extends Model
{
    protected $table = 'plan_comptable';

    protected $fillable = [
        'numero',
        'libelle',
    ];
}
