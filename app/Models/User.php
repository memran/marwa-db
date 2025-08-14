<?php

namespace App\Models;

use Marwa\DB\ORM\Model;

class User extends Model
{
    protected static ?string $table = 'user'; // if null, it will be inferred
    protected static array $fillable = ['name'];
    protected static array $guarded  = ['*'];
}
