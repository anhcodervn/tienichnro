<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CodeNotify extends Model
{
    use SoftDeletes;

    protected $table = 'code_notifies';

    protected $fillable = ['type_id', 'code', 'name', 'additional_filters', 'keywords', 'system_key'];

    protected $hidden = ['system_key'];

    protected function casts(): array
    {
        return ['additional_filters' => 'array', 'keywords' => 'array'];
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(TypeNotify::class, 'type_id');
    }
}
