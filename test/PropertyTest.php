<?php

namespace Abivia\Hydration\Test;

require 'Objects/ConstructOneArg.php';
require 'Objects/DefaultConfig.php';
require 'Objects/PropertyJig.php';

use Abivia\Hydration\HydrationException;
use Abivia\Hydration\Property;
use PHPUnit\Framework\TestCase;
use stdClass;

class PropertyTest extends TestCase
{

    public function test__construct()
    {
        $obj = new Property('common');
        $this->assertEquals('common', $obj->source());
        $this->assertEquals('common', $obj->target());
    }

    public function testMake()
    {
        $obj = Property::make('common');
        $this->assertEquals('common', $obj->source());
        $this->assertEquals('common', $obj->target());
    }

    public function testTarget()
    {
        $obj = Property::make('source')->as('target');
        $this->assertEquals('source', $obj->source());
        $this->assertEquals('target', $obj->target());
    }

    public function testBind()
    {
        $obj = Property::make('externalName')
            ->as('internalName');
        $obj->bind(Objects\PropertyJig::class);
        $this->assertEquals('hydrate', $obj->getHydrateMethod());
    }

    public function testBindHydrate()
    {
        $obj = Property::make('source');
        $this->assertEquals('hydrate', $obj->getHydrateMethod());
        $obj->bind('stdClass', 'custom');
        $this->assertEquals('custom', $obj->getHydrateMethod());
    }

    public function testBlock()
    {
        $msg = 'This property is blocked.';
        $obj = Property::make('blocked');
        $this->assertFalse($obj->getBlocked());

        $obj->block();
        $this->assertTrue($obj->getBlocked());

        $obj->unblock();
        $this->assertFalse($obj->getBlocked());

        $obj->block($msg);
        $this->assertTrue($obj->getBlocked());
        $this->assertEquals($msg, $obj->getBlockMessage());

        $target = new stdClass();
        $this->assertFalse($obj->assign($target, 'nada', ['strict' => false]));

        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage($msg);
        $this->assertFalse($obj->assign($target, 'nada'));

    }

    public function testIgnore()
    {
        $obj = Property::make('ignorable');
        $this->assertFalse($obj->getIgnored());

        $obj->ignore();
        $this->assertTrue($obj->getIgnored());

        $obj->ignore(false);
        $this->assertFalse($obj->getIgnored());

        $target = new Objects\PropertyJig();
        $reflectClass = new \ReflectionClass($target);
        $reflectProp = $reflectClass->getProperty('ignorable');
        $obj->reflects($reflectProp)->ignore();

        $status = $obj->assign($target, 'modified');
        $this->assertTrue($status);
        $this->assertEquals('unchanged', $target->ignorable);
    }

    public function testAssignPrivateString()
    {
        $target = new Objects\PropertyJig();
        $reflectClass = new \ReflectionClass($target);
        $reflectProp = $reflectClass->getProperty('privateString');

        $obj = Property::make('privateString')
            ->reflects($reflectProp);
        $this->assertEquals('initial', $target->getPrivateString());

        $status = $obj->assign($target, 'hydrated');
        $this->assertTrue($status);
        $this->assertEquals('hydrated', $target->getPrivateString());
    }

    public function testAssignArray()
    {
        $target = new Objects\PropertyJig();
        $reflectClass = new \ReflectionClass($target);
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
        $target = new Objects\PropertyJig();
        $reflectClass = new \ReflectionClass($target);
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

    public function testAssignAssociativeArray()
    {
        $target = new Objects\PropertyJig();
        $reflectClass = new \ReflectionClass($target);
        $reflectProp = $reflectClass->getProperty('arrayOfTestData');

        $obj = Property::make('arrayOfTestData')
            ->reflects($reflectProp)
            ->key();

        $data = ['p1' => 'one', 'p3' => 'two', 'p9' => 'three'];
        $status = $obj->assign($target, $data);
        $this->assertTrue($status);
        $this->assertEquals($data, $target->arrayOfTestData);
    }

    public function testAssignInstance()
    {
        $target = new Objects\PropertyJig();
        $reflectClass = new \ReflectionClass($target);
        $reflectProp = $reflectClass->getProperty('objectClass');

        $obj = Property::make('objectClass')
            ->reflects($reflectProp)
            ->bind(Objects\DefaultConfig::class);

        $json = '{"key": "k0", "p1": "hello"}';
        $config = json_decode($json);
        $status = $obj->assign($target, $config);
        $this->assertTrue($status);
        $expect = new Objects\DefaultConfig();
        $expect->key = 'k0';
        $expect->p1 = 'hello';

        $this->assertEquals($expect, $target->getObjectClass());
    }

    public function testAssignInstanceArray()
    {
        $target = new Objects\PropertyJig();
        $reflectClass = new \ReflectionClass($target);
        $reflectProp = $reflectClass->getProperty('objectClassArray');

        $obj = Property::make('objectClassArray')
            ->key('key')
            ->reflects($reflectProp)
            ->bind(Objects\DefaultConfig::class);

        $json = '[
            {"key": "k0", "p1": "hello"},
            {"key": "k1", "p1": "goodbye"}
        ]';
        $config = json_decode($json);
        $status = $obj->assign($target, $config);
        $this->assertTrue($status);
        $expect = [];
        $work = new Objects\DefaultConfig();
        $work->key = 'k0';
        $work->p1 = 'hello';
        $expect['k0'] = $work;
        $work = new Objects\DefaultConfig();
        $work->key = 'k1';
        $work->p1 = 'goodbye';
        $expect['k1'] = $work;

        $this->assertEquals($expect, $target->objectClassArray);
    }

    public function testAssignInstanceArrayNoDuplicates()
    {
        $target = new Objects\PropertyJig();
        $reflectClass = new \ReflectionClass($target);
        $reflectProp = $reflectClass->getProperty('objectClassArray');

        $obj = Property::make('objectClassArray')
            ->key('key')
            ->reflects($reflectProp)
            ->bind(Objects\DefaultConfig::class);

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
        $target = new Objects\PropertyJig();
        $reflectClass = new \ReflectionClass($target);
        $reflectProp = $reflectClass->getProperty('objectClassArray');

        $obj = Property::make('objectClassArray')
            ->key('key')
            ->reflects($reflectProp)
            ->allowDuplicates()
            ->bind(Objects\DefaultConfig::class);

        $json = '[
            {"key": "k0", "p1": "hello"},
            {"key": "k1", "p1": "goodbye"},
            {"key": "k1", "p1": "overwrite"}
        ]';
        $config = json_decode($json);
        $status = $obj->assign($target, $config);
        $this->assertTrue($status);
        $expect = [];
        $work = new Objects\DefaultConfig();
        $work->key = 'k0';
        $work->p1 = 'hello';
        $expect['k0'] = $work;
        $work = new Objects\DefaultConfig();
        $work->key = 'k1';
        $work->p1 = 'overwrite';
        $expect['k1'] = $work;

        $this->assertEquals($expect, $target->objectClassArray);
    }

    public function testAssignConstructor()
    {
        $target = new Objects\PropertyJig();
        $reflectClass = new \ReflectionClass($target);
        $reflectProp = $reflectClass->getProperty('objectClass');

        $obj = Property::make('objectClass')
            ->reflects($reflectProp)
            ->construct(Objects\ConstructOneArg::class);

        $json = '"hello"';
        $config = json_decode($json);
        $status = $obj->assign($target, $config);
        $this->assertTrue($status);

        $this->assertEquals('hello', $target->getObjectClass()->arg);
    }

    public function testAssignMethod()
    {
        $target = new Objects\PropertyJig();

        $obj = Property::make('ignorable')
            ->setter('setIgnorable');

        $status = $obj->assign($target, 'viaMethod');
        $this->assertTrue($status);
        $this->assertEquals('viaMethod', $target->ignorable);
    }

    public function testSetReflectionString()
    {
        $target = new Objects\PropertyJig();
        $reflectClass = new \ReflectionClass($target);
        $reflectProp = $reflectClass->getProperty('prop');
        $obj = Property::make('prop')
            ->reflects(Objects\PropertyJig::class);

        // It is sufficient to not see an exception before we get here.
        $this->assertTrue(true);
    }

    public function testSetReflectionObject()
    {
        $target = new Objects\PropertyJig();
        $obj = Property::make('prop')
            ->reflects($target);

        // It is sufficient to not see an exception before we get here.
        $this->assertTrue(true);
    }

    public function testSetReflectionBad1()
    {
        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage('does not exist');
        Property::make('prop')
            ->reflects('ClassDoesNotexist');
    }

    public function testSetReflectionBad2()
    {
        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage('does not exist');
        $target = new Objects\PropertyJig();
        Property::make('bogus')
            ->reflects($target);
    }

    public function testValidation() {
        $target = new Objects\PropertyJig();
        $reflectClass = new \ReflectionClass($target);
        $reflectProp = $reflectClass->getProperty('evenInt');

        $obj = Property::make('evenInt')
            ->as(Objects\PropertyJig::class)
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

    public function testValidationArray() {
        $target = new Objects\PropertyJig();
        $reflectClass = new \ReflectionClass($target);
        $reflectProp = $reflectClass->getProperty('evenInt');

        $obj = Property::make('evenIntArray')
            ->as(Objects\PropertyJig::class)
            ->key()
            ->validate(function ($value) {
                return ($value & 1) === 0;
            })
            ->reflects($reflectProp);

        $status = $obj->assign($target, [2, 4, 9, 6]);
        $this->assertFalse($status);
        $this->assertEquals(['Invalid value for evenIntArray'], $obj->getErrors());
    }

}
