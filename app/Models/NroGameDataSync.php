<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NroGameDataSync extends Model
{
    protected $table = 'nro_game_data_syncs';

    protected $fillable = ['dataset', 'status', 'account_id', 'server_code', 'source_version', 'source_updated_at', 'total', 'processed', 'created', 'updated', 'unchanged', 'total_chunks', 'completed_chunks', 'last_error'];

    protected function casts(): array
    {
        return ['source_updated_at' => 'immutable_datetime', 'completed_chunks' => 'array', 'total' => 'integer', 'processed' => 'integer', 'created' => 'integer', 'updated' => 'integer', 'unchanged' => 'integer', 'total_chunks' => 'integer'];
    }
}
