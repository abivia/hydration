<?php
/** @noinspection ALL */

namespace Abivia\Hydration\Test;

require_once 'objects/ConstructOneArg.php';
require_once 'objects/DefaultConfig.php';

use Abivia\Hydration\Encoder;
use Abivia\Hydration\EncoderRule;
use Abivia\Hydration\HydrationException;
use Abivia\Hydration\Property;
use Abivia\Hydration\Test\objects\ConstructOneArg;
use Abivia\Hydration\Test\objects\DefaultConfig;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use stdClass;

class EncoderTest extends TestCase
{

    public function testAddProperties()
    {
        $coder = new Encoder();
        $coder->addProperties(['foo'], ['ignore' => true]);
        $props = $coder->getProperties();
        $this->assertArrayHasKey('foo', $props);
        $this->assertTrue($props['foo']->getIgnored());
    }

    public function testAddProperty()
    {
        $coder = new Encoder();
        $prop = Property::make('foo')->ignore();
        $coder->addProperty($prop);
        $props = $coder->getProperties();
        $this->assertArrayHasKey('foo', $props);
        $this->assertTrue($props['foo']->getIgnored());
    }

    public function testBind()
    {
        $coder = new Encoder();
        $coder->addProperties(['key', 'p1']);
        $coder->bind(DefaultConfig::class);
        $props = $coder->getProperties();
        $this->assertInstanceOf(
            ReflectionProperty::class, $props['key']->getReflection()
        );
    }

    public function testBind_bad()
    {
        $coder = new Encoder();
        $coder->addProperties(['key', 'p1']);
        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage('Property "key" is not defined');
        $coder->bind(ConstructOneArg::class);
    }

    public function testJson()
    {
        $coder = new Encoder();
        $coder->addProperties(['key', 'p1']);

        $coder->bind(DefaultConfig::class);
        $source = new DefaultConfig();
        $source->key = 'someKey';
        $source->p1 = 'this is p1';
        $result = $coder->encode($source);
        $expect = new stdClass();
        $expect->key = 'someKey';
        $expect->p1 = 'this is p1';
        $this->assertEquals($expect, $result);
    }

    public function testJsonArray()
    {
        $coder = new Encoder();
        $coder->addProperty(
            Property::make('asArray')
                ->as('a1')
                ->encodeWith('array')
        );

        $coder->bind(DefaultConfig::class);
        $source = new DefaultConfig();
        $source->a1 = ['prop1', 'prop2'];
        $result = $coder->encode($source);
        $expect = new stdClass();
        $expect->asArray = ['prop1', 'prop2'];
        $this->assertEquals($expect, $result);
    }

    public function testJsonArrayFromObject()
    {
        $property = Property::make('asArray')
            ->as('o1')
            ->encodeWith('array');

        // Attempting to do this with stubs has not yet succeeded.
//        $rule = $this->createStub(EncoderRule::class)
//            ->method('command')
//            ->willReturn('array');
//        $property = $this->createStub(Property::class);
//        $property->method('bind');
//        $property->method('source')->willReturn('asArray');
//        $property->method('target')->willReturn('o1');
//        $property->method('getClass')->willReturn(DefaultConfig::class);
//        $property->method('getEncode')->willReturn([$rule]);

        $coder = new Encoder();
        $coder->addProperty($property);

        $coder->bind(DefaultConfig::class);
        $source = new DefaultConfig();
        $source->o1 = new stdClass();
        $source->o1->prop1 = 'prop1';
        $source->o1->prop2 = 'prop2';
        $result = $coder->encode($source);
        $expect = new stdClass();
        $expect->asArray = ['prop1', 'prop2'];
        $this->assertEquals($expect, $result);
    }

    public function testJsonArrayFromScalar()
    {
        $coder = new Encoder();
        $coder->addProperty(
            Property::make('asArray')
                ->as('key')
                ->encodeWith('array')
        );

        $coder->bind(DefaultConfig::class);
        $source = new DefaultConfig();
        $source->key = 'prop1';
        $result = $coder->encode($source);
        $expect = new stdClass();
        $expect->asArray = ['prop1'];
        $this->assertEquals($expect, $result);
    }

    public function testJsonDrop()
    {
        $coder = new Encoder();
        $coder->addProperty(
            Property::make('key')->encodeWith('drop:blank')
        );
        $coder->addProperty(
            Property::make('p1')
        );

        $coder->bind(DefaultConfig::class);
        $source = new DefaultConfig();
        $source->key = 'someKey';
        $source->p1 = 'this is p1';
        $result = $coder->encode($source);
        $expect = new stdClass();
        $expect->key = 'someKey';
        $expect->p1 = 'this is p1';
        $this->assertEquals($expect, $result);

        $source->key = '';
        $source->p1 = 'this is p1';
        $result = $coder->encode($source);
        $expect = new stdClass();
        $expect->p1 = 'this is p1';
        $this->assertEquals($expect, $result);
    }

    public function testJsonNotBound()
    {
        $coder = new Encoder();
        $coder->addProperties(['key', 'p1']);

        $source = new DefaultConfig();
        $source->key = 'someKey';
        $source->p1 = 'this is p1';
        $result = $coder->encode($source);
        $expect = new stdClass();
        $expect->key = 'someKey';
        $expect->p1 = 'this is p1';
        $this->assertEquals($expect, $result);
    }

    public function testJsonOrder()
    {
        $coder = new Encoder();
        $keyProp = Property::make('key')->encodeWith('order:20');
        $coder->addProperty($keyProp);
        $coder->addProperty(
            Property::make('p1')->encodeWith('order:10')
        );

        $coder->bind(DefaultConfig::class);
        $source = new DefaultConfig();
        $source->key = 'someKey';
        $source->p1 = 'this is p1';
        $result = json_encode($coder->encode($source));
        $this->assertEquals('{"p1":"this is p1","key":"someKey"}', $result);

        $keyProp->encodeWith('order:10');
        $result = json_encode($coder->encode($source));
        $this->assertEquals('{"key":"someKey","p1":"this is p1"}', $result);
    }

    public function testJsonScalarFromArray()
    {
        $coder = new Encoder();
        $coder->addProperty(
            Property::make('toScalar')
                ->as('a1')
                ->encodeWith('scalar')
        );

        $coder->bind(DefaultConfig::class);
        $source = new DefaultConfig();
        $source->a1 = ['prop1'];
        $result = $coder->encode($source);
        $expect = new stdClass();
        $expect->toScalar = 'prop1';
        $this->assertEquals($expect, $result);
    }

    public function testJsonTransformClosure()
    {
        $rule = EncoderRule::make(
            'transform',
            function ($value) {
                return strtoupper($value);
            }
        );
        $coder = new Encoder();
        $coder->addProperty(Property::make('key')->encodeWith($rule));
        $coder->addProperty(Property::make('p1'));

        $coder->bind(DefaultConfig::class);
        $source = new DefaultConfig();
        $source->key = 'someKey';
        $source->p1 = 'this is p1';
        $result = $coder->encode($source);
        $expect = new stdClass();
        $expect->key = 'SOMEKEY';
        $expect->p1 = 'this is p1';
        $this->assertEquals($expect, $result);
    }

    public function testJsonTransformMethod()
    {
        $rule = EncoderRule::make('transform', 'rev');
        $coder = new Encoder();
        $coder->addProperty(Property::make('key')->encodeWith($rule));
        $coder->addProperty(Property::make('p1'));

        $coder->bind(DefaultConfig::class);
        $source = new DefaultConfig();
        $source->key = 'someKey';
        $source->p1 = 'this is p1';
        $result = $coder->encode($source);
        $expect = new stdClass();
        $expect->key = 'yeKemos';
        $expect->p1 = 'this is p1';
        $this->assertEquals($expect, $result);
    }

    public function testJsonTransformMethod_bad()
    {
        $rule = EncoderRule::make('transform', 'foobar');
        $coder = new Encoder();
        $coder->addProperty(Property::make('key')->encodeWith($rule));
        $coder->addProperty(Property::make('p1'));

        $coder->bind(DefaultConfig::class);
        $source = new DefaultConfig();
        $source->key = 'someKey';
        $source->p1 = 'this is p1';
        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage('foobar not found');
        $result = $coder->encode($source);
    }

    public function testJsonTransformThenDrop()
    {
        $rule = EncoderRule::make(
            'transform',
            function ($value) {
                $value = false;
                return $value;
            }
        );
        $coder = new Encoder();
        $coder->addProperty(Property::make('key')
            ->encodeWith([$rule, 'drop:false']));
        $coder->addProperty(Property::make('p1'));

        $coder->bind(DefaultConfig::class);
        $source = new DefaultConfig();
        $source->key = 'someKey';
        $source->p1 = 'this is p1';
        $result = $coder->encode($source);
        $expect = new stdClass();
        $expect->p1 = 'this is p1';
        $this->assertEquals($expect, $result);
    }

    public function test__construct()
    {
        $props = [
            'p0' => $this->createStub(Property::class),
            'p1' => $this->createStub(Property::class)
        ];
        $coder = new Encoder($props);
        $this->assertEquals($props, $coder->getProperties());
    }

    /** @noinspection PhpParamsInspection */
    public function test__construct_bad()
    {
        $this->expectException(HydrationException::class);
        $coder = new Encoder([1, 2, 3]);
    }

}
