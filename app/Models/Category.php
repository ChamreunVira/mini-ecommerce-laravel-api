<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $guarded = ["id"];

    protected $fillable = ["name", "slug", "description", "status", "image"];

    protected $appends = ["image_url"];

    public function getImageUrlAttribute(): ?string
    {
        return $this->image;
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
