# Change log for Abivia\Hydration

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