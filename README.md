Abivia\Configurable
====

Configurable is designed to make it easy to hydrate (populate) complex
data structures from JSON or YAML sources. If your application

- has configurations with several levels of nesting,
- isn't validating user editable data in configuration files,
- is spending a lot of effort reading from the stdClass objects created by
by `json_decode()` or `yaml_parse()` to convert them into your
application's class structures, or
- is just using poorly typed, IDE unfriendly `stdClass` objects for
configuration

then Configurable is here to help.

Configurable makes it easy to convert of a set of untyped data structures
returned by decoding JSON or YAML configuration files, into PHP classes.
Configurable can

- create associative arrays of named classes indexed by a property of the
class,
- selectively cast properties to an array,
- validate the source data,
- guard against overwriting of protected properties, and
- remap properties from user-friendly names to application-meaningful
 identifiers.

Example
----
`Configurable` makes it easy to take data like this:

```json
{
    "application-name": "MyApp",
    "database": [
        {
            "label": "crm",
            "driver": "mysql",
            "host": "localhost",
            "name": "crm",
            "pass": "insecure",
            "port": 3306,
            "user": "admin"
        },
        {
            "label": "geocoder",
            "driver": "mysql",
            "host": "localhost",
            "name": "geo",
            "pass": "insecure",
            "port": 3306,
            "user": "admin"
        }
    ]
}
```

and turn it into a class structure like this:

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
                    [pass:DatabaseConfiguration:private] => insecure
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
                    [pass:DatabaseConfiguration:private] => insecure
                    [port:protected] => 3306
                    [user:protected] => admin
                )
        )
)
```

Features
----

`Configurable` supports property mapping, gated properties (via allow, block, and ignore methods),
data validation, and data-driven class instantiation. It will map arrays of objects to
associative arrays of PHP classes that are indexed by any unique scalar property in the object.

Loading can be either fault-tolerant or strict. Strict validation can either fail with a
`false` result or by throwing an Exception you specify.

Installation
----

```composer require abivia/configurable```

Configurable uses the Symphony YAML parser.


Basic Use
----

- Implement `configureClassMap()` for the top level class and any properties that map to your classes.
- Add the configurable trait to your classes.
- Instantiate the top level class and pass your decoded JSON or YAML to the `configure()` method.

Basic example:
```php
class ConfigurableObject {
    use \Abivia\Configurable\Configurable;

    protected $userName;
    protected $password;

}
$json = '{"userName": "admin"; "password": "insecure"}';
$obj = new ConfigurableObject();
$obj->configure(json_decode($json));
echo $obj->userName . ', ' . $obj->password;
```
Output:
admin, insecure

Selectively Convert Objects to Arrays
---

The `json_decode()` method has an option to force conversion of objects
to arrays, but there is no way to get selective conversion. Configurable can
do this via a class map to 'array'. See `CastArrayTest.php`
for a working example.

```php
class ConfigCastArray
{
    use \Abivia\Configurable\Configurable;

    /**
     * @var array
     */
    public $simple = [];

    protected function configureClassMap($property, $value)
    {
        if ($property === 'simple') {
            return ['className' => 'array'];
        }
        return false;
    }
}
$json = '{"simple": { "a": "this is a", "*": "this is *"}}';

$hydrate = new ConfigCastArray();
$hydrate->configure(json_decode($json));
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
)```


Associative arrays using a property as the key
---

Have an array of objects with a property that you'd like to extract for use as
an array key? No problem.

```php
class SocialMedia
{
    public array $list;

    protected function configureClassMap($property, $value)
    {
        if ($property === 'list') {
            return ['className' => 'stdClass', 'key' => 'code'];
        }
        return false;
    }
}

$json = '{"list":[
    {"code": "fb", "name": "Facebook"},
    {"code": "t", "name": "Twitter"},
    {"code": "ig", "name": "Instagram"}
]}
';

$hydrate = new SocialMedia();
$hydrate->configure(json_decode($json));
echo implode(', ', array_keys($hydrate->list) "\n";
echo $hydrate->list['ig']->name;
```

Will output

```
fb, t, ig
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
The options parameter can contain these elements:

- 'newLog' If missing or set true, Configurable's error log is cleared before any
  processing.
- 'parent' when instantiating a subclass, this is a reference to the parent class.
- 'strict' Controls error handling. If strict is false, Configurable will ignore minor
  issues such as additional properties. If strict is true, Configurable will return false
  if any errors are encountered. If strict is a string, this will be taken as the name of
  a Throwable class, and an instance of that class will be thrown.

Applications can also pass in their own context via options. The current options are
available via the `$configureOptions` property. Option names starting with an underscore
are guaranteed to not conflict with future options used by Configurable.

Note that a copy of the options array is passed to subclass configuration, no data can
be returned to the parent via this array.

Filtering
-----
The properties of the configured object can be explicitly permitted by overriding the
`configurePropertyAllow()` method, blocked by overriding the `configurePropertyBlock()`
method, or ignored via the `configurePropertyIgnore()` method. Ignore takes precedence, then
blocking, then allow. By default, attempts to set guarded properties
are ignored, but if the $strict parameter is either true or the name of a `Throwable`
class, then the configuration will terminate when the invalid parameter is encountered,
unless it has been explicitly ignored.

For a JSON input like this
```json
{
    "depth": 15,
    "length": 22,
    "primary": "Red",
    "width": 3
}
```

with a class that does not have the `primary` property, the result depends on the
`strict` option:
```php
    class SomeClass {
        use \Abivia\Configurable;

        protected $depth;
        protected $length;
        protected $width;

    }

    $obj = new SomeClass();
    // Returns true
    $obj->configure($jsonDecoded);
    // Lazy validation: Returns true
    $obj->configure($jsonDecoded, ['strict' => false]);
    // Strict validation: Returns false
    $obj->configure($jsonDecoded, ['strict' => true]);
    // Strict validation: throws MyException
    $obj->configure($jsonDecoded, ['strict' => 'MyException']);
 ```

Initialization and Completion
---
In many cases it is required that the object be in a known state before configuration,
and that the configured object has acceptable values. `Configurable` supplies
`configureInitialize()` and `configureComplete()` for this purpose. `configureInitialize()`
can be used to return a previously instantiated object to a known state.
`configureInitialize()` gets passed references to the configuration data and the options
array, and is thus able to pre-process the inputs if required.

One use case for pre-processing during initialization is to allow shorthand expressions.
For example, if you have an object with one property:
```json
{"name": "foo"}
```
Your application can support a shorthand expression:
```json
"somevalue"
```
With this code in the initialization:
```php
protected function configureInitialize(&$config) {
    if (is_string($config)) {
        $obj = new stdClass;
        $obj->name = $config;
        $config = $obj;
    }
}
```


Validation
---
Scalar properties can be validated with `configureValidate()`. This method takes
the property name and the input value as arguments.
The value is passed by reference so that the validation can enforce specific formats
required by the object (for example by forcing case or cleaning out unwanted characters).

The `configureComplete()` method provides a mechanism for object level validation. For
example, this method could be used to validate a set of access credentials, logging an
error or aborting the configuration process entirely if they are not valid.

Property Name Mapping
---
Since configuration files allow properties that are not valid PHP property names,
`configurePropertyMap()` can be used to convert illegal input properties to valid
PHP identifiers.

```php
protected function configurePropertyMap($property) {
    if ($property[0] == '$') {
        $property = 'dollar_' . substr($property, 1);
    }
    return $property;
}
```

If the property does not reference another configurable class then
the method can also return an array containing a property name and array index.
For example this json:

```json
{
    "prop.one": "element one",
    "prop.six": "element six"
}
```

Can be used to create an array:

````php
$configured->prop = ['one' => 'element one', 'six' => 'element six']
````

Contained Classes
---
The real power of Configurable is through `configureClassMap()` which can be
used to instantiate and configure classes that are contained in the current
class. Contained classes must either be `stdClass` or provide the
 `configure()` method, either via the `Configurable` trait or by providing
their own method.

`configureClassMap()` takes the name and value of a property as arguments and returns:

- the name of a class to be instantiated and configured, or
- an array or object that has the `className` property and any of the optional properties.

### className (string|callable)
`className` can be the name of a class that will be instantiated and configured,
or it can be a callable that takes the current property value as an argument.
This allows the creation of data-specific object classes.

If a property `foo` returns an array of `['className' => 'MyClass']` or just
the string `MyClass` then Configurable will instantiate a new `MyClass` and
pass the value to the `configure()` method.

### construct (bool)
`className` is the name of a class that will be instantiated by passing the
 value to the class constructor.

If a property `foo` returns an array of `['className' => 'DateInterval', 'construct' => true]`
then Configurable will create a `new DateInterval($value)` and assign it to `foo`.

### constructUnpack (bool)
`className` is the name of a class that will be instantiated by passing the
 unpacked value (which must be an array) to the class constructor.

If a property `foo` returns an array of `['className' => 'MyClass', 'constructUnpack' => true]`
then Configurable will create a `new MyClass(...$value)` and assign it to `foo`.

### key (string|callable)
The `key` property is optional and tells Configurable to populate an array.

 - if `key` is absent or blank, the constructed object is appended to the array,
 - if `key` is a string, then it is taken as the name of a property or method (if
`keyIsMethod` is true) in the constructed object, and this value is used as the
key for an associative array, and
 - if `key` is a callable array, then it is called with the object under construction
as an argument.

### keyIsMethod (bool)
The `keyIsMethod` property is only used when `key` is present and not a callable. When
set, `key` is treated as a method of the constructed object. Typically this is a getter.

### allowDups (bool)
If Configurable is creating an associative array, the normal response to a duplicate
key is to generate an error message. if the `allowDups` flag is present and set,
no error is generated.

Error Logging
-------------
Any problems encountered during configuration are logged. An array of errors can be
retrieved by calling the `configureGetErrors()` method. The error log is cleared by an
application call to `configure()` unless the newLog option is set to false.

Unit Tests and Examples
========
Unit tests are organized by PHP support level. Tests that use features of PHP
that are not available in PHP 7.2 are maintained in separate directories.
PHPUnit will automatically run tests up to your current PHP version but
not above.

The unit tests also contain a number of examples that should be helpful in
understanding how Configurable works. More detailed
examples with sample output can be found at
https://gitlab.com/abivia/configurable-examples