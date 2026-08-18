<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use YMigVal\LaravelModelCache\HasCachedQueries;
use YMigVal\LaravelModelCache\ModelRelationships;

abstract class BaseCacheableModel extends Model
{
    use HasCachedQueries, ModelRelationships;
}
