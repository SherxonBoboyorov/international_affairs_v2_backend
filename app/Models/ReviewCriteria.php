<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReviewCriteria extends Model
{
    protected $fillable = [
        'name',
        'name_ru',
        'name_uz',
        'max_score',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'max_score' => 'decimal:2',
        'is_active' => 'boolean',
        'sort_order' => 'integer'
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    public function getFieldNameAttribute()
    {
        return str_replace(' ', '_', strtolower($this->name)) . '_score';
    }
}
