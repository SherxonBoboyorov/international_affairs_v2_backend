<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScientificActivity extends Model
{
    protected $table = 'scientific_activities';

    protected $fillable = [
        'title_ru',
        'title_uz',
        'title_en'
    ];
}
