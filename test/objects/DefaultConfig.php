<?php

namespace Abivia\Hydration\Test\Objects;


class DefaultConfig
{
    public string $key;
    public string $p1;
    protected string $p2;
    private string $p3;

    /**
     * Simulate a hydrator.
     * @param object $config
     */
    public function hydrate(object $config, $options = [])
    {
        $this->key = $config->key;
        $this->p1 = $config->p1;
    }

}