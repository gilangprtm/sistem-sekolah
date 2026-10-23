<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class StudentAffairsViolationCategory extends Model
{
    protected $table = 'm_student_affairs_violation_categories';

    protected $fillable = ['name', 'name_key'];

    protected $hidden = ['name_key'];

    public static function resolveName(string $name): self
    {
        $name = Str::squish($name);

        return static::query()->firstOrCreate(
            ['name_key' => hash('sha256', Str::lower($name))],
            ['name' => $name],
        );
    }

    /** @return HasMany<StudentViolation, $this> */
    public function violations(): HasMany
    {
        return $this->hasMany(StudentViolation::class, 'student_affairs_violation_category_id');
    }
}
