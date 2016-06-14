<?php

/**
 * Class RedisState
 *
 * This class allows saving, retrieving, deleting, and checking of key/value pairs in Redis.
 */
class RedisState {

    /**
     * Returns the Redis client.
     *
     * @return \Predis\Client
     */
    private function redisClient()
    {
        return new Predis\Client(getenv('REDIS_URL'));
    }

    /**
     * Saves the value in Redis for a given key.
     *
     * @param $key
     * @param $value
     * @return mixed
     */
    public function setRedis($key, $value) {
        return $this->redisClient()->set($key, json_encode($value));
    }

    /**
     * Retrieves the key value from Redis.
     * If the key does not exist, the default value is returned.
     *
     * @param $key
     * @param null $default
     * @return mixed|null
     */
    public function getRedis($key, $default = null) {
        if ($this->redisClient()->exists($key)) {
            $value = json_decode($this->redisClient()->get($key), true);
            return $value;
        } else {
            return $default;
        }
    }

    /**
     * Delete the Redis entry for a given key.
     * @param $key
     * @return int
     */
    public function deleteRedis($key) {
        return $this->redisClient()->del($key);
    }

    /**
     * Check if a key exists in Redis.
     *
     * @param $key
     * @return int
     */
    public function existsRedis($key) {
        return $this->redisClient()->exists($key);
    }
}
