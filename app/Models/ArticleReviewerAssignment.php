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
        'in_progress_at',
        'refused_at',
        'deadline',
        'deadline_extended_at',
        'status',
        'comment',
        'completed_at',
        'general_recommendation',
        'review_comments',
        'review_files',
        'criteria_scores',

        'draft_criteria_scores',
        'draft_general_recommendation',
        'draft_review_comments',
        'draft_review_files',
        'draft_expires_at',
        'draft_last_saved_at',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'in_progress_at' => 'datetime',
        'refused_at' => 'datetime',
        'deadline' => 'datetime',
        'deadline_extended_at' => 'datetime',
        'completed_at' => 'datetime',
        'review_files' => 'array',
        'draft_review_files' => 'array',
    ];

    protected $attributes = [
        'criteria_scores' => 'json',
        'draft_criteria_scores' => 'json',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(ArticleReviewer::class, 'article_reviewer_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function deadlineExtendedBy()
    {
        return $this->belongsTo(User::class, 'deadline_extended_by');
    }

    public function getWasDeadlineExtendedAttribute()
    {
        return !is_null($this->deadline_extended_at);
    }

    public function getDeadlineExtensionInfoAttribute()
    {
        if (!$this->was_deadline_extended) {
            return null;
        }

        return [
            'extended_at' => $this->deadline_extended_at,
        ];
    }

    public function getInProgressAtAttribute($value)
    {
        return $value ? new \Carbon\Carbon($value) : null;
    }

    public function getRefusedAtAttribute($value)
    {
        return $value ? new \Carbon\Carbon($value) : null;
    }

    public function getCompletedAtAttribute($value)
    {
        return $value ? new \Carbon\Carbon($value) : null;
    }

    public function getStatusChangedAtAttribute()
    {
        switch ($this->status) {
            case 'in_progress':
                return $this->in_progress_at;
            case 'refused':
                return $this->refused_at;
            case 'completed':
                return $this->completed_at;
            default:
                return $this->assigned_at;
        }
    }

    public function getCriteriaScore($criteriaId)
    {
        $scores = $this->criteria_scores ?? [];
        return $scores[$criteriaId] ?? 0;
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

    public function scopeHasDraft($query)
    {
        return $query->whereNotNull('draft_expires_at')
                    ->where('draft_expires_at', '>', now());
    }

    public function scopeDraftExpired($query)
    {
        return $query->whereNotNull('draft_expires_at')
                    ->where('draft_expires_at', '<', now());
    }

    public function getHasDraftAttribute()
    {
        return $this->draft_expires_at && $this->draft_expires_at > now();
    }

    public function getDraftExpiresInAttribute()
    {
        if (!$this->draft_expires_at) return null;
        return $this->draft_expires_at;
    }

    public function clearDraft()
    {
        $this->update([
            'draft_criteria_scores' => null,
            'draft_general_recommendation' => null,
            'draft_review_comments' => null,
            'draft_expires_at' => null,
        ]);
    }

    public function getDraftExpiredAttribute()
    {
        if (!$this->draft_expires_at) return false;
        return $this->draft_expires_at < now();
    }

    public function getDraftTimeLeftAttribute()
    {
        if (!$this->draft_expires_at) return null;

        $diffInHours = $this->draft_expires_at;
        $diffInDays = $this->draft_expires_at;

        if ($diffInHours < 24) {
            return [
                'value' => $diffInHours,
                'unit' => 'hours',
                'text' => $diffInHours . ' часов'
            ];
        } else {
            return [
                'value' => $diffInDays,
                'unit' => 'days',
                'text' => $diffInDays . ' дней'
            ];
        }
    }

    public function getHasValidDraftAttribute()
    {
        return $this->draft_expires_at &&
            $this->draft_expires_at > now() &&
            $this->status === 'in_progress';
    }
}
