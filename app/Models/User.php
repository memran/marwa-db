<?php

namespace App\Models;

use Marwa\DB\ORM\Model;

class User extends Model
{
    protected static ?string $table = 'users'; // if null, it will be inferred
    protected static array $fillable = ['name'];
    protected static array $guarded  = ['*'];
}
