<?php

namespace Abivia\Configurable\Tests;

use Abivia\Hydration\HydrationException;
use Abivia\Hydration\Hydrator;
use Abivia\Hydration\Property;
use PHPUnit\Framework\TestCase;
use ReflectionException;

class ConfigCastArray
{
    /**
     * @var mixed Always validates.
     */
    public $allGood;

    protected static Hydrator $hydrator;

    /**
     * @var array
     */
    public array $simple = [];

    /**
     * Hydrate this object.
     * @param object $config
     * @param array $options
     * @return bool|array
     * @throws HydrationException
     * @throws ReflectionException
     */
    public function hydrate(object $config, array $options = [])
    {
        if (!isset(self::$hydrator)) {
            self::$hydrator = Hydrator::make()
                ->addProperty(
                    Property::make('simple')
                        ->toArray()
                        ->validate(function ($value) {
                            return substr($value, 0, 7) === 'this is';
                        })
                )
                ->bind(self::class);
        }
        $result = self::$hydrator->hydrate($this, $config, $options);
        if (!$result) {
            $result = self::$hydrator->getErrors();
        }

        return $result;
    }

}

/**
 * Test creating a simple array from a nested class
 */
class CastArrayTest extends TestCase
{
    public function testConfigure()
    {
        $json = '{"simple": { "a": "this is a", "*": "this is *"}}';

        $testObj = new ConfigCastArray();
        $result = $testObj->hydrate(json_decode($json));
        $this->assertTrue($result);
        $this->assertCount(2, $testObj->simple);
        $this->assertTrue(isset($testObj->simple['a']));
        $this->assertEquals('this is a', $testObj->simple['a']);
        $this->assertTrue(isset($testObj->simple['*']));
        $this->assertEquals('this is *', $testObj->simple['*']);
    }

    public function testConfigureBad()
    {
        $json = '{"simple": { "a": "this is a", "*": "this no good"}}';

        $testObj = new ConfigCastArray();
        $result = $testObj->hydrate(json_decode($json));
        $this->assertNotTrue($result);
    }

    /**
     * Make sure a valid property doesn't mask an invalid one
     */
    public function testConfigureBad2()
    {
        $json = '{"simple": { "a": "this is a", "*": "this no good"}, "allgood":1}';

        $testObj = new ConfigCastArray();
        $result = $testObj->hydrate(json_decode($json));
        $this->assertNotTrue($result);
    }

}