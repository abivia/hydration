<?php
/** @noinspection ALL */

namespace Abivia\Hydration\Test\Objects;

class ConstructOneArg
{
    /**
     * @var mixed Something passed via constructor.
     */
    public $arg;

    function __construct($arg)
    {
        $this->arg = $arg;
    }
}