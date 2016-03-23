<?php

namespace RNGR\Middleware\RateLimit;

interface StorageInterface
{
    /**
     * Read an item by key.
     *
     * @param  string  $key
     * @return mixed
     */
    public function read($key);

    /**
     * Put a value in by key.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return void
     */
    public function put($key, $value);

    /**
     * Remove an item by key.
     *
     * @param  string  $key
     * @return bool
     */
    public function delete($key);

    /**
     * Remove all items.
     *
     * @return void
     */
    public function flush();
}
