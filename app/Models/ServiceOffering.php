<?php

namespace App\Models;

use Database\Factories\ServiceOfferingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceOffering extends Model
{
    /** @use HasFactory<ServiceOfferingFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = ['code', 'name', 'description', 'url', 'page_slug', 'icon_type', 'icon', 'image_url', 'is_enabled', 'maintenance_message', 'sort_order', 'payload_fields'];

    protected $attributes = ['is_enabled' => true, 'sort_order' => 0, 'icon_type' => 'icon', 'icon' => 'bx-store'];

    protected function casts(): array
    {
        return ['is_enabled' => 'boolean', 'sort_order' => 'integer', 'payload_fields' => 'array'];
    }
}
