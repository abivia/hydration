<?php

namespace Abivia\Configurable\Tests\Php72;

use Abivia\Configurable\Configurable;
use DateInterval;
use PHPUnit\Framework\TestCase;
use stdClass;

class ConfigConstruct
{
    use Configurable;

    /**
     * @var Constructable
     */
    public $anObject;

    /**
     *
     * @var DateInterval
     */
    public $anInterval;

    public $badConstruct;

    public $nope;

    protected function configureClassMap(string $property, $value)
    {
        if ($property === 'anObject') {
            return ['className' => Constructable::class, 'constructUnpack' => true];
        }
        if ($property === 'anInterval') {
            return ['className' => 'DateInterval', 'construct' => true];
        }
        if ($property === 'badConstruct') {
            return ['className' => Constructable::class, 'constructUnpack' => true];
        }
        if ($property === 'nope') {
            return ['className' => 'Nonexistent', 'construct' => true];
        }
        return false;
    }

    protected function configureValidate(string $property, &$value)
    {
        if ($property === 'anObject') {
            foreach ($value as $element) {
                if (strlen($element) > 3) {
                    return false;
                }
            }
        }
        return true;
    }

}

class Constructable
{
    public $a;
    public $b;

    public function __construct($a, $b) {
        $this->a = $a;
        $this->b = $b;
    }
}

class NestedConstruct
{
    use Configurable;

    public $sub;

    protected function configureClassMap(string $property, $value)
    {
        if ($property === 'sub') {
            return ConfigConstruct::class;
        }
        return false;
    }
}

class ConstructTest extends TestCase
{
    public function testConstruct()
    {
        $input = new stdClass();
        $input->anInterval = 'P3M';
        $testObj = new ConfigConstruct();
        $testObj->configure($input);
        $this->assertInstanceOf(DateInterval::class, $testObj->anInterval);
        $this->assertEquals(3, $testObj->anInterval->m);
    }

    public function testBadUnpack1()
    {
        $input = new stdClass();
        $input->badConstruct = 1;
        $testObj = new ConfigConstruct();
        $testObj->configure($input);
        $errors = $testObj->configureGetErrors();
        $this->assertCount(2, $errors);
        $this->assertTrue(
            strpos(
                $errors[1],
                'Unable to construct: Too few arguments to function'
                . ' Abivia\Configurable\Tests\Php72\Constructable::__construct(), 1 passed'
            ) === 0
        );
    }

    public function testBadUnpack2()
    {
        $input = new stdClass();
        $input->badConstruct = [1];
        $testObj = new ConfigConstruct();
        $testObj->configure($input);
        $errors = $testObj->configureGetErrors();
        $this->assertCount(2, $errors);
        $this->assertEquals('Unable to configure property "badConstruct":', $errors[0]);
        $this->assertTrue(
            strpos(
                $errors[1],
                'Unable to construct: Too few arguments to function'
                . ' Abivia\Configurable\Tests\Php72\Constructable::__construct(), 1 passed'
            ) === 0
        );
    }

    public function testBadUnpack3()
    {
        // Anobject instances must have string lengths <= 3.
        $input = new stdClass();
        $input->anObject = ['one', 'toomany'];
        $testObj = new ConfigConstruct();
        $result = $testObj->configure($input);
        $this->assertFalse($result);
    }

    public function testNoClass()
    {
        $input = new stdClass();
        $input->nope = 'P3M';
        $testObj = new ConfigConstruct();
        $testObj->configure($input);
        $this->assertEquals(
            [
                'Unable to configure property "nope":',
                'Class not found: Nonexistent'
            ],
            $testObj->configureGetErrors()
        );
    }

    public function testNestedBadUnpack()
    {
        $sub = new stdClass();
        $sub->badConstruct = [1];
        $input = new stdClass;
        $input->sub = $sub;
        $testObj = new NestedConstruct();
        $testObj->configure($input);
        $errors = $testObj->configureGetErrors();
        $this->assertCount(3, $errors);
        $this->assertEquals('Unable to configure property "sub":', $errors[0]);
        $this->assertEquals('Unable to configure property "badConstruct":', $errors[1]);
        $this->assertTrue(
            strpos(
                $errors[2],
                'Unable to construct: Too few arguments to function'
                . ' Abivia\Configurable\Tests\Php72\Constructable::__construct(), 1 passed'
            ) === 0
        );
    }

    public function testConstructUnpack()
    {
        $input = new stdClass();
        $input->anObject = ['one', 'two'];
        $testObj = new ConfigConstruct();
        $testObj->configure($input);
        $this->assertInstanceOf(Constructable::class, $testObj->anObject);
        $this->assertEquals('one', $testObj->anObject->a);
        $this->assertEquals('two', $testObj->anObject->b);
    }

}