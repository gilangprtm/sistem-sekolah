<?php

namespace App\Models;

use Database\Factories\SubjectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $status
 * @property int $jp_per_class
 * @property string $color
 */
class Subject extends Model
{
    /** @use HasFactory<SubjectFactory> */
    use HasFactory;

    protected $table = 'm_subjects';

    protected $fillable = ['code', 'name', 'status', 'jp_per_class', 'color'];

    protected function casts(): array
    {
        return [
            'jp_per_class' => 'integer',
            'color' => 'string',
        ];
    }

    /** @return HasMany<TeacherSubject, $this> */
    public function teacherSubjects(): HasMany
    {
        return $this->hasMany(TeacherSubject::class);
    }
}
