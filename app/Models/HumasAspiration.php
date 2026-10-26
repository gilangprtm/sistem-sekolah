<?php

namespace App\Models;

use Database\Factories\HumasAspirationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HumasAspiration extends Model
{
    /** @use HasFactory<HumasAspirationFactory> */
    use HasFactory;

    protected $table = 'tr_humas_aspirations';

    protected $fillable = ['student_id', 'content'];

    protected $hidden = ['content'];

    protected function casts(): array
    {
        return ['content' => 'encrypted'];
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
