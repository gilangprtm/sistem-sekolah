<?php

namespace App\Models;

use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Student extends Model
{
    /** @use HasFactory<StudentFactory> */
    use HasFactory;

    protected $table = 'm_students';

    protected $hidden = [
        'photo_path',
    ];

    protected $fillable = [
        'user_id',
        'nis',
        'nisn',
        'tahun_angkatan',
        'photo_path',
        'full_name',
        'gender',
        'birth_place',
        'birth_date',
        'address',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date:Y-m-d',
            'tahun_angkatan' => 'integer',
        ];
    }

    /**
     * The optional Siswa account attached to this profile.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasOne<KantinSaldo, $this> */
    public function kantinSaldo(): HasOne
    {
        return $this->hasOne(KantinSaldo::class, 'student_id');
    }
}
