<?php
/** @noinspection ALL */

namespace Abivia\Hydration\Test\Objects;


class DefaultConfig
{
    public array $a1 = [];
    public string $key;
    public ?object $o1 = null;
    public string $p1;
    protected string $p2;
    private string $p3;

    /**
     * Simulate a hydrator.
     * @param object $config
     */
    public function hydrate(object $config, $options = []): bool
    {
        $this->key = $config->key;
        $this->p1 = $config->p1;

        return true;
    }

    public static function makeP1(string $key, string $p1): DefaultConfig
    {
        $obj = new self();
        $obj->key = $key;
        $obj->p1 = $p1;
        return $obj;
    }

    private function rev($value) {
        return strrev($value);
    }

}