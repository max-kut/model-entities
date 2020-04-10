<?php

namespace Entities\Contracts;

interface EntityCollection
{
    /**
     * @param array|static $attributes
     * @return static
     */
    public static function make($attributes);
    
    /**
     * @return string
     */
    public function getNestedClassName(): string;
}
