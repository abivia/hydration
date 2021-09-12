<?php
/** @noinspection ALL */

namespace Abivia\Hydration\Test;

use Abivia\Hydration\Hydratable;
use PHPUnit\Framework\TestCase;
use \Abivia\Hydration\Hydrator;

class BindingParent implements Hydratable
{
    public BindingChild $child;

    private static Hydrator $hydrator;

    public function hydrate($config, ?array $options = []): bool
    {
        if (!isset(self::$hydrator)) {
            self::$hydrator = Hydrator::make(self::class);
        }
        return self::$hydrator->hydrate($this, $config, $options);
    }
}

class BindingChild implements Hydratable
{
    public $foo;

    private static Hydrator $hydrator;

    public function hydrate($config, ?array $options = []): bool
    {
        if (!isset(self::$hydrator)) {
            self::$hydrator = Hydrator::make(self::class);
        }
        return self::$hydrator->hydrate($this, $config, $options);
    }
}

class ExampleBindingTest extends TestCase
{
    public function testExample()
    {
        $json = '{"child": { "foo": "this is foo"}}';
        $obj = new BindingParent();
        $obj->hydrate($json);
        $this->assertEquals('this is foo', $obj->child->foo);
    }
}
