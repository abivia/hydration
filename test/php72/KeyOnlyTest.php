<?php

namespace Abivia\Configurable\Tests\Php72;

class ConfigKeyOnly
{
    use \Abivia\Configurable\Configurable;

    /**
     * @var array
     */
    public $keyOnly;

    protected function configureClassMap($property, $value)
    {
        if ($property === 'keyOnly') {
            return ['className' => 'stdClass', 'key' => 'name'];
        }
        return false;
    }
}

/**
 * Test creating an associative array of Stdclass objects.
 */
class KeyOnlyTest extends \PHPUnit\Framework\TestCase
{
    public function testConstruct()
    {
        $input = new \stdClass();
        $input->keyOnly = [];

        $ko1 = new \stdClass;
        $ko1->name = 'ele1';
        $ko1->anyData = 'some random property';
        $input->keyOnly[] = $ko1;

        $ko2 = new \stdClass;
        $ko2->name = 'ele2';
        $ko2->someOtherData = 'another random property';
        $input->keyOnly[] = $ko2;

        $testObj = new ConfigKeyOnly();
        $testObj->configure($input);
        $this->assertEquals(2, count($testObj->keyOnly));
        $this->assertTrue(isset($testObj->keyOnly['ele1']));
        $this->assertEquals($ko1, $testObj->keyOnly['ele1']);
        $this->assertTrue(isset($testObj->keyOnly['ele2']));
        $this->assertEquals($ko2, $testObj->keyOnly['ele2']);
    }

}