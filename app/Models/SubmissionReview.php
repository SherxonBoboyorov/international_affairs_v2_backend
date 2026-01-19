<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubmissionReview extends Model
{
    protected $table = 'submission_reviews';

    protected $fillable = [
        'submission_id',
        'reviewer_id', 
        'originality_score', 
        'methodology_score', 
        'argumentation_score', 
        'structure_score', 
        'significance_score', 
        'general_recommendation', 
        'comments', 
        'files', 
        'status', 
        'submitted_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'files' => 'array',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}