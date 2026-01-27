<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ArticleConsideration extends Model
{
    protected $table = 'article_considerations';
    protected $fillable = [
        'fio',
        'workplace',
        'scientific_degree',
        'email',
        'phone',
        'mobile_phone',
        'work_phone',
        'article_title',
        'article_language',
        'article_file',
        'message',
        'status',
    ];
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function reviewers()
    {
        return $this->hasMany(ArticleReviewer::class, 'original_article_id');
    }

    public function articleReviewer(): HasOne
    {
        return $this->hasOne(ArticleReviewer::class, 'original_article_id');
    }
    public function scopeNotAssigned($query)
    {
        return $query->where('status', 'not_assigned');
    }
    public function scopeAppointed($query)
    {
        return $query->where('status', 'appointed');
    }

    public function scopeConverted($query)
    {
        return $query->where('status', 'converted');
    }
}
