<?php

namespace App\Models\Concerns;

/**
 * API clients still read `_id`. MySQL rows use an integer `id`, so responses include both.
 */
trait PresentsDocumentId
{
    public function __get($key)
    {
        if ($key === '_id') {
            return $this->getKey() === null ? null : (string) $this->getKey();
        }

        return parent::__get($key);
    }

    public function __isset($key)
    {
        if ($key === '_id') {
            return $this->getKey() !== null;
        }

        return parent::__isset($key);
    }

    public function toArray()
    {
        $array = parent::toArray();
        $array['_id'] = (string) $this->getKey();

        return $array;
    }
}
