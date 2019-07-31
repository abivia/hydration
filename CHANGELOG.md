Change log for Abivia\Configurable
====

0.5.0
----

- Fix return types in some docblock comments that was confusing Netbeans
- configureInitialize() now accepts references to the source data and options array

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

- Initial release