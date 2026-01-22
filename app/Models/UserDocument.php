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
        'science_field_id',
        'diploma_file',
        'diploma_issued_by',
        'orcid',
        'rejection_reason'
    ];

    public function scienceField(): BelongsTo
    {
        return $this->belongsTo(ScientificActivity::class, 'science_field_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
