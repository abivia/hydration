<?php
/** @noinspection ALL */

namespace Abivia\Hydration\Test;

use Abivia\Hydration\Property;
use PHPUnit\Framework\TestCase;
use Abivia\Hydration\Hydrator;

class BasicEncodeObject {

    private static Hydrator $hydrator;

    public string $password;
    public string $userName;

    public function encode()
    {
        return self::$hydrator->encode($this);
    }

    public function hydrate($config)
    {
        if (!isset(self::$hydrator)) {
            self::$hydrator = new Hydrator();
            self::$hydrator
                ->addProperty(
                    Property::make('userName')
                    ->encodeWith('order:10')
                )
                ->addProperty(
                    Property::make('password')
                        ->encodeWith('order:20')
                )
                ->bind(self::class);
        }
        self::$hydrator->hydrate($this, $config);
    }
}

class ExampleEncodeTest extends TestCase
{
    public function testExample()
    {
        $json = '{"password": "secret", "userName": "admin"}';
        $obj = new BasicEncodeObject();
        $obj->hydrate($json);
        $this->assertEquals('admin', $obj->userName);
        $this->assertEquals('secret', $obj->password);
        $encoded = $obj->encode();
        $expectJson = '{"userName":"admin","password":"secret"}';
        $this->assertEquals($expectJson, json_encode($encoded));
    }
}
