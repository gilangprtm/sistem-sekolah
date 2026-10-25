<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $question_key
 * @property array{value: string, format?: 'html'} $answer
 * @property string|null $score
 * @property int|null $graded_by
 * @property Carbon|null $graded_at
 */
class LmsCourseAnswer extends Model
{
    protected $table = 'tr_lms_course_answers';

    protected $fillable = ['course_attempt_id', 'question_key', 'answer', 'answered_at'];

    protected function casts(): array
    {
        return ['answer' => 'array', 'answered_at' => 'datetime', 'score' => 'decimal:2', 'graded_at' => 'datetime'];
    }
}
