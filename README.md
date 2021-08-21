#Abivia\Hydration

Hydration populates complex data structures from user editable JSON or YAML
sources. If your application

- has configurations with several levels of nesting,
- needs to validate user editable data in configuration files,
- is spending a lot of effort reading from the stdClass objects created by `json_decode()` or `yaml_parse()` to convert
  them into your application's class structures, or
- is just using `stdClass` objects for configuration

then Hydration is here to help.

Hydration makes it easy to convert of a set of untyped data structures
returned by decoding JSON or YAML configuration files, into PHP classes.
Hydration can:

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


##Example

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

##Features

Hydration supports property mapping, gated properties (via block, and ignore methods),
data validation, and data-driven class instantiation. It will map arrays of objects to
associative arrays of PHP classes that are indexed by any unique scalar property in the object.

Loading can be either fault-tolerant or strict. Strict validation can either fail with a
`false` result or by throwing an Exception.

##Installation

```composer require abivia/hydration```

Hydration uses the Symphony parser for YAML.


##Basic Use

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

Selectively Convert Objects to Associative Arrays
---

The `json_decode()` method has an option to force conversion of objects
to arrays, but there is no way to get selective conversion. Hydration can
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


Associative arrays using a property as the key
---

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

Advanced Use
---

- If you need to map properties, implement `configurePropertyMap()` where needed.
- Add property validation by implementing `configureValidate()`.
- Gate access to properties by implementing any of `configurePropertyAllow()`,
  `configurePropertyBlock()` or `configurePropertyIgnore()`.
- You can also initialize the class instance at run time with `configureInitialize()`.
- Semantic validation of the result can be performed at the end of the loading process by
  implementing `configureComplete()`.

Options
---
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
##`class Hydrator`

---
###`addProperty(Property $property)`
Fluent. Attaches a property specification to the `Hydrator`. See `Property` for details.

---
###`bind($subject[, int $filter])`
Fluent. Associates a `Hydrator` with the class to be hydrated. Bind will add any properties
in the subject class that have not already been defined via `addProperty()`
and which match the filter flag.

`string|object $subject` This is the name or an instance of the class to bind the hydrator to.

`int $filter` Filters automatically generated properties by visibility. The default is public. 
Accepts any combination of ReflectionProperty::IS_PRIVATE, ReflectionProperty::IS_PROTECTED,
and ReflectionProperty::IS_PUBLIC. 

---
###`getErrors()`
Returns an array of errors generated during the last call to `hydrate()`
the resulting array is empty if no errors were generated.

---
###`getOptions()`
Returns the options used in the last call to `hydrate()`.

---
###`hydrate($target, $config[, $options])`
Loads configuration data into an object structure.

`object $target` The object to be hydrated.

`string|object|array $config` Configuration data either as a string or the result of
decoding a configuration file. The contents are determined by the `source` option.

`array|null $options` Options are:
- `object parent` A reference to the object containing $target (if any).
- `string source` Format of the data in $config. Possible values:
[json] | object | yaml 
- `bool strict` If true, log and throw errors on any failure. If false, just log errors. Defaults to true.
- Application specific options begin with an underscore and will be passed through unchanged.

Returns true on success.

Throws HydrationException on error.

---

###make($subject[, $filter])
Fluent constructor.

`string|object|null $subject` This is the name or an instance of the class to bind the hydrator to.

`int $filter` Visibility filter. See `bind()`.

---

##`class Property`
Properties are a powerful way to transform and validate user input, to
ensure the hydrated structures are valid and consistent.

---
###`make($property[, $binding])`
Fluent constructor.

`string $property` Name of the property in the source data.
`string|null $binding` Name of the class to create when hydrating the property.

---
###as($name)

Fluent. Use a different name when storing the property.

`string $property` The property name in the class.

**Example**: In the source configuration, the user sees a property "app-name".
Since hyphens are not valid in property names, we want to map that to appName in the object.
```php
Property::make('app-name')->as('appName');
```

---
###bind

---
###block

---
###construct

getBlocked
getErrors
getHydrateMethod
getIgnored
ignore
key
reflects
setter
source
target
toArray
unblock
validate
---
###assign