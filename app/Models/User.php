<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    protected string $guard_name = 'api';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'surname',
        'given_name',
        'education_sector',
        'phone',
        'active',
        'email_verification_code',
        'email_verification_code_expires_at',            
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'email_verification_code_expires_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
    public function userDocument()
    {
        return $this->hasOne(UserDocument::class);
    }

    public function submissions()
    {
        return $this->hasMany(Submission::class);
    }

    public function submissionReviews()
    {
        return $this->hasManyThrough(SubmissionReview::class, SubmissionAssignment::class, 'reviewer_id');
    }

    public function assignedSubmissions()
    {
        return $this->hasManyThrough(Submission::class, SubmissionAssignment::class, 'reviewer_id');
    }
}
