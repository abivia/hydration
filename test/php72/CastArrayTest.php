<?php

namespace Abivia\Configurable\Tests\Php72;

class ConfigCastArray
{
    use \Abivia\Configurable\Configurable;

    /**
     *
     * @var mixed Always validates.
     */
    public $allGood;

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

    protected function configureValidate($property, &$value)
    {
        if ($property === 'simple') {
            foreach ($value as $element) {
                 if (substr($element, 0, 7) !== 'this is') {
                    return false;
                 }
            }
        }
        return true;
    }
}

/**
 * Test creating an simple array from a nested class
 */
class CastArrayTest extends \PHPUnit\Framework\TestCase
{
    public function testConfigure()
    {
        $json = '{"simple": { "a": "this is a", "*": "this is *"}}';

        $testObj = new ConfigCastArray();
        $result = $testObj->configure(json_decode($json));
        if (!$result) {
            print_r($testObj->configureGetErrors());
        }
        $this->assertTrue($result);
        $this->assertEquals(2, count($testObj->simple));
        $this->assertTrue(isset($testObj->simple['a']));
        $this->assertEquals('this is a', $testObj->simple['a']);
        $this->assertTrue(isset($testObj->simple['*']));
        $this->assertEquals('this is *', $testObj->simple['*']);
    }

    public function testConfigureBad()
    {
        $json = '{"simple": { "a": "this is a", "*": "this no good"}}';

        $testObj = new ConfigCastArray();
        $result = $testObj->configure(json_decode($json));
        $this->assertFalse($result);
    }

    /**
     * Make sure a valid property doesn't mask an invalid one
     */
    public function testConfigureBad2()
    {
        $json = '{"simple": { "a": "this is a", "*": "this no good"}, "allgood":1}';

        $testObj = new ConfigCastArray();
        $result = $testObj->configure(json_decode($json));
        $this->assertFalse($result);
    }

}