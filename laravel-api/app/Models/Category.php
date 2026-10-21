<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    public $timestamps = false;

    protected $fillable = ['name', 'slug', 'description', 'created_at'];

    public function books()
    {
        return $this->hasMany(Book::class);
    }
}
