# Change log for Abivia\Hydration

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