# Release Notes

## v0.0.9(2020-11-28)

- yacDeleteHash is a public function now

## v0.0.8(2020-03-27)

- use unlink instead of delete

## v0.0.7(2020-01-26)

- add cache key prefix for commands:
  hdel / hgetall / hmset / hmget / hset / hget / expire / get / set / delete
- ignore yac fails in SET command

## v0.0.6(2019-11-13)

- support hDel / hGetAll / hMSet / hMGet / hSet / hGet

## v0.0.5(2019-09-07)

- support key length greater than 48
- initialize default connection and database index

## v0.0.4(2019-09-03)

- support exception function
- support pool with or without yac

## ~v0.0.3 (2019-08-20)~

- add: support all redis commands
- fix: use the right yac key with prefix

## ~v0.0.2 (2019-08-15)~

- add: `CachePoolService` a cache pool identified by redis connection name
- add: `CacheService` a cache abstract class use cache pool more productive
- optimized: redis operation more effective

## v0.0.1 (2019-08-09)

- add: `LocalCache` class provide a local cache between application and redis server
