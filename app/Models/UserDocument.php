<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class UserDocument extends Model
{
    protected $table = 'user_documents';

    protected $fillable = [
        'user_id',
        'institutional_phone',
        'academic_degree',
        'work_place',
        'position',
        'science_field',
        'diploma_issued_by',
        'orcid',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getScienceFieldUrlAttribute()
    {
        return $this->science_field_path ? Storage::url($this->science_field_path) : null;
    }
}
