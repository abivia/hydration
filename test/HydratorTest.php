<?php
/** @noinspection ALL */

namespace Abivia\Hydration\Test;

require_once 'objects/DefaultConfig.php';
require_once 'objects/PropertyJig.php';
require_once 'objects/RequiredConfig.php';

use Abivia\Hydration\EncoderRule;
use Abivia\Hydration\Hydratable;
use Abivia\Hydration\HydrationException;
use Abivia\Hydration\Hydrator;
use Abivia\Hydration\Property;
use Abivia\Hydration\Test\objects\DefaultConfig;
use Abivia\Hydration\Test\Objects\PropertyJig;
use Abivia\Hydration\Test\Objects\RequiredConfig;
use Exception;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use stdClass;
use Symfony\Component\Yaml\Yaml;

class BadConfigException extends Exception
{
}

class ConfigurableMain
{
    public $badClass1;
    public $badClass2;
    public $badClass3;
    public $badClass4;
    public $doNotConfigure;

    /**
     * A member of this class is configured during configureComplete
     * @var stdClass
     */
    public $genericForSubConfiguration;

    public int $hydrateFilter = 0;

    private static Hydrator $hydrator;

    public $ignored;
    public $mappedClass;
    public $prop1;
    public $prop2;
    public $propArray;
    public $subAssoc;
    public $subAssocDup;
    public $subAssocP;
    public $subCallable;
    public $subClass;
    public ?ConfigurableSub $subClass2 = null;
    public $subClassArray;
    public $subDynamic;

    /**
     * @param mixed $value
     * @param Property $property
     * @return bool
     */
    public function addToCallable($value, Property $property): bool
    {
        $options = $property->getOptions();
        $this->subCallable[] = $value;

        return true;
    }

    public function getErrors(): array
    {
        if (!isset(self::$hydrator)) {
            self::hydrateInit($this->hydrateFilter);
        }
        return self::$hydrator->getErrors();
    }

    public function hydrate($config, $options = []): bool
    {
        // Re-initialize on every call because filters might change.
        self::hydrateInit($this->hydrateFilter);

        // Pre-hydration expansion of condensed properties.
        if (is_object($config) && isset($config->subClass) && is_array($config->subClass)) {
            foreach ($config->subClass as $key => $value) {
                if (!is_string($value)) {
                    continue;
                }
                $obj = new stdClass;
                $obj->subProp1 = $value;
                $config->subClass[$key] = $obj;
            }
        } elseif (is_array($config) && isset($config['subClass']) && is_array($config['subClass'])) {
            foreach ($config['subClass'] as $key => $value) {
                if ($key === 'subProp1' || isset($value['subProp1'])) {
                    continue;
                }
                $config['subClass'][$key] = ['subProp1' => $value];
            }
        }

        // Test that custom options are passed through
        $options['_custom'] = 'appOptions';
        $result = self::$hydrator->hydrate($this, $config, $options);
        if ($result && $this->genericForSubConfiguration !== null) {
            // Convert the sub property into a ConfigurableSub
            if (isset($this->genericForSubConfiguration->subClass)) {
                foreach ($this->genericForSubConfiguration->subClass as $key => $config) {
                    $obj = new ConfigurableSub();
                    $result = $obj->hydrate($config, self::$hydrator->getOptions());
                    if (!$result) {
                        return false;
                    }
                    $this->genericForSubConfiguration->subClass[$key] = $obj;
                }
            }
        }

        return $result;
    }

    /**
     * Create and initialize the hydrator.
     *
     * @throws \Abivia\Hydration\HydrationException
     * @throws \ReflectionException
     */
    private static function hydrateInit($filter)
    {
        self::$hydrator = Hydrator::make()
            ->addProperty(Property::make('badClass1', 'ThisClassDoesNotExist'))
            ->addProperty(
                Property::make('class')
                    ->as('mappedClass')
            //->bind(ConfigurableSub::class)
            )
            ->addProperty(Property::make('doNotConfigure')->block())
            ->addProperty(Property::make('ignored')->ignore())
            ->addProperty(
                Property::make('prop1')
                    ->validate(function ($value) {
                        return in_array($value, ['red', 'green', 'blue']);
                    })
            )
            ->addProperty(
                Property::make('propArray')
                    ->toArray()
            )
            ->addProperty(
                Property::make('subAssoc')
                    ->key('key')
                    ->bind(ConfigurableSub::class)
            )
            ->addProperty(
                Property::make('subAssocDup')
                    ->key('key')
                    ->allowDuplicates()
                    ->bind(ConfigurableSub::class)
            )
            ->addProperty(
                Property::make('subAssocP')
                    ->key(function ($obj, $value) {
                        return $obj->getKeyP($value);
                    })
                    ->bind(ConfigurableSub::class)
            )
            ->addProperty(
                Property::make('subCallable')
                    ->setter('addToCallable')
                    ->bind(ConfigurableSub::class)
            )
            ->addProperty(
                Property::make('subDynamic')
                    ->key('key')
                    ->with(function ($value) {
                        return __NAMESPACE__ . '\\ConfigurableType' . ucfirst($value->type);
                    })
            )
            ->addProperty(Property::make('subClass', ConfigurableSub::class))
            ->addProperty(
                Property::make('subClass2')
                //->bind()
            )
            ->addProperty(Property::make('subClassArray')
                ->key()
                ->bind(ConfigurableSub::class)
            )
            ->bind(self::class, $filter);
    }

}

/**
 * Subclass that can be created during configuration.
 * This class uses the trait's validation, which always returns true.
 */
class ConfigurableSub implements Hydratable
{
    private static Hydrator $hydrator;

    public $key;
    protected $keyP;
    public $notConfigurable;
    public $subProp1;

    public function checkOption($name)
    {
        return self::$hydrator->getOptions()[$name];
    }

    public function getErrors(): array
    {
        return self::$hydrator->getErrors();
    }

    public function getKeyP()
    {
        return $this->keyP;
    }

    public function hydrate($config, $options = []): bool
    {
        if (!isset(self::$hydrator)) {
            self::hydrateInit();
        }
        $result = self::$hydrator->hydrate($this, $config, $options);

        return $result;
    }

    private static function hydrateInit()
    {
        self::$hydrator = Hydrator::make()
            ->addProperty(
                Property::make('notConfigurable')
                    ->block()
            )
            ->bind(self::class, Hydrator::ALL_NONSTATIC_PROPERTIES);
    }

}

/**
 * A test class that can be created during configuration.
 */
class ConfigurableTypeA
{
    public $key;
    public $propA;
    public $type;

    public function hydrate($config, $options = []): bool
    {
        $this->key ??= $config->key;
        $this->propA ??= $config->propA;
        $this->type ??= $config->type;
        return true;
    }

}

/**
 * B test class that can be created during configuration.
 */
class ConfigurableTypeB implements Hydratable
{
    public $key;
    public $propB;
    public $type;

    public function hydrate($config, ?array $options = []): bool
    {
        $this->key ??= $config->key;
        $this->propB ??= $config->propB;
        $this->type ??= $config->type;
        return true;
    }

}

class HydratorTest extends TestCase
{

    static $configSource = [
        'testBadScalar' => '"invalid"',

        'testPropertyMapping' => '{"class":"purple"}',
        'testPropertyMappingArray' => '{"propArray": {"array1":"one", "array5":"five"}}',

        'testSimpleEmptyArray' => '{"prop2":[]}',
        'testSimpleIgnoreRelaxed' => '{"ignored":"purple"}',
        'testSimpleIgnoreStrict' => '{"ignored":"purple"}',
        'testSimpleInvalid' => '{"prop1":"purple"}',

        'testSimpleUndeclaredRelaxed' => '{"undeclared":"purple"}',
        'testSimpleUndeclaredStrict' => '{"undeclared":"purple"}',
        'testSimpleUndeclaredStrictException' => '{"undeclared":"purple"}',

        'testSimpleValid' => '{"prop1":"blue"}',
        'testSimpleValidStrictDefault' => '{"prop1":"blue","bonus":true}',

        'testSubclassArrayNew' => '{"subClassArray":[{"subProp1":"e0"},{"subProp1":"e1"}]}',
        'testSubclassArrayNewAssoc' => '{"subAssoc":[{"key":"item0","subProp1":"e0"},{"key":"item1","subProp1":"e1"}]}',
        'testSubclassArrayNewAssocCast' => '{"subAssoc":{"key":"item0","subProp1":"e0"}}',
        'testSubclassArrayNewAssocDupKeys' => '{"subAssocDup":[{"key":"item0","subProp1":"e0"},{"key":"item0","subProp1":"e1"}]}',
        'testSubclassArrayNewAssocNoDupKeys' => '{"subAssoc":[{"key":"item0","subProp1":"e0"},{"key":"item0","subProp1":"e1"}]}',
        'testSubclassArrayNewAssocP' => '{"subAssocP":[{"keyP":"item0","subProp1":"e0"},{"keyP":"item1","subProp1":"e1"}]}',
        'testSubclassArrayNewEmpty' => '{"subClass":[]}',
        'testSubclassDynamic' => '{"subDynamic":['
            . '{"key":"item0","type":"a","propA":"e0"},'
            . '{"key":"item1","type":"b","propB":"e1"}]'
            . '}',
        'testSubclassArrayNewTransform' => '{"subClass":["e0","e1",{"subProp1":"e2"}]}',
        'testSubclassScalar' => '{"subClass":{"subProp1":"subprop"}}',
        'testSubclassScalarNew' => '{"subClass":{"subProp1":"subprop"}}',
        'testSubclassStringNew' => '{"subClass2":{"subProp1":"subprop"}}',
    ];

    static function getConfig($method, $format = '')
    {
        if ($format == '') {
            $source = substr($method, 0, -4);
            $format = strtolower(substr($method, -4));
        } else {
            $source = $method;
        }
        if (!isset(self::$configSource[$source])) {
            throw new Exception('Unknown configuration source ' . $source);
        }
        switch ($format) {
            case 'json':
                $result = self::$configSource[$source];
                break;
            case 'yaml':
                $result = json_decode(self::$configSource[$source], true);
                if ($result) {
                    $result = Yaml::dump($result);
                }
                break;
            default:
                throw new Exception('Unknown format ' . $format);
        }
        if (!$result) {
            throw new Exception('Configuration source error in ' . $method);
        }
        return $result;
    }

    public function testAddProperties()
    {
        $hydrate = Hydrator::make()
            ->addProperties([
                'subProp1',
                ['george', 'subDynamic'],
                Property::make('bob')->as('key')
            ])
            ->bind(ConfigurableSub::class, 0);
        $this->assertTrue($hydrate->hasSource('subProp1'));
        $this->assertTrue($hydrate->hasSource('george'));
        $this->assertTrue($hydrate->hasSource('bob'));
        $this->assertTrue($hydrate->hasTarget('subProp1'));
        $this->assertTrue($hydrate->hasTarget('subDynamic'));
        $this->assertTrue($hydrate->hasTarget('key'));
    }

    public function testAddPropertiesWithOptions()
    {
        $hydrate = Hydrator::make()
            ->addProperties(
                ['subProp1'],
                [
                    'ignore' => true,
                    'construct' => ['SomeClass', true],
                    'validate' => function ($value) {
                        return $value == 5;
                    },
                    'as' => 'should be ignored'
                ]
            )
            ->bind(ConfigurableSub::class, 0);
        $this->assertTrue($hydrate->hasSource('subProp1'));
        $prop = $hydrate->getSource('subProp1');
        $this->assertTrue($prop->getIgnored());
        $this->assertEquals('SomeClass', $prop->getClass());
    }

    public function testAddProperties_Bad()
    {
        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage("Expected 'propertyName'");
        Hydrator::make()
            ->addProperties([new StdClass()])
            ->bind(ConfigurableSub::class, 0);
    }

    public function testAddProperty()
    {
        $hydrate = Hydrator::make()
            ->addProperty(
                Property::make('bob')->as('key')
            )
            ->bind(ConfigurableSub::class);
        $this->assertTrue($hydrate->hasSource('bob'));
        $property = $hydrate->getSource('bob');
        $this->assertEquals('key', $property->target());
        $this->assertFalse($hydrate->hasSource('carol'));
        $this->assertFalse($hydrate->hasSource('key'));
        $this->assertTrue($hydrate->hasTarget('key'));
        $property = $hydrate->getTarget('key');
        $this->assertEquals('bob', $property->source());
    }

    public function testAddProperty_Bad()
    {
        $hydrate = Hydrator::make()
            ->bind(ConfigurableSub::class);
        $this->expectException(HydrationException::class);
        $hydrate->addProperty(
            Property::make('bob')->as('key')
        );
    }

    public function testBadScalar()
    {
        foreach (['json', 'yaml'] as $format) {
            $config = self::getConfig(__FUNCTION__, $format);
            $obj = new ConfigurableMain();
            $this->expectException(HydrationException::class);
            $this->expectExceptionMessage('Unexpected scalar value');
            $obj->hydrate($config, ['source' => $format]);
        }
    }

    public function testConfigurableInstantiation()
    {
        $obj = new ConfigurableMain();
        $this->assertInstanceOf(ConfigurableMain::class, $obj);
        $obj = new ConfigurableSub();
        $this->assertInstanceOf(ConfigurableSub::class, $obj);
    }

    public function testGetSource()
    {
        $obj = new Hydrator();
        $obj->addProperties(['prop1'])->bind(ConfigurableMain::class);

        $this->assertInstanceOf(Property::class, $obj->getSource('prop1'));

        $this->expectException(HydrationException::class);
        $obj->getSource('nope');
    }

    public function testGetSources()
    {
        $obj = new Hydrator();
        $obj->addProperties(['prop1'])->bind(ConfigurableMain::class);

        $this->assertArrayHasKey('prop1', $obj->getSources());
    }

    public function testGetTarget()
    {
        $obj = new Hydrator();
        $obj->addProperties(['foo', 'bar']);

        $this->assertInstanceOf(Property::class, $obj->getTarget('bar'));

        $this->expectException(HydrationException::class);
        $obj->getSource('nope');
    }

    public function testGetTargets()
    {
        $obj = new Hydrator();
        $obj->addProperties(['foo', 'bar']);

        $this->assertArrayHasKey('bar', $obj->getTargets());
    }

    public function testEncode()
    {
        $hydrator = Hydrator::make()
            ->addProperties(['key', 'p1'])
            ->addProperties(['a1', 'o1'], ['ignore' => true])
            ->bind(DefaultConfig::class);
        $source = new DefaultConfig();
        $source->key = 'someKey';
        $source->p1 = 'this is p1';
        $result = $hydrator->encode($source);
        $expect = new stdClass();
        $expect->key = 'someKey';
        $expect->p1 = 'this is p1';
        $this->assertEquals($expect, $result);
    }

    public function testEncodeRules()
    {
        $hydrator = Hydrator::make()
            ->addProperties(['key', 'p1'])
            ->addProperties(['a1', 'o1'], ['ignore' => true])
            ->bind(DefaultConfig::class);
        $source = new DefaultConfig();
        $source->key = 'someKey';
        $source->p1 = 'this is p1';
        $topRule = new EncoderRule();
        $topRule->define('transform', function (&$value) {
            return $value->key . '<==>' . $value->p1;
        });
        $result = $hydrator->encode($source, $topRule);
        $expect = new stdClass();
        $expect = 'someKey<==>this is p1';
        $this->assertEquals($expect, $result);
    }

    /**
     * Ensure that nested calls to configure do not modify the source data.
     */
    public function testNestedDoesNotCorruptSource()
    {
        $json = '{
            "genericForSubConfiguration":{
                "subClass":[{"subProp1":"e0"},{"subProp1":"e1"}]
            }
        }';

        $source = json_decode($json);
        $config = json_decode($json);
        $obj = new ConfigurableMain();
        $obj->hydrateFilter = ReflectionProperty::IS_PUBLIC;
        $this->assertTrue($obj->hydrate($config, ['source' => 'object']));
        $this->assertEquals($source, $config);
    }

    /**
     * An attempt to set a blocked property generates an error in relaxed mode.
     */
    public function testPropertyAllow()
    {
        $config = '{"notConfigurable":"purple"}';
        $obj = new ConfigurableSub();
        $obj->notConfigurable = 'uninitialized';
        $result = $obj->hydrate($config, ['strict' => false]);
        $this->assertFalse($result);
        $this->assertEquals('uninitialized', $obj->notConfigurable);
        $this->assertStringContainsStringIgnoringCase(
            'prohibited', $obj->getErrors()[0]
        );
    }

    /**
     * A strict attempt to set a blocked property causes an exception.
     */
    public function testPropertyAllowStrict()
    {
        $config = '{"notConfigurable":"purple"}';
        $obj = new ConfigurableSub();
        $obj->notConfigurable = 'uninitialized';
        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage('prohibited');
        $obj->hydrate($config, ['strict' => true]);
    }

    /**
     * ensure that blocked properties can't be set.
     */
    public function testPropertyBlock()
    {
        $config = '{"doNotConfigure":"purple"}';
        $obj = new ConfigurableMain();
        $obj->doNotConfigure = 'uninitialized';
        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage('prohibited');
        $obj->hydrate($config, ['strict' => true]);
    }

    public function testPropertyMapping()
    {
        $config = self::getConfig(__FUNCTION__, 'json');
        $obj = new ConfigurableMain();
        $obj->mappedClass = 'uninitialized';
        $result = $obj->hydrate($config);
        if (!$result) {
            print_r($obj->getErrors());
        }
        $this->assertTrue($result);
        $this->assertEquals('purple', $obj->mappedClass);
        $this->assertEquals([], $obj->getErrors());
    }

    public function testPropertyMappingArray()
    {
        $config = self::getConfig(__FUNCTION__, 'json');
        $obj = new ConfigurableMain();
        $result = $obj->hydrate($config);
        if (!$result) {
            print_r($obj->getErrors());
        }
        $this->assertTrue($result);
        $this->assertEquals(['array1' => 'one', 'array5' => 'five'], $obj->propArray);
        $this->assertEquals([], $obj->getErrors());
    }

    /**
     * ensure that required properties work.
     */
    public function testPropertyRequire()
    {
        $obj = new RequiredConfig();
        $config = '{"key":"foo", "p1": "p1"}';
        $this->assertTrue($obj->hydrate($config));
        $config = '{"p1": "p1"}';
        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage('No value provided');
        $obj->hydrate($config);
    }

    public function testReflectionType()
    {
        $reflectionInfo = Hydrator::fetchReflection(PropertyJig::class);
        $result = [];
        /**
         * @var ReflectionProperty $property
         */
        foreach ($reflectionInfo['properties'] as $property) {
            $result[$property->getName()] = Hydrator::reflectionType($property);
        }
        $expect = [
            'arrayOfTestData' => 'array',
            'arrayProtected' => 'array',
            'blocked' => 'string',
            'evenInt' => 'int',
            'getPropertyIsNull' => 'bool',
            'ignorable' => 'string',
            'objectClass' => 'object',
            'objectClassArray' => 'array',
            'internalName' => 'string',
            'privateString' => 'string',
            'setPropertyIsNull' => 'bool',
            'subClass' => RequiredConfig::class,
            'prop' => null,
            'unspecified' => 'string',
        ];
        $this->assertEquals($expect, $result);
    }

    /**
     * Make sure a basic empty array returns an empty array
     */
    public function testSimpleEmptyArray()
    {
        foreach (['json', 'yaml'] as $format) {
            $config = self::getConfig(__FUNCTION__, $format);
            $obj = new ConfigurableMain();
            $obj->hydrateFilter = ReflectionProperty::IS_PUBLIC;
            $obj->prop2 = 'uninitialized';
            $this->assertTrue($obj->hydrate($config, ['source' => $format]));
            $this->assertEquals([], $obj->prop2);
            $this->assertEquals([], $obj->getErrors());
        }
    }

    /**
     * The presence of a declared but ignored property succeeds but does not change
     * the value in relaxed mode.
     */
    public function testSimpleIgnoreRelaxed()
    {
        foreach (['json', 'yaml'] as $format) {
            $config = self::getConfig(__FUNCTION__, $format);
            $obj = new ConfigurableMain();
            $obj->ignored = 'uninitialized';
            $this->assertTrue($obj->hydrate($config, ['source' => $format]));
            $this->assertEquals('uninitialized', $obj->ignored);
            $this->assertEquals([], $obj->getErrors());
        }
    }

    /**
     * The presence of a declared but ignored property succeeds but does not change
     * the value in strict mode.
     */
    public function testSimpleIgnoreStrict()
    {
        foreach (['json', 'yaml'] as $format) {
            $config = self::getConfig(__FUNCTION__, $format);
            $obj = new ConfigurableMain();
            $obj->ignored = 'uninitialized';
            $this->assertTrue(
                $obj->hydrate($config, ['source' => $format, 'strict' => true])
            );
            $this->assertEquals('uninitialized', $obj->ignored);
            $this->assertEquals([], $obj->getErrors());
        }
    }

    public function testSimpleInvalid()
    {
        foreach (['json', 'yaml'] as $format) {
            $config = self::getConfig(__FUNCTION__, $format);
            $obj = new ConfigurableMain();
            $obj->prop1 = 'uninitialized';
            $this->assertFalse($obj->hydrate($config, ['source' => $format]));
            $this->assertEquals('uninitialized', $obj->prop1);
            $this->assertStringContainsStringIgnoringCase(
                'invalid value',
                $obj->getErrors()[0]
            );
        }
    }

    public function testSimpleUndeclaredRelaxed()
    {
        foreach (['json', 'yaml'] as $format) {
            $config = self::getConfig(__FUNCTION__, $format);
            $obj = new ConfigurableMain();
            $obj->prop1 = 'uninitialized';
            $this->assertTrue(
                $obj->hydrate($config, ['source' => $format, 'strict' => false])
            );
            $this->assertEquals('uninitialized', $obj->prop1);
            $this->assertEquals([], $obj->getErrors());
        }
    }

    /**
     * The presence of an undeclared property causes configure() to fail in strict mode.
     */
    public function testSimpleUndeclaredStrict()
    {
        foreach (['json', 'yaml'] as $format) {
            $config = self::getConfig(__FUNCTION__, $format);
            $obj = new ConfigurableMain();
            $obj->prop1 = 'uninitialized';
            $this->expectException(HydrationException::class);
            $this->expectExceptionMessage('Undefined property');
            $obj->hydrate($config, ['source' => $format, 'strict' => true]);
        }
    }

    public function testSimpleUndeclaredStrictException()
    {
        foreach (['json', 'yaml'] as $format) {
            $config = self::getConfig(__FUNCTION__, $format);
            $obj = new ConfigurableMain();
            $obj->prop1 = 'uninitialized';
            $this->expectException(HydrationException::class);
            $this->expectExceptionMessage('Undefined property');
            $obj->hydrate($config, ['source' => $format, 'strict' => true]);
        }
    }

    public function testSimpleValid()
    {
        foreach (['json', 'yaml'] as $format) {
            $config = self::getConfig(__FUNCTION__, $format);
            $obj = new ConfigurableMain();
            $obj->prop1 = 'uninitialized';
            $result = $obj->hydrate($config, ['source' => $format]);
            if (!$result) {
                print_r($obj->getErrors());
            }
            $this->assertTrue($result);
            $this->assertEquals('blue', $obj->prop1);
            $this->assertEquals([], $obj->getErrors());
        }
    }

    /**
     * Pass an empty options array to make sure strict defaults
     */
    public function testSimpleValidStrictDefault()
    {
        foreach (['json', 'yaml'] as $format) {
            $config = self::getConfig(__FUNCTION__, $format);
            $obj = new ConfigurableMain();
            $obj->prop1 = 'uninitialized';
            $this->expectException(HydrationException::class);
            $this->expectExceptionMessage('Undefined property');
            $obj->hydrate($config, ['source' => $format]);
        }
    }

    public function testSubclassArrayNew()
    {
        // {"subClassArray":[{"subProp1":"e0"},{"subProp1":"e1"}]}
        foreach (['json', 'yaml'] as $format) {
            $config = self::getConfig(__FUNCTION__, $format);
            $obj = new ConfigurableMain();
            $obj->prop1 = 'uninitialized';
            $this->assertTrue($obj->hydrate($config, ['source' => $format]), $format);
            $this->assertIsArray($obj->subClassArray, $format);
            $this->assertCount(2, $obj->subClassArray, $format);
            $this->assertInstanceOf(ConfigurableSub::class, $obj->subClassArray[0], $format);
            $this->assertEquals('e0', $obj->subClassArray[0]->subProp1, $format);
            $this->assertEquals('e1', $obj->subClassArray[1]->subProp1, $format);
            $this->assertEquals([], $obj->getErrors(), $format);
        }
    }

    /**
     * Test populating an associative array when the key property is public.
     */
    public function testSubclassArrayNewAssoc()
    {
        foreach (['json', 'yaml'] as $format) {
            $config = self::getConfig(__FUNCTION__, $format);
            $obj = new ConfigurableMain();
            $obj->prop1 = 'uninitialized';
            $this->assertTrue($obj->hydrate($config, ['source' => $format]));
            $this->assertIsArray($obj->subAssoc);
            $this->assertEquals(2, count($obj->subAssoc));
            $this->assertTrue(isset($obj->subAssoc['item0']));
            $this->assertInstanceOf(ConfigurableSub::class, $obj->subAssoc['item0']);
            $this->assertEquals('e0', $obj->subAssoc['item0']->subProp1);
            $this->assertEquals('e1', $obj->subAssoc['item1']->subProp1);
            $this->assertEquals([], $obj->getErrors());
        }
    }

    /**
     * Check that we cast to an array when a key is specified
     */
    public function testSubclassArrayNewAssocCast()
    {
        foreach (['json', 'yaml'] as $format) {
            $config = self::getConfig(__FUNCTION__, $format);
            $obj = new ConfigurableMain();
            $obj->prop1 = 'uninitialized';
            $this->assertTrue($obj->hydrate($config, ['source' => $format]));
            $this->assertIsArray($obj->subAssoc);
            $this->assertEquals(1, count($obj->subAssoc));
            $this->assertTrue(isset($obj->subAssoc['item0']));
            $this->assertInstanceOf(ConfigurableSub::class, $obj->subAssoc['item0']);
            $this->assertEquals('e0', $obj->subAssoc['item0']->subProp1);
            $this->assertEquals([], $obj->getErrors());
        }
    }

    /**
     * Test duplicate keys allowed
     */
    public function testSubclassArrayNewAssocDupKeys()
    {
        foreach (['json', 'yaml'] as $format) {
            $config = self::getConfig(__FUNCTION__, $format);
            $obj = new ConfigurableMain();
            $obj->prop1 = 'uninitialized';
            $this->assertTrue($obj->hydrate($config, ['source' => $format]));
            $this->assertIsArray($obj->subAssocDup);
            $this->assertEquals(1, count($obj->subAssocDup));
            $this->assertTrue(isset($obj->subAssocDup['item0']));
            $this->assertInstanceOf(ConfigurableSub::class, $obj->subAssocDup['item0']);
            $this->assertEquals('e1', $obj->subAssocDup['item0']->subProp1);
            $this->assertEquals(
                [],
                $obj->getErrors()
            );
        }
    }

    /**
     * Test duplicate key error
     */
    public function testSubclassArrayNewAssocNoDupKeys()
    {
        foreach (['json', 'yaml'] as $format) {
            $config = self::getConfig(__FUNCTION__, $format);
            $obj = new ConfigurableMain();
            $obj->prop1 = 'uninitialized';
            $this->assertFalse($obj->hydrate($config, ['source' => $format]));
            $this->assertIsArray($obj->subAssoc);
            $this->assertCount(1, $obj->subAssoc);
            $this->assertTrue(isset($obj->subAssoc['item0']));
            $this->assertInstanceOf(ConfigurableSub::class, $obj->subAssoc['item0']);
            $this->assertEquals('e0', $obj->subAssoc['item0']->subProp1);
            $this->assertStringContainsStringIgnoringCase(
                'Duplicate key',
                $obj->getErrors()[0]
            );
        }
    }

    /**
     * Test populating an associative array when the key property must be accessed
     * via a getter.
     */
    public function testSubclassArrayNewAssocP()
    {
        foreach (['json', 'yaml'] as $format) {
            $config = self::getConfig(__FUNCTION__, $format);
            $obj = new ConfigurableMain();
            $obj->prop1 = 'uninitialized';
            $this->assertTrue($obj->hydrate($config, ['source' => $format]));
            $this->assertIsArray($obj->subAssocP);
            $this->assertEquals(2, count($obj->subAssocP));
            $this->assertTrue(isset($obj->subAssocP['item0']));
            $this->assertInstanceOf(ConfigurableSub::class, $obj->subAssocP['item0']);
            $this->assertEquals('e0', $obj->subAssocP['item0']->subProp1);
            $this->assertEquals('e1', $obj->subAssocP['item1']->subProp1);
            $this->assertEquals([], $obj->getErrors());
        }
    }

    /**
     * Check that we handle an empty subclass array
     */
    public function testSubclassArrayNewEmpty()
    {
        foreach (['json', 'yaml'] as $format) {
            $config = self::getConfig(__FUNCTION__, $format);
            $obj = new ConfigurableMain();
            $this->assertTrue($obj->hydrate($config, ['source' => $format]));
            $this->assertIsArray($obj->subClass);
            $this->assertEquals(0, count($obj->subClass));
            $this->assertEquals([], $obj->getErrors());
        }
    }

    public function testSubclassArrayNewInvalid()
    {
        $config = '{"subClass":[{"subProp1":"e0"},{"badprop":"e1"}]}';
        $obj = new ConfigurableMain();
        $obj->prop1 = 'uninitialized';
        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage('Undefined property');
        $obj->hydrate($config, ['strict' => true]);
    }

    /**
     * Test transforming data in initialization. Only works with source=object.
     */
    public function testSubclassArrayNewTransform()
    {
        $config = self::getConfig(__FUNCTION__, 'json');
        $config = json_decode($config);
        $obj = new ConfigurableMain();
        $obj->prop1 = 'uninitialized';
        $this->assertTrue($obj->hydrate($config, ['source' => 'object']));
        $this->assertIsArray($obj->subClass);
        $this->assertCount(3, $obj->subClass);
        $this->assertInstanceOf(ConfigurableSub::class, $obj->subClass[0]);
        $this->assertEquals('e0', $obj->subClass[0]->subProp1);
        $this->assertEquals('e1', $obj->subClass[1]->subProp1);
        $this->assertEquals('e2', $obj->subClass[2]->subProp1);
        $this->assertEquals([], $obj->getErrors());
    }

    public function testSubclassCallableNew()
    {
        $config = '{"subCallable":
            [{"subProp1":"e0"},{"subProp1":"e1"}]
        }';
        $obj = new ConfigurableMain();
        $this->assertTrue($obj->hydrate($config));
        $this->assertIsArray($obj->subCallable);
        $this->assertCount(2, $obj->subCallable);
        $this->assertInstanceOf(ConfigurableSub::class, $obj->subCallable[0]);
        $this->assertEquals('e0', $obj->subCallable[0]->subProp1);
        $this->assertEquals('e1', $obj->subCallable[1]->subProp1);
        $this->assertEquals([], $obj->getErrors());
    }

    /**
     * Test use of a closure to trigger data-dependent instantiation
     */
    public function testSubclassDynamic()
    {
        foreach (['json', 'yaml'] as $format) {
            $config = self::getConfig(__FUNCTION__, $format);
            $obj = new ConfigurableMain();
            $obj->prop1 = 'uninitialized';
            $result = $obj->hydrate($config, ['source' => $format]);
            if (!$result) {
                print_r($obj->getErrors());
            }
            $this->assertTrue($result);
            $this->assertIsArray($obj->subDynamic);
            $this->assertCount(2, $obj->subDynamic);
            $this->assertTrue(isset($obj->subDynamic['item0']));
            $this->assertInstanceOf(ConfigurableTypeA::class, $obj->subDynamic['item0']);
            $this->assertEquals('e0', $obj->subDynamic['item0']->propA);
            $this->assertInstanceOf(ConfigurableTypeB::class, $obj->subDynamic['item1']);
            $this->assertEquals('e1', $obj->subDynamic['item1']->propB);
            $this->assertEquals([], $obj->getErrors());
        }
    }

    /**
     * Test initializing a pre-existing subclass.
     */
    public function testSubclassScalar()
    {
        foreach (['json', 'yaml'] as $format) {
            $config = self::getConfig(__FUNCTION__, $format);
            $obj = new ConfigurableMain();
            $obj->subClass = new ConfigurableSub();
            $obj->subClass->subProp1 = 'uninitialized';
            $this->assertTrue($obj->hydrate($config, ['source' => $format]));
            $this->assertInstanceOf(ConfigurableSub::class, $obj->subClass);
            $this->assertEquals('subprop', $obj->subClass->subProp1);
            $this->assertEquals([], $obj->getErrors());
            // See if our custom option got passed in
            $this->assertEquals(
                'appOptions',
                $obj->subClass->checkOption('_custom')
            );
        }
    }

    /**
     * Test initializing an internally instantiated subclass.
     */
    public function testSubclassScalarNew()
    {
        foreach (['json', 'yaml'] as $format) {
            $config = self::getConfig('testSubclassScalar', $format);
            $obj = new ConfigurableMain();
            $obj->prop1 = 'uninitialized';
            $result = $obj->hydrate($config, ['source' => $format]);
            $this->assertTrue(
                $result,
                $format . "\n" . implode("\n", $obj->getErrors())
            );
            $this->assertInstanceOf(ConfigurableSub::class, $obj->subClass);
            $this->assertEquals('subprop', $obj->subClass->subProp1);
            $this->assertEquals([], $obj->getErrors());
        }
    }

    public function testSubclassScalarNewInvalid()
    {
        $config = '{"subClass":{"badprop":"subprop"}}';
        $obj = new ConfigurableMain();
        $obj->prop1 = 'uninitialized';
        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage('Undefined property');
        $obj->hydrate($config, ['strict' => true]);
    }

    /**
     * Test initializing an internally instantiated subclass with a string class specification.
     */
    public function testSubclassStringNew()
    {
        foreach (['json', 'yaml'] as $format) {
            $config = self::getConfig(__FUNCTION__, $format);
            $obj = new ConfigurableMain();
            $obj->prop1 = 'uninitialized';
            $this->assertTrue($obj->hydrate($config, ['source' => $format]));
            $this->assertInstanceOf(ConfigurableSub::class, $obj->subClass2);
            $this->assertEquals('subprop', $obj->subClass2->subProp1);
            $this->assertEquals([], $obj->getErrors());
        }
    }

    /**
     * Case where a requested class does not exist
     */
    public function testSubclass_Bad()
    {
        $config = '{"badClass1":{"subProp1":"e0"}}';
        $obj = new ConfigurableMain();
        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage('Unable to load');
        $result = $obj->hydrate($config);
    }

}
