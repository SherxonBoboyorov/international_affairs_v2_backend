<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ArticleReviewer extends Model
{
    protected $table = 'article_reviewers';
    protected $fillable = [
        'title',
        'fio',
        'description',
        'file_path',
        'edited_file_path',
        'deadline',
        'status',
        'created_by',
        'original_article_id',
        'original_fio',
        'original_article_file',
        'original_title',
    ];
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deadline' => 'datetime',
    ];
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    public function originalArticle(): BelongsTo
    {
        return $this->belongsTo(ArticleConsideration::class, 'original_article_id');
    }
    public function assignments(): HasMany
    {
        return $this->hasMany(ArticleReviewerAssignment::class, 'article_reviewer_id');
    }
    public function reviewers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'article_reviewer_assignments', 'article_reviewer_id', 'reviewer_id')
            ->withPivot(['assigned_at', 'deadline', 'status', 'comment'])
            ->withTimestamps();
    }
    public function scopeNotAssigned($query)
    {
        return $query->where('status', 'not_assigned');
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
            'not_assigned' => 'Не назначено',
            'assigned' => 'Назначено',
            'in_progress' => 'В работе',
            'overdue' => 'Просрочено',
            'completed' => 'Завершено',
        ];
        return $statuses[$this->status] ?? $this->status;
    }
    public function getActiveFilePath(): ?string
    {
        return $this->edited_file_path ?? $this->file_path;
    }
    public function getActiveFileUrl(): ?string
    {
        $path = $this->getActiveFilePath();
        return $path ? 'https://international-affairs.uz/storage/' . $path : null;
    }
    public function hasEditedFile(): bool
    {
        return !is_null($this->edited_file_path);
    }
}
