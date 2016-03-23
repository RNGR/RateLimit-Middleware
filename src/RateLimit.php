<?php

namespace RNGR\Middleware\RateLimit;

use Slim\Middleware;

class RateLimit extends Middleware
{
    /**
     * @var StorageInterface
     */
    protected $storage;
    protected $root = '';
    protected $limit = 100;
    protected $period = 3600;

    public function __construct(StorageInterface $storage, $root = '', $limit = 100, $period = 3600)
    {
        $this->storage = $storage;
        $this->root = $root;
        $this->limit = $limit;
        $this->period = $period;
    }

    public function call()
    {
        $response = $this->app->response;
        $request = $this->app->request;

        // Activate on given root URL only
        if (preg_match(
            '|^' . $this->root . '.*|',
            $request->getResourceUri()
        )) {
            if ($key = $this->app->config('rate.limit.key')) {
                $data = $this->fetch($key);
                if (!$data) {
                    // First time or previous period expired,
                    // initialize and save a new entry
                    $remaining = $this->limit-1;
                    $reset = $this->period;

                    $this->save(
                        $key,
                        array(
                            'remaining' => $remaining,
                            'created' => time()
                        ),
                        $reset
                    );
                } else {
                    // Take the current entry and update it
                    $remaining = (--$data['remaining'] >= 0)
                        ? $data['remaining'] : -1;

                    $reset = (($data['created'] + $this->period) - time());

                    if ($reset > 0) {
                        $this->save(
                            $key,
                            array(
                                'remaining' => $remaining,
                                'created' => $data['created']
                            ),
                            $reset
                        );
                    } else {
                        $this->delete($key);
                    }
                }

                // Set rating headers
                $response->headers->set(
                    'X-Rate-Limit-Limit',
                    $this->limit
                );

                $response->headers->set(
                    'X-Rate-Limit-Reset',
                    $reset
                );
                $response->headers->set(
                    'X-Rate-Limit-Remaining',
                    $remaining
                );

                // Check if the current key is allowed to pass
                if (0 > $remaining) {
                    // Rewrite remaining headers
                    $response->headers->set(
                        'X-Rate-Limit-Remaining',
                        0
                    );

                    // Exits with status "429 Too Many Requests" (see doc below)
                    $this->fail();
                }

            } else {
                // Exits with status "429 Too Many Requests" (see doc below)
                $this->fail();
            }
        }
        $this->next->call();

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
        header('HTTP/1.1 429 Too Many Requests', false, 429);

        // Write the remaining headers
        foreach ($this->app->response->headers as $key => $value) {
            header($key . ': ' . $value);
        }
        exit;
    }
}
