<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherDocument extends Model
{
    protected $table = 'tr_teacher_documents';

    protected $attributes = ['module' => 'teacher'];

    protected $fillable = [
        'teacher_document_folder_id', 'created_by', 'module', 'name', 'original_name', 'disk', 'path', 'mime_type', 'visibility', 'size',
    ];

    /** @return BelongsTo<TeacherDocumentFolder, $this> */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(TeacherDocumentFolder::class, 'teacher_document_folder_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
