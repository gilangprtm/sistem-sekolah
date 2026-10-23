<?php

namespace App\Models;

use Database\Factories\CounselingNoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CounselingNote extends Model
{
    /** @use HasFactory<CounselingNoteFactory> */
    use HasFactory;

    protected $table = 'tr_counseling_notes';

    protected $fillable = ['student_id', 'counseling_date', 'content', 'created_by', 'updated_by', 'creator_name', 'updater_name'];

    protected $hidden = ['content'];

    protected function casts(): array
    {
        return ['counseling_date' => 'date:Y-m-d', 'content' => 'encrypted'];
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
