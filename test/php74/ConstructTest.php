<?php

namespace Abivia\Configurable\Tests\Php74;

class ConfigConstruct
{
    use \Abivia\Configurable\Configurable;

    /**
     * @var Constructable
     */
    public ?Constructable $anObject = null;

    /**
     *
     * @var DateInterval
     */
    public ?\DateInterval $anInterval = null;

    public $badConstruct;

    public $nope;

    protected function configureClassMap($property, $value)
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
    use \Abivia\Configurable\Configurable;

    public $sub;

    protected function configureClassMap($property, $value)
    {
        if ($property === 'sub') {
            return ConfigConstruct::class;
        }
        return false;
    }
}

class ConstructTest extends \PHPUnit\Framework\TestCase
{
    public function testConstruct()
    {
        $input = new \stdClass();
        $input->anInterval = 'P3M';
        $testObj = new ConfigConstruct();
        $testObj->configure($input);
        $this->assertInstanceOf(\DateInterval::class, $testObj->anInterval);
        $this->assertEquals(3, $testObj->anInterval->m);
    }

    public function testBadUnpack1()
    {
        $input = new \stdClass();
        $input->badConstruct = 1;
        $testObj = new ConfigConstruct();
        $testObj->configure($input);
        $errors = $testObj->configureGetErrors();
        $this->assertEquals(2, count($errors));
        $this->assertEquals(
            0,
            strpos(
                $errors[0],
                'Unable to construct: Too few arguments to function'
                . ' Abivia\Configurable\Tests\Php74\Constructable::__construct(), 1 passed'
            )
        );
    }

    public function testBadUnpack2()
    {
        $input = new \stdClass();
        $input->badConstruct = [1];
        $testObj = new ConfigConstruct();
        $testObj->configure($input);
        $errors = $testObj->configureGetErrors();
        $this->assertEquals(2, count($errors));
        $this->assertEquals('Unable to configure property "badConstruct":', $errors[0]);
        $this->assertTrue(
            strpos(
                $errors[1],
                'Unable to construct: Too few arguments to function'
                . ' Abivia\Configurable\Tests\Php74\Constructable::__construct(), 1 passed'
            ) === 0
        );
    }

    public function testNoClass()
    {
        $input = new \stdClass();
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
        $sub = new \stdClass();
        $sub->badConstruct = [1];
        $input = new \stdClass;
        $input->sub = $sub;
        $testObj = new NestedConstruct();
        $testObj->configure($input);
        $errors = $testObj->configureGetErrors();
        $this->assertEquals(3, count($errors));
        $this->assertEquals('Unable to configure property "sub":', $errors[0]);
        $this->assertEquals('Unable to configure property "badConstruct":', $errors[1]);
        $this->assertTrue(
            strpos(
                $errors[2],
                'Unable to construct: Too few arguments to function'
                . ' Abivia\Configurable\Tests\Php74\Constructable::__construct(),'
                . ' 1 passed'
            ) === 0
        );
    }

    public function testConstructUnpack()
    {
        $input = new \stdClass();
        $input->anObject = ['one', 'two'];
        $testObj = new ConfigConstruct();
        $testObj->configure($input);
        $this->assertInstanceOf(Constructable::class, $testObj->anObject);
        $this->assertEquals('one', $testObj->anObject->a);
        $this->assertEquals('two', $testObj->anObject->b);
    }

}