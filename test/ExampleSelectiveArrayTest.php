<?php
/** @noinspection ALL */

namespace Abivia\Hydration\Test;

use PHPUnit\Framework\TestCase;
use \Abivia\Hydration\Hydrator;
use \Abivia\Hydration\Property;

class SelectiveArrayObject
{
    private static Hydrator $hydrator;

    public array $simple;

    public function hydrate($config)
    {
        if (!isset(self::$hydrator)) {
            self::$hydrator = new Hydrator();
            self::$hydrator
                ->addProperty(
                    Property::make('simple')
                        ->toArray()
                )
                ->bind(self::class);
        }
        self::$hydrator->hydrate($this, $config);
    }
}

class ExampleSelectiveArrayTest extends TestCase
{
    public function testExample()
    {
        $json = '{"simple": { "a": "this is a", "*": "this is *"}}';
        $obj = new SelectiveArrayObject();
        $obj->hydrate($json);
        $this->assertEquals(
            [
                'a' => 'this is a',
                '*' => 'this is *',
            ],
            $obj->simple
        );
    }
}
