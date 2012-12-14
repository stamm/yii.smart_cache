Smart cache
===============

It reduce fallback calls who execute hard-work code when you have many concurrency calls. Therefore keep a system resources.
Smart cache work over memcached.


## Install

It depends on memcached pecl extension, not memcache.

```bash
pecl install memcached
```

Put in config/main.php to component section:

```php
        'smartCache'=>array(
            'class'=>'SmartCache',
            'servers'=>array(
                array(
                    'host'=>'127.0.0.1',
                    'port'=>11211,
                ),
            ),
            'keyPrefix' => 'sc_',
        ),
```

And use it in your code:

```php
$users = Yii::app()->smartCache->get($cacheKey, 100000000);
if ($users !== false) {
        return $users;
}
# ... big sql-query
Yii::app()->smartCache->set($cacheKey, $users, CACHE_TIME);
```
Second argument is max waiting time, in microseconds. By default, 5000000 (5 second).

You may global configure it by key *maxWaitingTime* or change for particular place in code.



## When I need to use this?

When you have a very big query to sql wrapped in cache

For example.

When you have a clear cache. You got 100 request to the page. At this page you have a very hard sql query wrapped by cache.

Instead of executing 100 concurrency sql query to db, it execute only 1 sql query.

Another request waiting for end of this 1 query. When it done, another request immediately get a cached value.




## How it works?

When you haven't a value in cache, it create a lock-value in memcached. And this first request do hard-work code.

At this time another request try to get a value from cache, see a lock-value and go to a sleep mode in cycle (you may choose 2 strategy).

When the first request set a needing value, it delete a lock-value and another request get a cached value.
