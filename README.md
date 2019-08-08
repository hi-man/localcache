# LocalCache

[![Build Status](https://secure.travis-ci.org/hi-man/localcache.png)](https://travis-ci.org/hi-man/localcache)

now, let's back to our classroom,
professor is talking about the architecture of `cpu`,
just like the software your are creating，`Redis` is the `L2` cache,
and `Yac` is the `L1` cache for your software,
and `LocalCache` is the cache manager of `Redis` and `Yac`.

so, if your redis server is busying, and your data does not change frequently,
`LocalCache` is the right man you are finding.

> If I have seen further, it is by standing on the shoulders of giants.

thanks to [phpredis](https://github.com/phpredis/phpredis) and [yac](https://github.com/laruence/yac)

# Requirement

- PHP 7.0+
- phpredis
- yac

# Install

```
composer require 'hi-man/localcache'
```

# testing

```
./vendor/bin/phpunit
```

# Methods

## Construct

```php
$lc = new LocalCache(
    '127.0.01',     /* redis host */
    'Yac prefix',   /* yac prefix, empty prefix will disable yac, default value is empty, max length is 20 */
    6379,           /* redis port, default value is 6379 */
    3,              /* redis connection timeout, in seconds, default value is 3 */
    500000,         /* redis retry interval, in microseconds, default value is 500000 */
    3,              /* redis read timeout, default value is 3 */
    3,              /* max retry, default value is 3 */
    0               /* redis reserved, default value is 0 */
);
```

## select

the same as redis command `select`, but not really issue a command request.

```php
$lc->select(
    0 /* redis database index */
);

```

## get

the same as redis command `get`, use yac cache value first, then issue a command request if cache is missing.

```php
$lc->get(
    'key',          /* redis item key */
    'default value' /* default value if the key does not exists */
);

```

## set

the same as redis command `set`, reset yac cache value

```php
$lc->set(
    'key',      /* redis item key */
    'value',    /* value to store */
    3           /* ttl */
    'default value' /* default value if the key does not exists */
);

```

## delete

the same as redis command `delete`, also delete yac cache

```php
$lc->delete(
    'key',      /* redis item key */
);

```

## expire

the same as redis command `expire`, also reset yac cache expire time

```php
$lc->expire(
    'key',      /* redis item key */
    3           /* expire time in seconds */
);

```

## clear

the same as redis command `flushdb`, but flush **all** yac cache

```php
$lc->clear();

```

## setLocalCacheTimeout

set yac cache timeout, **set the right value for your scenario**.
cache invalidation is a big concept to deal with.

```php
$lc->setLocalCacheTimeout(
    'key',      /* redis item key */
    'value',    /* value to store */
    3           /* ttl */
    'default value' /* default value if the key does not exists */
);

```

## getLocalCacheTimeout

get yac cache timeout

```php
$lc->getLocalCacheTimeout();

```
