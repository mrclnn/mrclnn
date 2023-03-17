<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class GalleryPosts extends Model
{
    public $timestamps = false;
    protected $fillable = ['category_id', 'file_name', 'status'];
}
