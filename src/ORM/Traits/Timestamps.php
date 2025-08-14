<?php

declare(strict_types=1);

namespace Marwa\DB\ORM\Traits;

trait Timestamps
{
    protected function touchTimestamps(array &$data): void
    {
        $now = date('Y-m-d H:i:s');
        $data['updated_at'] = $now;
        if (!isset($this->attributes['created_at']) && !isset($data['created_at'])) {
            $data['created_at'] = $now;
        }
    }
}
