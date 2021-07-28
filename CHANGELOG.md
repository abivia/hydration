Change log for Abivia\Configurable
====

2.0.0
---
- Drop support for PHP 7.2
- Add parameter and return types
- New major version since some of these may be breaking changes.

1.0.11
---
- Produce a reasonable error when an unexpected scalar is encountered.

1.0.10
---
- Bugfix: Fix in v1.0.9 did not respect prior validation failures.

1.0.9
---
- Bugfix: Failed to call validation on array-cast and simple constructed properties.

1.0.8
---
- Add "array" as a recognized className, allowing selective array conversion.

1.0.7
---
- Make it easier to create associative arrays with untyped (stdClass) elements.

1.0.6
---
- Trap type errors when doing a property assignment.

1.0.5
---
- Fix handling of nested errors when an object was initialized via constructor.

1.0.4
---

- Added `construct` and `constructUnpack` flags to the results from class mapping.
    This allows instantiation of classes via constructor, e.g. DateInterval.

1.0.3
----

- `configurePropertyMap` can now return an array of [property, index] to allow
  mapping to an array.

1.0.2
----

- Allow `configureInitialize()` to receive application context.

1.0.1
----
- Allow array return from `configurableClassMap()`
- Bugfix: catch malformed result from `configurableClassMap()`
- Documentation improvements

1.0.0
----
- Stable release

0.5.2
----

- Generate errors for duplicate keys in associative arrays unless the new
  `allowDups` flag is set in the object returned by `configureClassMap()`.

0.5.1
----

- Remove options argument from `configureInitialize()`: redundant and misleading.

0.5.0
----

- Fix return types in some docblock comments that was confusing Netbeans.
- `configureInitialize()` now accepts references to the source data and options array.

0.4.0
----

- Add: CHANGELOG.md.
- Add: Pass a reference to the parent class via the $options argument.
- Add: Store a copy of the current options in Configurable::$configureOptions.
- Change: error logging from subclasses to make the order of messages more readable.
- Change: Made Configurable::$configureErrors private.
- Update unit tests, implement Gitlab CI.

0.3.0
----

- Add: Error logging and reporting.
- Change: convert $strict parameter in configure() to an option array, retain
  backwards compatibility.
- Update unit tests.

0.2.0
----

- Add: YAML support
- Add: configureClassMap can now return a callable to keys for associative arrays.
- Update unit tests.

0.1.0
----

- Initial release.