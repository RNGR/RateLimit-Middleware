<?php

namespace RNGR\Middleware\RateLimit;

use Slim\Middleware;

class RateLimit extends Middleware
{
    /**
     * Storage Adapter
     *
     * @var StorageInterface
     */
    protected $storage;
    protected $root = '';
    protected $limit = 100;
    protected $period = 3600;
    protected $successCallback;
    protected $failCallback;
    protected $remaining;
    protected $reset;

    public function __construct(StorageInterface $storage, array $options = [])
    {
        $this->storage = $storage;
        $this->setOptions($options);
    }

    private function setOptions(array $options)
    {
        foreach ($options as $option => $value) {
            if (in_array($option, ['root', 'limit', 'period', 'successCallback', 'failCallback'])) {
                $this->{$option} = $value;
            }
        }
    }

    public function call()
    {
        // Activate on given root URL only
        if ($this->checkURL()) {
            $this->checkLimit();
        }

        $this->next->call();
    }

    protected function checkURL()
    {
        return preg_match(
            '|^' . $this->root . '.*|',
            $this->app->request->getResourceUri()
        );
    }

    protected function checkLimit()
    {
        if (!$key = $this->app->config('rate.limit.key')) {
            $key = '_rngr_ratelimit';
        }

        if (!$this->isValid($key)) {
            return $this->fail();
        } else {
            return $this->success();
        }
    }

    protected function isValid($key)
    {
        $data = $this->fetch($key);

        if (!$data) {
            $data = $this->addData($key);
        } else {
            $data = $this->updateData($key, $data);
        }

        if ($data['remaining'] >= 0 || $this->reset <= 0) {
            $valid = true;
        } else {
            $valid = false;
        }

        $this->setHeaders($data);

        return $valid;
    }

    protected function setHeaders($data)
    {
        // Set rating headers
        $this->app->response->headers->set(
            'X-Rate-Limit-Limit',
            $this->limit
        );

        $this->app->response->headers->set(
            'X-Rate-Limit-Reset',
            $this->reset
        );

        $this->app->response->headers->set(
            'X-Rate-Limit-Remaining',
            $this->remaining < 0 ? 0 : $this->remaining
        );
    }

    /**
     * First time or previous period expired,
     * initialize and save a new entry
     * @param $key
     * @param $nextCall
     * @return array
     */
    protected function addData($key, $nextCall = false)
    {
        $this->reset = $this->period;
        $this->remaining = $this->limit;

        if ($nextCall == false) {
            $this->remaining -= 1;
        }

        $data = [
            'remaining' => $this->remaining,
            'created' => time()
        ];

        $this->save($key, $data, $this->reset);

        return $data;
    }

    /**
     * Take the current entry and update it
     * @param $key
     * @param $currentData
     * @return bool
     */
    protected function updateData($key, $currentData)
    {
        $this->remaining = (--$currentData['remaining'] > 0) ? $currentData['remaining'] : -1;
        $this->reset = (($currentData['created'] + $this->period) - time());
        $data = [
            'remaining' => $this->remaining,
            'created' => $currentData['created']
        ];

        if ($this->reset > 0) {
            $this->save($key, $data, $this->reset);
        } else {
            $this->delete($key);
            $data = $this->addData($key, true);
        }

        return $data;
    }

    protected function fetch($key)
    {
        return $this->storage->read($key);
    }

    protected function save($key, $value, $expire = 0)
    {
        $this->storage->put($key, $value, $expire);
    }

    protected function delete($key)
    {
        $this->storage->delete($key);
    }

    public function success()
    {
        if (is_callable($this->successCallback)) {
            return call_user_func_array($this->successCallback, [$this->app]);
        }
    }

    /**
     * Exits with status "429 Too Many Requests"
     *
     * Work around on Apache's issue: it does not support
     * status code 429 until version 2.4
     *
     * @link http://stackoverflow.com/questions/17735514/php-apache-silently-converting-http-429-and-others-to-500
     */
    protected function fail()
    {
        if (is_callable($this->failCallback)) {
            return call_user_func_array($this->failCallback, [$this->app]);
        }

        return $this->defaultFailCallback();
    }

    protected function defaultFailCallback()
    {
        header('HTTP/1.1 429 Too Many Requests', false, 429);

        // Write the remaining headers
        foreach ($this->app->response->headers as $key => $value) {
            header($key . ': ' . $value);
        }
        exit;
    }
}
