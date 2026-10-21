<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UploadedFile extends Model
{
    protected $fillable = ['user_id', 'kind', 'name'];
}
