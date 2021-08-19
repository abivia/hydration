<?php

namespace Abivia\Configurable\Test;

use PHPUnit\Framework\TestCase;
use \Abivia\Hydration\Hydrator;
use \Abivia\Hydration\Property;

class AssociativeArrayObject
{
    private static Hydrator $hydrator;

    public array $list;

    public function hydrate($config)
    {
        if (!isset(self::$hydrator)) {
            self::$hydrator = new Hydrator();
            self::$hydrator
                ->addProperty(
                    Property::make('list')
                        ->bind('stdClass')
                        ->key('code')
                )
                ->bind(self::class);
        }
        self::$hydrator->hydrate($this, $config);
    }
}

class ExampleAssociativeArrayTest extends TestCase
{
    public function testExample()
    {
        $json = '{"list":[
    {"code": "fb", "name": "Facebook"},
    {"code": "tw", "name": "Twitter"},
    {"code": "ig", "name": "Instagram"}
]}
';
        $obj = new AssociativeArrayObject();
        $obj->hydrate(json_decode($json));
        $this->assertEquals(['fb', 'tw', 'ig'], array_keys($obj->list));
        $this->assertEquals('Facebook', $obj->list['fb']->name);
    }
}
