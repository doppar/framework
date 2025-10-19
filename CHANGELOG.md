# Release Notes

## v2.9.6-beta.4 - 2025-10-19

* [enhancement] Set proper exception message to response body by [@techmahedy](https://github.com/techmahedy) in https://github.com/doppar/framework/pull/47
* route:list command to display registerd route by [@techmahedy](https://github.com/techmahedy) in https://github.com/doppar/framework/pull/48

## v2.9.6-beta.3 - 2025-10-18

* [SQLite] fix unique constraint + tests by [@rrr63](https://github.com/rrr63) in https://github.com/doppar/framework/pull/43
* Unit testing a nested DTO object with BindPayload and Router attributes, and making the class final by [@techmahedy](https://github.com/techmahedy) in https://github.com/doppar/framework/pull/44
* [feat] Exclude sensitive input fields from being stored in the session by [@techmahedy](https://github.com/techmahedy) in https://github.com/doppar/framework/pull/45
* [feat] before-exception hook for improved exception logging by [@techmahedy](https://github.com/techmahedy) in https://github.com/doppar/framework/pull/46

## v2.9.6-beta.2 - 2025-10-16

* [feat] update BindPayload code and add new Bind() attr:  by [@techmahedy](https://github.com/techmahedy) in https://github.com/doppar/framework/pull/42

## v2.9.6-beta.1 - 2025-10-16

* composer.json "minimum-stability": "stable", to "minimum-stability": "dev" by [@techmahedy](https://github.com/techmahedy) in https://github.com/doppar/framework/pull/41

## 2.9.6-beta - 2025-10-16

### What's Changed

* doppar installer class system requirements by [@techmahedy](https://github.com/techmahedy) in https://github.com/doppar/framework/pull/40

**Full Changelog**: https://github.com/doppar/framework/compare/2.9.5-beta...2.9.6-beta

## 2.9.5-beta - 2025-10-16

### What's Changed

* New command to install a package dynamically by user prompts by [@techmahedy](https://github.com/techmahedy) in https://github.com/doppar/framework/pull/36
* [fix] whereDateTimeBetween() function and console progress and table generation by [@techmahedy](https://github.com/techmahedy) in https://github.com/doppar/framework/pull/37
* [BindPayload] Introduce to bind payload attribute by [@rrr63](https://github.com/rrr63) in https://github.com/doppar/framework/pull/38
* [fix] Upsert issue resolved for SQLite and pgsql driver  by [@techmahedy](https://github.com/techmahedy) in https://github.com/doppar/framework/pull/39

**Full Changelog**: https://github.com/doppar/framework/compare/v2.9.5.6...2.9.5-beta

## v2.9.5.6 - 2025-10-15

* Heartbeat remove from atomic lock: by [@techmahedy](https://github.com/techmahedy) in https://github.com/doppar/framework/pull/26
* [Console] Fix server:stop on Mac by [@rrr63](https://github.com/rrr63) in https://github.com/doppar/framework/pull/27
* error page design improved for dark mode and set error information in… by [@techmahedy](https://github.com/techmahedy) in https://github.com/doppar/framework/pull/29
* [Authenticate] Fix multiple user version verification by [@rrr63](https://github.com/rrr63) in https://github.com/doppar/framework/pull/28
* version check variable non-static to static by [@techmahedy](https://github.com/techmahedy) in https://github.com/doppar/framework/pull/30
* [ErrorHandler] Refactor with Factory & Strategy patterns by [@techmahedy](https://github.com/techmahedy) in https://github.com/doppar/framework/pull/31
* [Database] Add SQLite support by [@rrr63](https://github.com/rrr63) in https://github.com/doppar/framework/pull/12
* [Database] Unit test for missing method by [@rrr63](https://github.com/rrr63) in https://github.com/doppar/framework/pull/33
* [Database] Fix SQLite sqrt() error : use PHP instead of SQL by [@rrr63](https://github.com/rrr63) in https://github.com/doppar/framework/pull/34
* unit test of some model method like original attr dirty attr savemany etc by [@techmahedy](https://github.com/techmahedy) in https://github.com/doppar/framework/pull/35

## v2.9.5.5 - 2025-10-13

* [Atomic Lock] Concurrent-Safe Atomic Locks by [@techmahedy](https://github.com/techmahedy) in https://github.com/doppar/framework/pull/24
* [name:hello:foo] Normalize cache key format to include colon separator  by [@techmahedy](https://github.com/techmahedy) in https://github.com/doppar/framework/pull/25

## v2.9.5.4 - 2025-10-11

* console command make:schedule to make:command by [@techmahedy](https://github.com/techmahedy) in https://github.com/doppar/framework/pull/23

## v2.9.5.3 - 2025-10-10

* database migration fresh and migrate command improved: by [@techmahedy](https://github.com/techmahedy) in https://github.com/doppar/framework/pull/22

## v2.9.5.2 - 2025-10-10

* [Console] Improve server:stop command by [@rrr63](https://github.com/rrr63) in https://github.com/doppar/framework/pull/20
* attr route params path to uri and comment improved in string class: by [@techmahedy](https://github.com/techmahedy) in https://github.com/doppar/framework/pull/21

## v2.9.5.1 - 2025-10-10

* date timezone issue resolve for error page by [@techmahedy](https://github.com/techmahedy) in https://github.com/doppar/framework/pull/19
* [Console] Add a --complete parameter to create a working controller by [@rrr63](https://github.com/rrr63) in https://github.com/doppar/framework/pull/17

## v2.9.5.0 - 2025-10-09

* [Console] Add suffix Controller to each controllers by [@rrr63](https://github.com/rrr63) in https://github.com/doppar/framework/pull/15
* [Package] package update by [@rrr63](https://github.com/rrr63) in https://github.com/doppar/framework/pull/16
* Improve error page UI and functionality by [@techmahedy](https://github.com/techmahedy) in https://github.com/doppar/framework/pull/18

## v2.9.4.9 - 2025-10-08

* fix: routing issue resolve for same origin endpoint without attr base… by [@techmahedy](https://github.com/techmahedy) in https://github.com/doppar/framework/pull/14

## v2.9.4.7 - 2025-10-07

**Full Changelog**: https://github.com/doppar/framework/compare/v2.9.4.6...v2.9.4.7

## v2.9.4.3 - 2025-10-05

**Full Changelog**: https://github.com/doppar/framework/compare/v2.9.4.2...v2.9.4.3

## v2.9.4.2 - 2025-10-05

**Full Changelog**: https://github.com/doppar/framework/compare/v2.9.4.1...v2.9.4.2

## v2.9.4.1 - 2025-10-04

**Full Changelog**: https://github.com/doppar/framework/compare/v2.9.4.0...v2.9.4.1

## v2.9.4.0 - 2025-10-03

**Full Changelog**: https://github.com/doppar/framework/compare/v2.9.3.9...v2.9.4.0

## v2.9.3.9 - 2025-10-03

**Full Changelog**: https://github.com/doppar/framework/compare/v2.9.3.8...v2.9.3.9

## v2.9.3.8 - 2025-10-03

**Full Changelog**: https://github.com/doppar/framework/compare/v2.9.3.7...v2.9.3.8

## v2.9.3.7 - 2025-10-02

**Full Changelog**: https://github.com/doppar/framework/compare/v2.9.3.6...v2.9.3.7

## v2.9.3.6 - 2025-10-02

**Full Changelog**: https://github.com/doppar/framework/compare/v2.9.3.5...v2.9.3.6

## v2.9.3.5 - 2025-10-02

**Full Changelog**: https://github.com/doppar/framework/compare/v2.9.3.4...v2.9.3.5

## v2.9.3.4 - 2025-10-01

**Full Changelog**: https://github.com/doppar/framework/compare/v2.9.3.3...v2.9.3.4

## v2.9.3.3 - 2025-09-28

**Full Changelog**: https://github.com/doppar/framework/compare/v2.9.3.2...v2.9.3.3

## v2.9.3.2 - 2025-09-26

**Full Changelog**: https://github.com/doppar/framework/compare/v2.9.3.1...v2.9.3.2

## v2.9.3.1 - 2025-09-26

**Full Changelog**: https://github.com/doppar/framework/compare/v2.9.3.0...v2.9.3.1

## v2.9.3.0 - 2025-09-24

**Full Changelog**: https://github.com/doppar/framework/compare/v2.9.2.9...v2.9.3.0

## v2.9.2.9 - 2025-09-20

**Full Changelog**: https://github.com/doppar/framework/compare/v2.9.2.8...v2.9.2.9

## v2.9.2.8 - 2025-09-19

**Full Changelog**: https://github.com/doppar/framework/compare/v2.9.2.7...v2.9.2.8

## v2.9.2.7 - 2025-09-18

**Full Changelog**: https://github.com/doppar/framework/compare/v2.9.2.6...v2.9.2.7

## v2.9.2.6 - 2025-09-18

**Full Changelog**: https://github.com/doppar/framework/compare/v2.9.2.5...v2.9.2.6

## v2.9.2.5 - 2025-09-17

**Full Changelog**: https://github.com/doppar/framework/compare/v2.9.2.4...v2.9.2.5

## v2.9.2.4 - 2025-09-15

**Full Changelog**: https://github.com/doppar/framework/compare/v2.9.2.3...v2.9.2.4

## v2.9.2.3 - 2025-09-11

**Full Changelog**: https://github.com/doppar/framework/compare/v2.9.2.2...v2.9.2.3

## v2.9.2.2 - 2025-09-10

**Full Changelog**: https://github.com/doppar/framework/compare/v2.9.2.1...v2.9.2.2

## v2.9.2.1 - 2025-09-09

**Full Changelog**: https://github.com/doppar/framework/compare/v2.9.2.0...v2.9.2.1

## v2.9.2.0 - 2025-09-09

**Full Changelog**: https://github.com/doppar/framework/compare/v2.9.1.9...v2.9.2.0

## v2.9.1.9 - 2025-09-08

**Full Changelog**: https://github.com/doppar/framework/compare/v2.9.1.8...v2.9.1.9

## v2.9.1.8 - 2025-09-08

**Full Changelog**: https://github.com/doppar/framework/compare/v2.1.9.7...v2.9.1.8

## v2.9.1.6 - 2025-09-05

**Full Changelog**: https://github.com/doppar/framework/compare/v2.9.1.5...v2.9.1.6

## v2.9.1.5 - 2025-09-05

### What's Changed

* LoginController user query updated. by [@NazmusShakib](https://github.com/NazmusShakib) in https://github.com/doppar/framework/pull/10

### New Contributors

* [@NazmusShakib](https://github.com/NazmusShakib) made their first contribution in https://github.com/doppar/framework/pull/10

**Full Changelog**: https://github.com/doppar/framework/compare/v2.9.1.4...v2.9.1.5

## v2.9.1.4 - 2025-09-04

**Full Changelog**: https://github.com/doppar/framework/compare/v2.9.1.3...v2.9.1.4

## v2.9.1.3 - 2025-09-03

**Full Changelog**: https://github.com/doppar/framework/compare/v2.9.1.2...v2.9.1.3

## v2.9.1.2 - 2025-09-02

**Full Changelog**: https://github.com/doppar/framework/compare/v2.9.1.1...v2.9.1.2

## v2.9.1.0 - 2025-09-01

**Full Changelog**: https://github.com/doppar/framework/compare/v2.9.0.0...v2.9.1.0

## v2.9.0.0 - 2025-08-31

**Full Changelog**: https://github.com/doppar/framework/compare/v2.8.9.9...v2.9.0.0

## v2.8.9.9 - 2025-08-18

**Full Changelog**: https://github.com/doppar/framework/compare/v2.8.9.8...v2.8.9.9

## v2.8.9.8 - 2025-08-16

**Full Changelog**: https://github.com/doppar/framework/compare/v2.8.9.6...v2.8.9.8

## v2.8.9.7 - 2025-08-15

**Full Changelog**: https://github.com/doppar/framework/compare/v2.8.9.6...v2.8.9.7

## v2.8.9.6 - 2025-08-15

**Full Changelog**: https://github.com/doppar/framework/compare/v2.8.9.5...v2.8.9.6

## v2.8.9.5 - 2025-08-14

**Full Changelog**: https://github.com/doppar/framework/compare/v2.8.9.4...v2.8.9.5

## v2.8.9.4 - 2025-08-14

**Full Changelog**: https://github.com/doppar/framework/compare/v2.8.9.3...v2.8.9.4

## v2.8.9.3 - 2025-08-13

**Full Changelog**: https://github.com/doppar/framework/compare/v2.8.9.2...v2.8.9.3

## v2.8.9.2 - 2025-08-13

### What's Changed

* fix: output the error message when PDOException by [@rrr63](https://github.com/rrr63) in https://github.com/doppar/framework/pull/9
* fix/feat: Refactor commands by [@rrr63](https://github.com/rrr63) in https://github.com/doppar/framework/pull/7

### New Contributors

* [@rrr63](https://github.com/rrr63) made their first contribution in https://github.com/doppar/framework/pull/9

**Full Changelog**: https://github.com/doppar/framework/compare/v2.8.9.1...v2.8.9.2

## v2.8.9.1 - 2025-08-11

**Full Changelog**: https://github.com/doppar/framework/compare/v2.8.9.0...v2.8.9.1

## v2.8.9.0 - 2025-08-10

**Full Changelog**: https://github.com/doppar/framework/compare/v2.8.8.9...v2.8.9.0

## v2.8.8.9 - 2025-08-10

**Full Changelog**: https://github.com/doppar/framework/compare/v2.8.8.8...v2.8.8.9

## v2.8.8.8 - 2025-08-10

**Full Changelog**: https://github.com/doppar/framework/compare/v2.8.8.7...v2.8.8.8

## v2.8.8.7 - 2025-08-05

**Full Changelog**: https://github.com/doppar/framework/compare/v2.8.8.6...v2.8.8.7

## v2.8.8.6 - 2025-08-05

**Full Changelog**: https://github.com/doppar/framework/compare/v2.8.8.5...v2.8.8.6

## v2.8.8.3 - 2025-08-05

**Full Changelog**: https://github.com/doppar/framework/compare/v2.8.8.2...v2.8.8.3

## v2.8.8.2 - 2025-08-05

**Full Changelog**: https://github.com/doppar/framework/compare/v2.8.8.1...v2.8.8.2

## v2.8.8.1 - 2025-08-03

**Full Changelog**: https://github.com/doppar/framework/compare/v2.8.8.0...v2.8.8.1

## v2.8.8.0 - 2025-08-03

**Full Changelog**: https://github.com/doppar/framework/compare/v2.8.7.9...v2.8.8.0

## v2.8.7.9 - 2025-08-02

**Full Changelog**: https://github.com/doppar/framework/compare/v2.8.7.8...v2.8.7.9
