<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TeacherDocumentFolder extends Model
{
    protected $table = 'm_teacher_document_folders';

    protected $fillable = ['parent_id', 'created_by', 'name', 'visibility'];

    /** @return BelongsTo<TeacherDocumentFolder, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<TeacherDocumentFolder, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** @return HasMany<TeacherDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(TeacherDocument::class, 'teacher_document_folder_id');
    }

    /** @return list<TeacherDocumentFolder> */
    public function lineage(): array
    {
        $lineage = [];
        $current = $this;
        while ($current !== null) {
            abort_if(isset($lineage[$current->id]), 409, 'Struktur folder tidak valid.');
            $lineage[$current->id] = $current;
            $current = $current->parent_id === null ? null : $current->parent;
        }

        return array_reverse(array_values($lineage));
    }
}
