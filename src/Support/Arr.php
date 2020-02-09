<?php

namespace Entities\Support;

use ArrayAccess;

/**
 * Class Arr
 * Copies some methods Illuminate\Support\Arr
 */
class Arr
{
    /**
     * Determine whether the given value is array accessible.
     *
     * @param  mixed  $value
     * @return bool
     */
    public static function accessible($value)
    {
        return is_array($value) || $value instanceof ArrayAccess;
    }
    
    /**
     * Determine if the given key exists in the provided array.
     *
     * @param  \ArrayAccess|array  $array
     * @param  string|int  $key
     * @return bool
     */
    public static function exists($array, $key)
    {
        if ($array instanceof ArrayAccess) {
            return $array->offsetExists($key);
        }
        
        return array_key_exists($key, $array);
    }
    
    /**
     * Remove one or many array items from a given array using "dot" notation.
     *
     * @param  array  $array
     * @param  array|string  $keys
     * @return void
     */
    public static function forget(&$array, $keys)
    {
        $original = &$array;
        
        $keys = (array) $keys;
        
        if (count($keys) === 0) {
            return;
        }
        
        foreach ($keys as $key) {
            // if the exact key exists in the top-level, remove it
            if (static::exists($array, $key)) {
                unset($array[$key]);
                
                continue;
            }
            
            $parts = explode('.', $key);
            
            // clean up before each pass
            $array = &$original;
            
            while (count($parts) > 1) {
                $part = array_shift($parts);
                
                if (isset($array[$part]) && is_array($array[$part])) {
                    $array = &$array[$part];
                } else {
                    continue 2;
                }
            }
            
            unset($array[array_shift($parts)]);
        }
    }
    
    /**
     * Get an item from an array using "dot" notation.
     *
     * @param  \ArrayAccess|array  $array
     * @param  string|int  $key
     * @param  mixed   $default
     * @return mixed
     */
    public static function get($array, $key, $default = null)
    {
        if (! static::accessible($array)) {
            return Helpers::value($default);
        }
        
        if (is_null($key)) {
            return $array;
        }
        
        if (static::exists($array, $key)) {
            return $array[$key];
        }
        
        if (strpos($key, '.') === false) {
            return $array[$key] ?? Helpers::value($default);
        }
        
        foreach (explode('.', $key) as $segment) {
            if (static::accessible($array) && static::exists($array, $segment)) {
                $array = $array[$segment];
            } else {
                return Helpers::value($default);
            }
        }
        
        return $array;
    }
    
    /**
     * Get a value from the array, and remove it.
     *
     * @param  array   $array
     * @param  string  $key
     * @param  mixed   $default
     * @return mixed
     */
    public static function pull(&$array, $key, $default = null)
    {
        $value = static::get($array, $key, $default);
        
        static::forget($array, $key);
        
        return $value;
    }
    
    /**
     * Push an item onto the beginning of an array.
     *
     * @param  array  $array
     * @param  mixed  $value
     * @param  mixed  $key
     * @return array
     */
    public static function prepend($array, $value, $key = null)
    {
        if (is_null($key)) {
            array_unshift($array, $value);
        } else {
            $array = [$key => $value] + $array;
        }
        
        return $array;
    }
}
