<?php

namespace RNGR\Middleware\RateLimit;


class SessionStorage implements StorageInterface
{
    protected $prefix = '';
    protected $storage;

    public function __construct($prefix = '')
    {
        if (!isset($_SESSION)) {
            throw new RuntimeException('Session storage failed. Session not found.');
        }

        $this->prefix = $prefix;
        if (!array_key_exists($this->prefix, $_SESSION)) {
            $_SESSION[$this->prefix] = [];
        }

        $this->storage = &$_SESSION[$this->prefix];
    }

    /**
     * @inheritDoc
     */
    public function read($key)
    {
        if (isset($this->storage[$key])) {
            return $this->storage[$key];
        }
    }

    /**
     * @inheritDoc
     */
    public function put($key, $value)
    {
        $this->storage[$key] = $value;
    }

    /**
     * @inheritDoc
     */
    public function delete($key)
    {
        unset($this->storage[$key]);
    }

    /**
     * @inheritDoc
     */
    public function flush()
    {
        unset($this->storage);
    }

}
