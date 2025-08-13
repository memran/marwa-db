<?php

declare(strict_types=1);

namespace Marwa\DB\ORM\Traits;

trait SoftDeletes
{
    public function trashed(): bool
    {
        return !empty($this->attributes['deleted_at']);
    }
}
