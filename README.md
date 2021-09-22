# Abivia\Hydration

[![coverage report](https://gitlab.com/abivia/hydration/badges/main/coverage.svg)](https://gitlab.com/abivia/hydration/-/commits/main) 
[![pipeline status](https://gitlab.com/abivia/hydration/badges/main/pipeline.svg)](https://gitlab.com/abivia/hydration/-/commits/main)

Hydration is designed to make JSON and YAML configuration files more user intuitive
while providing robust validation and smart creation of data structures.

Hydration can simplify configuration files to improve usability. Instead of 
```json
{
  "providers": [
    {
      "name": "myFirstProvider"
    },
    {
      "name": "mySecondProvider"
    }
  ]
}
```
Users can simplify the syntax to
```json
{
  "providers": ["myFirstProvider", "mySecondProvider"]
}
```

Hydration
- Populates complex data structures from user editable JSON or YAML sources.
- Allows your application to validate inputs, including ensuring that required properties
are present.

Encoding (dehydration?) facilities can transform your application data structures into objects for
encoding as JSON/YAML, automatically removing unwanted properties, rearranging properties into a
user-friendly order, removing properties with default values and simplifying redundant constructs
to improve usability. 

If your application
- has configurations with several levels of nesting,
- needs to validate user editable data in configuration files,
- is spending a lot of effort converting the stdClass objects created by `json_decode()` or `yaml_parse()` to 
  your application's class structures, or
- is just using `stdClass` objects for configuration

then Hydration is here to help.

Hydration makes it easy to convert of a set of untyped data structures
returned by decoding JSON or YAML configuration files, into PHP classes.

- create associative arrays of named classes indexed by a property of the
class,
- selectively cast properties to an array,
- validate the source data,
- guard against overwriting of protected properties, and
- map properties from user-friendly names to application-meaningful identifiers.

Hydration is an evolution of `Abivia\Configurable`. Hydration is implemented as a library that uses
reflection to access the objects it constructs. It also offers a fluent interface that is more
modular and easy to set up.

If your classes implement the `Hydratable` interface, then Hydration will use reflection
to automatically connect related objects. This behavior can be customized by
creating `Property` objects for any properties that require special handling,
validation, etc.


## Example

```php
use Abivia\Hydration\Hydrator;

class MyClass {
    private static Hydrator $hydrator;
    protected $bar;
    private $foo;
    
    public function hydrate($config): bool {
        if (!isset(self::$hydrator)) {
            self::$hydrator = Hydrator::make(self::class);
        }
        return self::$hydrator->hydrate($this, $config);
    }
}

$config = json_decode('{"bar": "This is bar", "foo": "This is foo"}');
$myObject = new MyClass();
$myObject->hydrate($config);
print_r($myObject);
```
Will output:
```
MyClass Object
(
    [bar:protected] => This is bar
    [foo:private] => This is foo    
)
```


`Hydration` takes data like this:

```json
{
    "application-name": "MyApp",
    "database": [
        {
            "label": "crm",
            "driver": "mysql",
            "host": "localhost",
            "name": "crm",
            "pass": "secret",
            "port": 3306,
            "user": "admin"
        },
        {
            "label": "geocoder",
            "driver": "mysql",
            "host": "localhost",
            "name": "geo",
            "pass": "secret",
            "port": 3306,
            "user": "admin"
        }
    ]
}
```

and turns it into a class structure like this:

```
Environment Object
(
    [appName] => MyApp
    [database] => Array
        (
            [crm] => DatabaseConfiguration Object
                (
                    [driver:protected] => mysql
                    [host:protected] => localhost
                    [key] => crm
                    [label:protected] => crm
                    [name:protected] => crm
                    [pass:DatabaseConfiguration:private] => secret
                    [port:protected] => 3306
                    [user:protected] => admin
                )

            [geocoder] => DatabaseConfiguration Object
                (
                    [driver:protected] => mysql
                    [host:protected] => localhost
                    [key] => geocoder
                    [label:protected] => geocoder
                    [name:protected] => geo
                    [pass:DatabaseConfiguration:private] => secret
                    [port:protected] => 3306
                    [user:protected] => admin
                )
        )
)
```

## Features

Hydration supports property mapping, gated properties (via block, and ignore methods),
data validation, and data-driven class instantiation. It will map arrays of objects to
associative arrays of PHP classes that are indexed by any unique scalar property in the object.

Loading can be either fault-tolerant or strict. Strict validation can either fail with a
`false` result or by throwing an Exception.

## Installation

`composer require abivia/hydration`

Hydration uses the Symphony parser for YAML.


## Basic Use

- Provide a hydration method that initializes the hydrator.
- If required, customize the hydration process by adding Property definitions.
- Bind the hydrator to the class being hydrated.
- Instantiate the top level class and pass your decoded JSON or YAML to the method.

The default is to associate all public properties in the host class. If a property is typed
with a class that implements the `Hydratable` interface, then the property will

Basic example:
```php
use \Abivia\Hydration\Hydrator;

class BasicObject {

    private static Hydrator $hydrator;

    public string $userName;
    public string $password;

    public function hydrate($config)
    {
        if (!isset(self::$hydrator)) {
            self::$hydrator = new Hydrator();
            self::$hydrator->bind(self::class);
        }
        self::$hydrator->hydrate($this, $config);
    }
}

$json = '{"userName": "admin"; "password": "secret"}';
$obj = new BasicObject();
$obj->hydrate($json);
echo "$obj->userName, $obj->password";
```
Expected Output:
admin, secret

See `test/ExampleBasicTest.php` for a working example.

## Selectively Convert Objects to Associative Arrays

The `json_decode()` method has an option to force conversion from object
to array, but there is no way to get selective conversion. Hydration can
do this via a class map to 'array'. See `test/ExampleSelectiveArrayTest.php`
for a working example.

```php
class SelectiveArrayObject
{

    private static Hydrator $hydrator;

    public array $simple;

    public function hydrate($config)
    {
        if (!isset(self::$hydrator)) {
            self::$hydrator = new Hydrator();
            self::$hydrator
                ->addProperty(
                    Property::make('simple')->toArray('array')
                )
                ->bind(self::class);
        }
        self::$hydrator->hydrate($this, $config);
    }
}

$json = '{"simple": { "a": "this is a", "*": "this is *"}}';
$obj = new SelectiveArrayObject();
$obj->hydrate($json);
print_r($hydrate);
```
Will give this result

```
(
    [simple] => Array
        (
            [a] => this is a
            [*] => this is *
        )
)
```


## Associative arrays using a property as the key

Have an array of objects with a property that you'd like to extract for use as
an array key? No problem.

```php
class AssociativeArrayObject implements Hydratable
{
    private static Hydrator $hydrator;

    public array $list;

    public function hydrate($config, $options = []): bool
    {
        if (!isset(self::$hydrator)) {
            self::$hydrator = new Hydrator();
            self::$hydrator
                ->addProperty(
                    Property::make('list')
                        ->bind('stdClass')
                        ->key('code')
                )
                ->bind(self::class);
        }
        return self::$hydrator->hydrate($this, $config);
    }
}

$json = '{"list":[
    {"code": "fb", "name": "Facebook"},
    {"code": "tw", "name": "Twitter"},
    {"code": "ig", "name": "Instagram"}
]}
';

$hydrate = new AssociativeArrayObject();
$hydrate->configure(json_decode($json));
echo implode(', ', array_keys($hydrate->list) "\n";
echo $hydrate->list['ig']->name;
```

Will output

```
fb, tw, ig
Instagram
```

## Options

The `options` parameter can contain these elements:

- 'parent' when instantiating a subclass, this is a reference to the parent class.
- 'strict' Controls error handling. If strict is false, Hydration will ignore minor
  issues. If strict is true, Hydration will throw an exception.

Applications can also pass in their own context via options. The current options are
available via `Hydrator::getOptions()`. Option names starting with an underscore
are guaranteed to not conflict with future options used by Hydration.

Note that a copy of the options array is passed to subclass configuration, no data can
be returned to the parent via this array.

---
## `Hydrator`

---
### Hydrator::addProperty($property): self

`Property $property` The property object to add.

Attaches a property specification to the `Hydrator`. See `Property` for details.

---
### Hydrator::addProperties($properties, $options): self

`array $properties` Elements are any of 'propertyName', ['sourceName', 'targetName'],
or a Property object.

`array $options` Common attributes to apply to the new properties. Options are any
public method of the Property class, except __construct, as, assign, make, and reflects.
Use an array to pass multiple arguments.

Creates or attaches a list of properties to the `Hydrator`.

---
### Hydrator::bind($subject[, int $filter]): self
Associates a `Hydrator` with the class to be hydrated. Bind will add any properties
in the subject class that have not already been defined via `addProperty()`
and which match the filter flag.

`string|object $subject` This is the name or an instance of the class to bind the hydrator to.

`int $filter` Filters automatically generated properties by visibility. The default is public. 
Accepts any combination of ReflectionProperty::IS_PRIVATE, ReflectionProperty::IS_PROTECTED,
and ReflectionProperty::IS_PUBLIC. 

---
### Hydrator::encode($source, $rules = [])

`object $source` The object to be encoded
`EncoderRule|array|null $rules` Rules to be applied to the object.

Uses the properties defined in the Hydrator to encode the data in `$source`
into a `stdClass` object. If any rules are provided, they are applied
to the `stdClass` object.

---
### Hydrator::getErrors(): array
Returns an array of errors generated during the last call to `hydrate()`
the resulting array is empty if no errors were generated.

---
### Hydrator::getOptions(): array

Returns the options used in the last call to `hydrate()`.

---
### Hydrator::getSource($name): Property

`string $name` The name of the property in the source data.

Retrieve a Property by source name. Throws `HydrationException` if not found. Use
`Hydration::hasSource()` to see if the property exists.

---
### Hydrator::getTarget($name): Property

`string $name` The name of the property in the target object.

Retrieve a Property by target name. Throws `HydrationException` if not found. Use
`Hydration::hasTarget()` to see if the property exists.

---
### Hydrator::hasSource($name): bool

`string $name` The name of the property in the source data.

Check for a source property.

---
### Hydrator::hasTarget($name): bool

`string $name` The name of the property in the target object.

Check for a target property.

---
### Hydrator::hydrate($target, $config[, $options]): bool

`object $target` The object to be hydrated.

`string|object|array $config` Configuration data either as a string or the result of
decoding a configuration file. The contents are determined by the `source` option.

`array|null $options` Options are:
- `object parent` A reference to the object containing $target (if any).
- `string source` Format of the data in $config. Possible values:
[json] | object | yaml 
- `bool strict` If true, log and throw errors on any failure. If false, just log errors. Defaults to true.
- Application specific options begin with an underscore and will be passed through unchanged.

Loads configuration data into an object structure. Returns true on success.

Throws HydrationException on error.

---

### Hydrator::make($subject[, $filter]): self

`string|object|null $subject` This is the name or an instance of the class to bind the hydrator to.

`int $filter` Visibility filter. See `bind()`.

Fluent constructor.

---

## `Property`
Properties are a powerful way to transform and validate user input, to
ensure the hydrated structures are valid and consistent.

---
### Property::make($property[, $binding]): self

`string $property` Name of the property in the source data.

`string|null $binding` Name of the class to create when hydrating the property.

Fluent constructor.

---
### Property::as($name): self

`string $name` The property name in the class.

Use a different name when storing the property.

**Example**: In the source configuration, the user sees a property "app-name".
Since hyphens are not valid in property names, we want to map that to appName in the object.
```php
Property::make('app-name')->as('appName');
```

---
### Property::block([$message]): self

`string|null $message` A custom message to be returned as the error.

Prevent this property from being hydrated. Attempts to hydrate this property
will generate a `HydrationException`. If a message is provided it will be in the
exception, otherwise a default message is generated. See `unblock()`.

---
### Property::unblock(): self

Allow this property to be hydrated. Clears the `block()` setting.

---
### Property::ignore([$ignore]): self

`bool $ignore` Ignore state

Prevent this property from being hydrated. Attempts to hydrate this property
will be ignored.

---
### Property::require([$required]): self

`bool $ignore` Require state

Require this property to be hydrated. If data provided to the hydrator does
not include this property, a HydrationException will be thrown.

---
### Property::reflects($reflectProperty): self

Sets the property's reflection info.

`ReflectionProperty|string|object $reflectProperty` The target class, an object of that class, or
a ReflectionProperty object.

---
### Property::bind($binding[, $method]): self
Defines the class to be created when populating the property.

`string|object|null $binding` A class name or an object of the class to be bound.
If null, then the property is just a simple assignment.

`string $method` The name of the method to call when hydrating this property.
Defaults to 'hydrate'.

---
### Property::with($callback[, $method)]): self

`Closure $callback` Function that returns the name of the class to be used when
hydrating this property.

This enables the creation of different objects based on the content of the data. The 
Closure takes the property value and current Property as arguments.

**Example:** Return a class based on the `type` property in the property value.
```php
Property::make('myProperty')
    ->with(function ($value) {
        return 'MyNamespace\BaseName' . ucfirst(strtolower($value->type));
    })
```

---
### Property::set($options): self

`array $options` Settings array indexed by method.

Configure the property via a list of attributes.

---
### Property::construct($className[, $unpack]): self

`string $className` Name of the class to be created.

`bool $unpack` If the data to be passed is an array, unpack it to individual arguments.

Populate a class through the constructor. If `$unpack` is set and the property
value is an array, the array is unpacked and passed through as individual arguments.
This method is useful for populating PHP Classes, for example `DateInterval`.

---
### Property::setter($method): self

`string $method` The name of a method in the target class.

Invoke this method to set the value of the property. The
method(mixed $value, Property $property):bool takes the proposed property value and Property as
arguments, returns true on success.

**Example:** See `test\ExampleSetterTest.php`

---
### Property::getter($method): self

`string $method` The name of a method in the target class.

Invoke this method to set the value of the property. The
method(Property $property):bool takes the Property as an argument, returns the value of the
property.

---
### Property::validate($fn): self

`Closure $fn` Validation function ($value, $property):bool to return true on success. 

Function to validate the contents of a property before setting it.

---
### Property::toArray($castToArray): self

---
### Property::key([$key]): self

`string|bool|Closure|null $key` Array index. Default is `true`

Indicates that this property is an array, optionally indicating how the array key
should be calculated.

If `$key` is `true`, then the array is created with integer keys starting at zero.

If `$key` is s `string` then it specifies property in the value to be used as the index.

If `$key` is a `Closure` then it receives the object being hydrated, the value, and the Property
as arguments and is expected to return the array key as a string.

If `$key` is `false`, `null`, or absent, then the property is not treated as an array. 


---
### Property::getBlocked(): bool

Gets the current block state.

---
### Property::getClass(): ?string

Gets the class associated with this property. If the class is computed via a closure,
the method returns `null`.

---
### Property::getErrors(): array

Gets the current error list array.

---
### Property::getHydrateMethod(): string

Gets the name of the method used to hydrate an object created for this property.

---
### Property::getIgnored(): bool

Gets the current ignore state.

---
### Property::getRequired(): bool

Gets the current require state.

---
### Property::getOptions(): array

Gets the current hydration options.

---
### Property::source(): string

Returns the name of this property in the source data.

---
### Property::target(): string

Returns the name of this property in the hydrated object. If the property is accessed through a
getter or setter method, the name is prefixed with an asterisk.

---
### Property::assign($target, $value[, $options]): bool

`object $target` The object being hydrated.
`mixed $value` Value of the property.
`array $options` Options (passed to any objects hydrated by this property).

Assigns `$value` to the property in `$target`. Used by `Hydrator`.

---
### Property::encodeWith($rules): self

`string|EncoderRule|EncoderRule[] $rules`

Define rules for encoding the property (see `EncoderRule`).
Multiple rules can be defined by delimiting them with a vertical bar.

For example, to not output a property that is blank or null:

`$property->encodeWith('drop:blank|drop:null`);`

---
## Encoder

The `Encoder` applies a set of rules contained in a list of properties
to prepare an object fo encoding in JSON, YAML, etc.

The nominal use case is to let a `Hydrator` manage encoding however it is
possible to use it directly.

---
## EncoderRule

Contains a single encoding command and any related arguments.

---
### EncoderRule::arg($slot): mixed

`int $slot` Argument number, starting from zero.

Retrieves the requested argument or returns null if the argument is not defined.

---
### EncoderRule::command(): string

Retrieves the rule's command.

---
### EncoderRule::define($command[, ...$args]): self

`string $command` Either a simple command or a command:argument.
`mixed ...$args` Command arguments

Defines a command with arguments. A compound command has the form "command:arg0:arg1..."

---
### EncoderRule::emit($value): bool

Determines whether this value should be part of the output.

---
### EncoderRule::make($command[, ...$args]): self

Fluent constructor. See `define`.