<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $question_key
 * @property array{value: string} $answer
 */
class LmsCourseAnswer extends Model
{
    protected $table = 'tr_lms_course_answers';

    protected $fillable = ['course_attempt_id', 'question_key', 'answer', 'answered_at'];

    protected function casts(): array
    {
        return ['answer' => 'array', 'answered_at' => 'datetime'];
    }
}
