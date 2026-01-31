<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArticleReviewerAssignment extends Model
{
     protected $table = 'article_reviewer_assignments';

    protected $fillable = [
        'article_reviewer_id',
        'reviewer_id',
        'assigned_at',
        'deadline',
        'status',
        'comment',
        'completed_at',
        'general_recommendation',
        'review_comments',
        'review_files',
        'criteria_scores',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'deadline' => 'datetime',
        'completed_at' => 'datetime',
        'review_files' => 'array',
    ];

    protected $attributes = [
        'criteria_scores' => 'json',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(ArticleReviewer::class, 'article_reviewer_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function getCriteriaScore($criteriaId)
    {
        $scores = $this->criteria_scores ?? [];
        return $scores[$criteriaId] ?? null;
    }

    public function setCriteriaScore($criteriaId, $score)
    {
        $scores = $this->criteria_scores ?? [];
        $scores[$criteriaId] = $score;
        $this->criteria_scores = $scores;
    }

    public function getCriteriaScoresAttribute($value)
    {
        return json_decode($value, true) ?? [];
    }

    public function setCriteriaScoresAttribute($value)
    {
        $this->attributes['criteria_scores'] = json_encode($value);
    }
    public function scopeAssigned($query)
    {
        return $query->where('status', 'assigned');
    }
    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }
    public function scopeOverdue($query)
    {
        return $query->where('status', 'overdue');
    }
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }
    public function scopeRefused($query)
    {
        return $query->where('status', 'refused');
    }
    public function getIsOverdueAttribute()
    {
        return $this->deadline &&
               $this->deadline < now() &&
               $this->status !== 'completed';
    }
    public function getStatusNameAttribute()
    {
        $statuses = [
            'assigned' => 'Назначено',
            'in_progress' => 'В работе',
            'overdue' => 'Просрочено',
            'completed' => 'Завершено',
            'refused' => 'Отказано',
        ];
        return $statuses[$this->status] ?? $this->status;
    }
}
