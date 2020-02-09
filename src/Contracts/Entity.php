<?php

namespace Entities\Contracts;

interface Entity
{
    /**
     * @param array|static $attributes
     * @return static
     */
    public static function make($attributes);
}
