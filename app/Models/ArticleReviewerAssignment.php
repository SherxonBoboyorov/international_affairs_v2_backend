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
        'originality_score',
        'methodology_score',
        'argumentation_score',
        'structure_score',
        'significance_score',
        'general_recommendation',
        'review_comments',
        'review_files',
    ];
    protected $casts = [
        'assigned_at' => 'datetime',
        'deadline' => 'datetime',
        'completed_at' => 'datetime',
        'review_files' => 'array',
        'originality_score' => 'decimal:1',
        'methodology_score' => 'decimal:1',
        'argumentation_score' => 'decimal:1',
        'structure_score' => 'decimal:1',
        'significance_score' => 'decimal:1',
    ];
    public function article(): BelongsTo
    {
        return $this->belongsTo(ArticleReviewer::class, 'article_reviewer_id');
    }
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
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
        ];
        return $statuses[$this->status] ?? $this->status;
    }
}
