<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReviewCriteria extends Model
{
    protected $fillable = [
        'name_ru',
        'name_uz',
        'name_en',
        'max_score',
        'is_active',
        'sort_order',
    ];
    protected $casts = [
        'max_score' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer'
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    public function getFieldNameAttribute()
    {
        return $this->id;
    }

    public function getLocalizedNameAttribute()
    {
        $locale = app()->getLocale();

        switch ($locale) {
            case 'ru':
                return $this->name_ru;
            case 'uz':
                return $this->name_uz;
            default:
                return $this->name_en;
        }
    }
}
