# Change log for Abivia\Hydration

## 2.7.0
- Minimum PHP version raised to 8.1. Deprecation notice in PHP 8.4 fixed.

## 2.6.1
- Bugfix: Hydrator::parse() did not handle an empty options array correctly.

## 2.6.0
- Make Hydrator::parse() static and public.

## 2.5.1
- Update gitlab->github.
- Remove gitlab->github mirroring from CI.

## 2.5.0
- Added Hydrator::decode().
- Ongoing code/doc cleanup.
- Stripped out old inline documentation.
- added CONTRIBUTING.md.
- Updated contact email.

## 2.4.0
- Add Property::factory(Closure $fn) to allow for more complex hydration of objects that
don't implement Hydratable.
- Deprecate Property::construct().

## 2.3.1
- Add bool $passProperty argument (default true) to Property::getter() and Property::setter() to disable
passing of the property as an argument to getter/setter methods. Change is backwards compatible.

## 2.3.0
- Properties that have not been bound to a class or a closure (via Property::with())
now use Reflection to see if the property has a class type that implements Hydratable.
If it does, then the property is automatically bound to the class.
- Documentation updates.

## 2.2.2
- Moved applyTransform logic from Encoder to EncoderRule.
- Documentation updates.

## 2.2.1
- Improvements to README

## 2.2.0
- Added required properties - hydration will fail if a required property is missing.
- Doc fixes.

## 2.1.0
- Encoder transform functions accessed via reflection, may now be private/protected.
- Encoder transform functions changed to return transformed value.
- Improvements suggested by vimeo/psalm static analysis.

## 2.0.2
- Bugfix: preprocessing drop commands was an over-optimization. 

## 2.0.1
- Bugfix: Bad args to Encoder::encodeProperty() on internal call.

## 2.0.0
- Modify arguments passed to setter closure to pass the Property instead of just the property
options.
- Add Property::getter() to allow complex retrieval.
- Add Property::getOptions() to retrieve current options.
- Adjust update tests and add new test for "synthetic" properties.

## 1.1.1
- Fix bug and wrong delimiter in Property::encodeWith().

## 1.1.0
- Added encoding capabilities to facilitate converting structures back to user-friendly
configuration files.

## 1.0.5
- Added `options` argument to Hydrator::addProperties() to bulk configure the properties, e.g.
with a common validation method or ignore status.
- Added Property::getClass(). If the associated class is constant, this gets the class name.

## 1.0.4
- Use the options array to pass the current Property into all closures and method calls.

## 1.0.3
- Allow passing of the value for validation into the validation closure by reference.

## 1.0.2
- Use the options array to pass the current Property into Setter methods.

## 1.0.1
- New Hydration methods: addProperties(), getSource(), getTarget(), hasSource(), and hasTarget() 

## 1.0.0
- Initial release
- Replaces Abivia\Configurable
