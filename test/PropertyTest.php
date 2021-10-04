<?php
/** @noinspection ALL */

namespace Abivia\Hydration\Test;

require_once 'objects/AltMethod.php';
require_once 'objects/ConstructOneArg.php';
require_once 'objects/DefaultConfig.php';
require_once 'objects/PropertyJig.php';

use Abivia\Hydration\EncoderRule;
use Abivia\Hydration\HydrationException;
use Abivia\Hydration\Property;
use Abivia\Hydration\Test\Objects\AltMethod;
use Abivia\Hydration\Test\Objects\ConstructOneArg;
use Abivia\Hydration\Test\Objects\DefaultConfig;
use Abivia\Hydration\Test\Objects\PropertyJig;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use stdClass;

class PropertyTest extends TestCase
{

    public function testAssignArray()
    {
        $target = new PropertyJig();
        $reflectClass = new ReflectionClass($target);
        $reflectProp = $reflectClass->getProperty('arrayOfTestData');

        $obj = Property::make('arrayOfTestData')
            ->reflects($reflectProp)
            ->key();

        $data = ['one', 'two', 'three'];
        $status = $obj->assign($target, $data);
        $this->assertTrue($status);
        $this->assertEquals($data, $target->arrayOfTestData);
    }

    public function testAssignArrayCast()
    {
        $target = new PropertyJig();
        $reflectClass = new ReflectionClass($target);
        $reflectProp = $reflectClass->getProperty('arrayOfTestData');

        $obj = Property::make('arrayOfTestData')
            ->toArray()
            ->reflects($reflectProp)
            ->key();

        $data = new stdClass();
        $data->one = 1;
        $data->two = 2;
        $data->three = 3;
        $status = $obj->assign($target, $data);
        $this->assertTrue($status);
        $this->assertEquals(
            ["one" => 1, "two" => 2, "three" => 3],
            $target->arrayOfTestData
        );
    }

    public function testAssignArrayFromScalar()
    {
        $target = new PropertyJig();
        $reflectClass = new ReflectionClass($target);
        $reflectProp = $reflectClass->getProperty('arrayOfTestData');

        $obj = Property::make('arrayOfTestData')
            ->reflects($reflectProp)
            ->key();

        $data = 'one';
        $status = $obj->assign($target, $data);
        $this->assertTrue($status);
        $this->assertEquals(['one'], $target->arrayOfTestData);
    }

    public function testAssignArrayNullKey()
    {
        $target = new PropertyJig();
        $reflectClass = new ReflectionClass($target);
        $reflectProp = $reflectClass->getProperty('arrayOfTestData');

        $obj = Property::make('arrayOfTestData')
            ->reflects($reflectProp)
            ->key(function () {
                return null;
            });

        $data = ['one', 'two', 'three'];
        $status = $obj->assign($target, $data);
        $this->assertTrue($status);
        $this->assertEquals($data, $target->arrayOfTestData);
    }

    public function testAssignAssociativeArray()
    {
        $target = new PropertyJig();
        $reflectClass = new ReflectionClass($target);
        $reflectProp = $reflectClass->getProperty('arrayOfTestData');

        $obj = Property::make('arrayOfTestData')
            ->reflects($reflectProp)
            ->key();

        $data = ['p1' => 'one', 'p3' => 'two', 'p9' => 'three'];
        $status = $obj->assign($target, $data);
        $this->assertTrue($status);
        $this->assertEquals($data, $target->arrayOfTestData);
    }

    public function testAssignConflict()
    {
        $target = new PropertyJig();
        $reflectClass = new ReflectionClass($target);
        $reflectProp = $reflectClass->getProperty('objectClass');

        $this->expectException(HydrationException::class);
        $obj = Property::make('objectClass')
            ->reflects($reflectProp)
            ->key()
            ->construct(ConstructOneArg::class);
    }

    public function testAssignConstructor()
    {
        $target = new PropertyJig();
        $reflectClass = new ReflectionClass($target);
        $reflectProp = $reflectClass->getProperty('objectClass');

        $obj = Property::make('objectClass')
            ->reflects($reflectProp)
            ->construct(ConstructOneArg::class);

        $json = '"hello"';
        $config = json_decode($json);
        $status = $obj->assign($target, $config);
        $this->assertTrue($status);

        $this->assertEquals('hello', $target->getObjectClass()->arg);
    }

    public function testAssignFactory()
    {
        $target = new PropertyJig();
        $reflectClass = new ReflectionClass($target);
        $reflectProp = $reflectClass->getProperty('objectClass');

        $obj = Property::make('objectClass')
            ->reflects($reflectProp)
            ->factory(function ($value, Property $property): object {
                return new \DateTime($value[0], new \DateTimeZone($value[1]));
            });

        $json = '["2000-01-01", "America/Toronto"]';
        $config = json_decode($json);
        $status = $obj->assign($target, $config);
        $this->assertTrue($status);

        $this->assertInstanceOf(\DateTime::class, $target->getObjectClass());
    }

    public function testAssignInstance()
    {
        $target = new PropertyJig();
        $reflectClass = new ReflectionClass($target);
        $reflectProp = $reflectClass->getProperty('objectClass');

        $obj = Property::make('objectClass')
            ->reflects($reflectProp)
            ->bind(DefaultConfig::class);

        $json = '{"key": "k0", "p1": "hello"}';
        $config = json_decode($json);
        $status = $obj->assign($target, $config);
        $this->assertTrue($status);
        $expect = new DefaultConfig();
        $expect->key = 'k0';
        $expect->p1 = 'hello';

        $this->assertEquals($expect, $target->getObjectClass());
    }

    public function testAssignInstanceArray()
    {
        $target = new PropertyJig();
        $reflectClass = new ReflectionClass($target);
        $reflectProp = $reflectClass->getProperty('objectClassArray');

        $obj = Property::make('objectClassArray')
            ->key('key')
            ->reflects($reflectProp)
            ->bind(DefaultConfig::class);

        $json = '[
            {"key": "k0", "p1": "hello"},
            {"key": "k1", "p1": "goodbye"}
        ]';
        $config = json_decode($json);
        $status = $obj->assign($target, $config);
        $this->assertTrue($status);
        $expect = [];
        $work = new DefaultConfig();
        $work->key = 'k0';
        $work->p1 = 'hello';
        $expect['k0'] = $work;
        $work = new DefaultConfig();
        $work->key = 'k1';
        $work->p1 = 'goodbye';
        $expect['k1'] = $work;

        $this->assertEquals($expect, $target->objectClassArray);
    }

    public function testAssignInstanceArrayNoDuplicates()
    {
        $target = new PropertyJig();
        $reflectClass = new ReflectionClass($target);
        $reflectProp = $reflectClass->getProperty('objectClassArray');

        $obj = Property::make('objectClassArray')
            ->key('key')
            ->reflects($reflectProp)
            ->bind(DefaultConfig::class);

        $json = '[
            {"key": "k0", "p1": "hello"},
            {"key": "k1", "p1": "goodbye"},
            {"key": "k1", "p1": "overwrite"}
        ]';
        $config = json_decode($json);
        $status = $obj->assign($target, $config);
        $this->assertFalse($status);
        $this->assertNotCount(0, $obj->getErrors());
    }

    public function testAssignInstanceArrayWithDuplicates()
    {
        $target = new PropertyJig();
        $reflectClass = new ReflectionClass($target);
        $reflectProp = $reflectClass->getProperty('objectClassArray');

        $obj = Property::make('objectClassArray')
            ->key('key')
            ->reflects($reflectProp)
            ->allowDuplicates()
            ->bind(DefaultConfig::class);

        $json = '[
            {"key": "k0", "p1": "hello"},
            {"key": "k1", "p1": "goodbye"},
            {"key": "k1", "p1": "overwrite"}
        ]';
        $config = json_decode($json);
        $status = $obj->assign($target, $config);
        $this->assertTrue($status);
        $expect = [];
        $work = new DefaultConfig();
        $work->key = 'k0';
        $work->p1 = 'hello';
        $expect['k0'] = $work;
        $work = new DefaultConfig();
        $work->key = 'k1';
        $work->p1 = 'overwrite';
        $expect['k1'] = $work;

        $this->assertEquals($expect, $target->objectClassArray);
    }

    public function testAssignMethod()
    {
        $target = new PropertyJig();

        $obj = Property::make('ignorable')
            ->setter('setIgnorable');

        $status = $obj->assign($target, 'viaMethod');
        $this->assertTrue($status);
        $this->assertEquals('viaMethod', $target->ignorable);
        $this->assertFalse($target->setPropertyIsNull);

        $obj->setter('setIgnorable', false);
        $status = $obj->assign($target, 'viaMethod2');
        $this->assertTrue($status);
        $this->assertEquals('viaMethod2', $target->ignorable);
        $this->assertTrue($target->setPropertyIsNull);

        $status = $obj->assign($target, 'badViaMethod');
        $this->assertFalse($status);
    }

    public function testAssignMethodArray()
    {
        $target = new PropertyJig();
        $reflectClass = new ReflectionClass($target);
        $reflectProp = $reflectClass->getProperty('arrayOfTestData');

        $obj = Property::make('arrayOfTestData')
            ->reflects($reflectProp)
            ->setter('setArray')
            ->key();

        $data = ['one', 'two', 'three'];
        $status = $obj->assign($target, $data);
        $this->assertTrue($status);
        $this->assertEquals($data, $target->arrayOfTestData);

        $badData = ['one', 'bad', 'three'];
        $status = $obj->assign($target, $badData);
        $this->assertFalse($status);
        $this->assertEquals($data, $target->arrayOfTestData);
    }

    public function testAssignObjectArray()
    {
        $target = new PropertyJig();
        $reflectClass = new ReflectionClass($target);
        $reflectProp = $reflectClass->getProperty('arrayOfTestData');

        $obj = Property::make('arrayOfTestData')
            ->reflects($reflectProp)
            ->key('key')
            ->bind(DefaultConfig::class);

        $data = json_decode('[
            {"key": "p1", "p1": "one"},
            {"key": "p2", "p1": "two"},
            {"key": "p3", "p1": "three"}
        ]');
        $expect = [
            'p1' => DefaultConfig::makeP1('p1', 'one'),
            'p2' => DefaultConfig::makeP1('p2', 'two'),
            'p3' => DefaultConfig::makeP1('p3', 'three'),
        ];
        $status = $obj->assign($target, $data);
        $this->assertTrue($status);
        $this->assertEquals($expect, $target->arrayOfTestData);
    }

    public function testAssignObjectArrayNoDups()
    {
        $target = new PropertyJig();
        $reflectClass = new ReflectionClass($target);
        $reflectProp = $reflectClass->getProperty('arrayOfTestData');

        $obj = Property::make('arrayOfTestData')
            ->reflects($reflectProp)
            ->key('key')
            ->bind(DefaultConfig::class);

        $data = json_decode('[
            {"key": "p1", "p1": "one"},
            {"key": "p2", "p1": "two"},
            {"key": "p3", "p1": "three"},
            {"key": "p2", "p1": "two-two"}
        ]');
        $status = $obj->assign($target, $data);
        $this->assertFalse($status);
        $this->assertStringContainsStringIgnoringCase(
            'duplicate key', $obj->getErrors()[0]
        );
    }

    public function testAssignObjectArrayWithDups()
    {
        $target = new PropertyJig();
        $reflectClass = new ReflectionClass($target);
        $reflectProp = $reflectClass->getProperty('arrayOfTestData');

        $obj = Property::make('arrayOfTestData')
            ->reflects($reflectProp)
            ->key('key')
            ->allowDuplicates()
            ->bind(DefaultConfig::class);

        $data = json_decode('[
            {"key": "p1", "p1": "one"},
            {"key": "p2", "p1": "two"},
            {"key": "p3", "p1": "three"},
            {"key": "p2", "p1": "two-two"}
        ]');
        $expect = [
            'p1' => DefaultConfig::makeP1('p1', 'one'),
            'p2' => DefaultConfig::makeP1('p2', 'two-two'),
            'p3' => DefaultConfig::makeP1('p3', 'three'),
        ];
        $status = $obj->assign($target, $data);
        $this->assertTrue($status);
        $this->assertEquals($expect, $target->arrayOfTestData);
    }

    public function testAssignPrivateString()
    {
        $target = new PropertyJig();
        $reflectClass = new ReflectionClass($target);
        $reflectProp = $reflectClass->getProperty('privateString');

        $obj = Property::make('privateString')
            ->reflects($reflectProp);
        $this->assertEquals('initial', $target->getPrivateString());

        $status = $obj->assign($target, 'hydrated');
        $this->assertTrue($status);
        $this->assertEquals('hydrated', $target->getPrivateString());
    }

    public function testAssignScalarArrayProtected()
    {
        $target = new PropertyJig();
        $reflectClass = new ReflectionClass($target);
        $reflectProp = $reflectClass->getProperty('arrayProtected');

        $obj = Property::make('arrayProtected')
            ->reflects($reflectProp);

        $status = $obj->assign($target, [1, 2, 3, 'bob' => 4]);
        $this->assertTrue($status);
    }

    public function testAssignScalarArrayPublic()
    {
        $target = new PropertyJig();
        $reflectClass = new ReflectionClass($target);
        $reflectProp = $reflectClass->getProperty('arrayOfTestData');

        $obj = Property::make('arrayOfTestData')
            ->reflects($reflectProp);

        $status = $obj->assign($target, [1, 2, 3, 'bob' => 4]);
        $this->assertTrue($status);
    }

    public function testBind()
    {
        $obj = Property::make('externalName')
            ->as('internalName');
        $obj->bind(PropertyJig::class);
        $this->assertEquals('hydrate', $obj->getHydrateMethod());
    }

    public function testBindHydrate()
    {
        $obj = Property::make('source');
        $this->assertEquals('hydrate', $obj->getHydrateMethod());
        $obj->bind(AltMethod::class, 'populate');
        $this->assertEquals('populate', $obj->getHydrateMethod());
    }

    public function testBindString()
    {
        $obj = Property::make('prop')
            ->bind(PropertyJig::class);

        // It is sufficient to not see an exception before we get here.
        $this->assertTrue(true);
    }

    public function testBlock()
    {
        $msg = 'This property is blocked.';
        $obj = Property::make('blocked');
        $this->assertFalse($obj->getBlocked());

        $obj->block();
        $this->assertTrue($obj->getBlocked());
        $obj->block('');

        $obj->unblock();
        $this->assertFalse($obj->getBlocked());

        $obj->block($msg);
        $this->assertTrue($obj->getBlocked());

        $target = new stdClass();
        $this->assertFalse($obj->assign($target, 'nada', ['strict' => false]));

        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage($msg);
        $this->assertFalse($obj->assign($target, 'nada'));

    }

    public function testEncodeWith()
    {
        $rule = new EncoderRule();
        $obj = Property::make('foo');
        $obj->encodeWith($rule);
        $rules = $obj->getEncode();
        $this->assertTrue($rule === $rules[0]);
        $obj->encodeWith('drop:null|order:30');
        $rules = $obj->getEncode();
        $this->assertCount(2, $rules);
        $this->assertEquals('drop', $rules[0]->command());
        $this->assertEquals('order', $rules[1]->command());
    }

    public function testGetter()
    {
        $source = new PropertyJig();

        $obj = Property::make('evenInt')
            ->getter('getEvenInt');

        $this->assertEquals(2, $obj->getValue($source));
        $this->assertFalse($source->getPropertyIsNull);

        $obj->getter('getEvenInt', false);
        $this->assertEquals(2, $obj->getValue($source));
        $this->assertTrue($source->getPropertyIsNull);
    }

    public function testGetValueProtected()
    {
        $target = new PropertyJig();
        $reflectClass = new ReflectionClass($target);
        $reflectProp = $reflectClass->getProperty('arrayProtected');

        $obj = Property::make('arrayProtected')
            ->reflects($reflectProp);

        $data = [1, 2, 3, 'bob' => 4];
        $status = $obj->assign($target, $data);
        $this->assertTrue($status);
        $actual = $obj->getValue($target);
        $this->assertEquals($data, $actual);
    }

    public function testHasReflection()
    {
        $obj = Property::make('externalName')
            ->as('internalName');
        $this->assertFalse($obj->hasReflection());
        $obj->reflects(PropertyJig::class);
        $this->assertTrue($obj->hasReflection());
    }

    public function testIgnore()
    {
        $obj = Property::make('ignorable');
        $this->assertFalse($obj->getIgnored());

        $obj->ignore();
        $this->assertTrue($obj->getIgnored());

        $obj->ignore(false);
        $this->assertFalse($obj->getIgnored());

        $target = new PropertyJig();
        $reflectClass = new ReflectionClass($target);
        $reflectProp = $reflectClass->getProperty('ignorable');
        $obj->reflects($reflectProp)->ignore();

        $status = $obj->assign($target, 'modified');
        $this->assertTrue($status);
        $this->assertEquals('unchanged', $target->ignorable);
    }

    public function testMake()
    {
        $obj = Property::make('common');
        $this->assertEquals('common', $obj->source());
        $this->assertEquals('common', $obj->target());
    }

    public function testMakeAs()
    {
        $obj = Property::makeAs('common');
        $this->assertEquals('common', $obj->source());
        $this->assertEquals('common', $obj->target());

        $obj = Property::makeAs(['common', 'uncommon']);
        $this->assertEquals('common', $obj->source());
        $this->assertEquals('uncommon', $obj->target());
    }

    public function testMakeAs_Bad()
    {
        $this->expectException(HydrationException::class);
        $obj = Property::makeAs(1);
    }

    public function testNoHydrateMethod()
    {
        $target = new PropertyJig();
        $reflectClass = new ReflectionClass($target);
        $reflectProp = $reflectClass->getProperty('objectClass');

        $obj = Property::make('objectClass')
            ->reflects($reflectProp)
            ->bind(DefaultConfig::class, 'bogus');

        $json = '"hello"';
        $config = json_decode($json);
        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage('no hydration method');
        $obj->assign($target, $config);
    }

    public function testRequire()
    {
        $obj = Property::make('requireable');
        $this->assertFalse($obj->getRequired());

        $obj->require();
        $this->assertTrue($obj->getRequired());

        $obj->require(false);
        $this->assertFalse($obj->getRequired());
    }

    public function testSet()
    {
        $obj = Property::make('prop');

        // Empty list should do nothing
        $obj->set([]);

        $obj->set(['ignore' => true]);
        $this->assertTrue($obj->getIgnored());

        $obj->set(['bind' => [function () {
        }, 'someMethod']]);
        $this->assertEquals('someMethod', $obj->getHydrateMethod());
    }

    public function testSetReflectionBad1()
    {
        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage('does not exist');
        Property::make('prop')
            ->reflects('ClassDoesNotExist');
    }

    public function testSetReflectionBad2()
    {
        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage('does not exist');
        $target = new PropertyJig();
        Property::make('bogus')
            ->reflects($target);
    }

    public function testSetReflectionObject()
    {
        $target = new PropertyJig();
        $obj = Property::make('prop')
            ->reflects($target);

        // It is sufficient to not see an exception before we get here.
        $this->assertTrue(true);
    }

    public function testSetReflectionString()
    {
        Property::make('prop')
            ->reflects(PropertyJig::class);

        // It is sufficient to not see an exception before we get here.
        $this->assertTrue(true);
    }

    public function testTarget()
    {
        $obj = Property::make('source')->as('target');
        $this->assertEquals('source', $obj->source());
        $this->assertEquals('target', $obj->target());
    }

    public function testValidation()
    {
        $target = new PropertyJig();
        $reflectClass = new ReflectionClass($target);
        $reflectProp = $reflectClass->getProperty('evenInt');

        $obj = Property::make('evenInt')
            ->as(PropertyJig::class)
            ->validate(function ($value) {
                return ($value & 1) === 0;
            })
            ->reflects($reflectProp);

        $status = $obj->assign($target, 4);
        $this->assertTrue($status);

        $status = $obj->assign($target, 9);
        $this->assertFalse($status);
        $this->assertEquals(['Invalid value for evenInt'], $obj->getErrors());
    }

    public function testValidationArray()
    {
        $target = new PropertyJig();
        $reflectClass = new ReflectionClass($target);
        $reflectProp = $reflectClass->getProperty('evenInt');

        $obj = Property::make('evenIntArray')
            ->as(PropertyJig::class)
            ->key()
            ->validate(function ($value) {
                return ($value & 1) === 0;
            })
            ->reflects($reflectProp);

        $status = $obj->assign($target, [2, 4, 9, 6]);
        $this->assertFalse($status);
        $this->assertEquals(['Invalid value for evenIntArray'], $obj->getErrors());
    }

    public function testValidationReference()
    {
        $target = new PropertyJig();
        $reflectClass = new ReflectionClass($target);
        $reflectProp = $reflectClass->getProperty('evenInt');

        $obj = Property::make('evenInt')
            ->as(PropertyJig::class)
            ->validate(function (&$value) {
                $value *= 2;
                return true;
            })
            ->reflects($reflectProp);

        $status = $obj->assign($target, 4);
        $this->assertTrue($status);
        $this->assertEquals(8, $target->getEvenInt());
    }

    public function test__construct()
    {
        $obj = new Property('common');
        $this->assertEquals('common', $obj->source());
        $this->assertEquals('common', $obj->target());
    }

}
