<?php

/**
 * SmartCache
 * @author Alexander Emelyanov
 * @author Rustam Zagirov
 * 
 * @version 0.1
 * 
 * @link https://github.com/Stamm/yii.smart_cache
 */
class SmartCache extends CMemCache
{
    public $useMemcached=true;
    /** Separates the waiting time into equal parts */
    const WAITING_TYPE_LINEAR = 1;
    /** Separates the waiting time on the part of members of a geometric progression */
    const WAITING_TYPE_GEOMETRIC = 2;

    /** @var bool If true - SmartCache not used magic, and work as CMemCache */ 
    public $usualCacheMode = false;
    public $lockItemPrefix = '_smart_lock';
    public $lockLifetime = 60; // Seconds, used for Memcache::set method
    /** @var integer Microseconds, used for usleeep method */
    public $maxWaitingTime = 5000000;
    public $waitingCyclesCount = 100;
    public $waitingType = self::WAITING_TYPE_LINEAR;
    /** @var int Used for geometric waiting type cycles */
    public $waitingTypeDenominator = 2;

    /**
     * Set lock for cache item. Used method - Memcached->add
     * @param string $itemKey
     * @return true, if lock successfully installed, false otherwise
     */
    private function lock($itemKey)
    {
        $lockKey = $itemKey . $this->lockItemPrefix;

        $result = parent::add($lockKey, 1, $this->lockLifetime);

        return $result;
    }

    /**
     * Remove lock for cache item. Used method - Memcached->del
     * @param string $itemKey
     * @return boolean if no error happens during unlocking
     */
    private function unlock($itemKey)
    {
        $lockKey = $itemKey . $this->lockItemPrefix;

        return parent::delete($lockKey, 1, $this->lockLifetime);
    }

    /**
     * Return sleeping time for current waiting cycle
     *
     * @param $maxWaitingTime - max time for waiting
     * @param $waitingCyclesCount - attempts count for data extracting
     * @param $waitingCycleNumber - current waiting cycle number
     * @return int - microseconds, for sleeping
     */
    private function getWaitingCycleDuration($maxWaitingTime, $waitingCyclesCount, $waitingCycleNumber)
    {
        switch ($this->waitingType){
            case self::WAITING_TYPE_LINEAR:{
                return (int)($maxWaitingTime / $waitingCyclesCount);
                break;
            }
            case self::WAITING_TYPE_GEOMETRIC:{
                $q = ((float)$this->waitingTypeDenominator > 1) ? (float)$this->waitingTypeDenominator : 2;
                $n = $waitingCycleNumber;
                return (int)(($maxWaitingTime * (1 - $q) / (1 - pow($q, $waitingCyclesCount))) * pow($q, $n - 1));
                break;
            }
            default:{
                return (int)($maxWaitingTime / $waitingCyclesCount);
                break;
            }
        }
        return 0;
    }

    /**
     * Retrieves a value from cache with a specified key.
     *
     * @param string $itemKey a key identifying the cached value
     * @param integer $maxWaitingTime max waiting time, in microseconds
     * @return mixed the value stored in cache, false if the value is not in the cache, expired or the dependency has changed.
     */
    public function get($itemKey, $maxWaitingTime = null)
    {
        $value = parent::get($itemKey);

        if ($this->usualCacheMode || $value){
            return $value;
        }

        // Try set lock. If successfully - return false for run data extraction from external sources.
        if ($this->lock($itemKey)){
            return false;
        }

        if ( ! (int) $maxWaitingTime ){
            $maxWaitingTime = $this->maxWaitingTime;
        }

        for($i = 1; $i <= $this->waitingCyclesCount; $i++){

            $cycleWaitingTime = $this->getWaitingCycleDuration($maxWaitingTime, $this->waitingCyclesCount, $i);

            usleep($cycleWaitingTime);

            if (($value = parent::get($itemKey)) !== false){
                return $value;
            }
        }

        return false;

    }

    /**
     * Stores a value identified by a key into cache.
     * If the cache already contains such a key, the existing value and
     * expiration time will be replaced with the new ones.
     *
     * @param string $itemKey the key identifying the value to be cached
     * @param mixed $value the value to be cached
     * @param integer $expire the number of seconds in which the cached value will expire. 0 means never expire.
     * @param ICacheDependency $dependency dependency of the cached item. If the dependency changes, the item is labeled invalid.
     * @return boolean true if the value is successfully stored into cache, false otherwise
     */
    public function set($id, $value, $expire=0, $dependency=null)
    {
        try{
            $success = (int)parent::set($id, $value, $expire, $dependency);
        } catch (Exception $e){
            $this->unlock($id);
            throw $e;
        }
        $this->unlock($id);
        return $success;

    }
             
}
