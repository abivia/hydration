<?php
/** @noinspection ALL */

namespace Abivia\Hydration\Test;

use Abivia\Hydration\Property;
use PHPUnit\Framework\TestCase;
use \Abivia\Hydration\Hydrator;

class SetterObject {

    private static Hydrator $hydrator;

    public string $userName;
    public string $passwordHash;

    public function hydrate($config)
    {
        if (!isset(self::$hydrator)) {
            self::$hydrator = Hydrator::make()
                ->addProperty(
                    Property::make('password')
                    ->as('passwordHash')
                    ->setter('storePassword')
                )
                ->bind(self::class);
        }
        self::$hydrator->hydrate($this, $config);
    }

    public function storePassword(string $password)
    {
        $this->passwordHash = 'hashed-' . $password;
        return true;
    }
}

class ExampleSetterTest extends TestCase
{
    public function testExample()
    {
        $json = '{"userName": "admin", "password": "secret"}';
        $obj = new SetterObject();
        $obj->hydrate($json);
        $this->assertEquals('admin', $obj->userName);
        $this->assertEquals('hashed-secret', $obj->passwordHash);
    }
}
