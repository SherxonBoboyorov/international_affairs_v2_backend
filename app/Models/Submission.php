// app/Models/Submission.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Submission extends Model
{
    protected $table = 'submissions';
    
    protected $fillable = [
        'title',
        'abstract',
        'keywords',
        'file_path',
        'user_id',
        'status',
        'submitted_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'keywords' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(SubmissionReview::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(SubmissionAssignment::class);
    }
}