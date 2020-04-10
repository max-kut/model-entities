<?php

namespace Entities;

use Entities\Contracts\Entity as EntityContract;
use Entities\Contracts\EntityCollection as EntityCollectionContract;
use ArrayAccess;
use ArrayIterator;
use Countable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Entities\Support\Arr;
use InvalidArgumentException;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

abstract class EntityCollection implements EntityCollectionContract,
    ArrayAccess,
    JsonSerializable,
    Jsonable,
    Arrayable,
    Countable,
    IteratorAggregate
{
    /**
     * The items contained in the collection.
     *
     * @var array
     */
    protected $items = [];
    
    /**
     * Create a new collection.
     *
     * @param mixed $items
     *
     * @return void
     */
    public function __construct($items = [])
    {
        $this->items = $this->getNestedItems($items);
    }
    
    /**
     * @param array|static $attributes
     *
     * @return static
     */
    public static function make($attributes = [])
    {
        return new static($attributes);
    }
    
    /**
     * Get all of the items in the collection.
     *
     * @return array
     */
    public function all()
    {
        return $this->items;
    }
    
    /**
     * @param $items
     *
     * @return array
     * @uses makeNestedItem
     */
    protected function getNestedItems($items): array
    {
        if (is_string($items)) {
            return array_map([$this, 'makeNestedItem'], json_decode($items, true));
        } else if (is_array($items)) {
            return array_map([$this, 'makeNestedItem'], $items);
        } else if ($items instanceof self) {
            return $items->all();
        } else if ($items instanceof Arrayable) {
            return array_map([$this, 'makeNestedItem'], $items->toArray());
        }
        
        return (array)$items;
    }
    
    /**
     * @param $nestedItem
     *
     * @return mixed
     */
    protected function makeNestedItem($nestedItem)
    {
        $className = $this->getNestedClassName();
        if (class_exists($className) && in_array(EntityContract::class, class_implements($className))) {
            return $className::make($nestedItem);
        }
        throw new InvalidArgumentException(sprintf('Nested item "%s" must implemented %s',
            $className, EntityContract::class));
    }
    
    /**
     * Push an item onto the end of the collection.
     *
     * @param mixed $value
     *
     * @return $this
     */
    public function push(EntityContract $value)
    {
        $this->offsetSet(null, $value);
        
        return $this;
    }
    
    /**
     * Get and remove the last item from the collection.
     *
     * @return mixed
     */
    public function pop()
    {
        return array_pop($this->items);
    }
    
    /**
     * Push an item onto the beginning of the collection.
     *
     * @param mixed $value
     * @param mixed $key
     *
     * @return $this
     */
    public function prepend(EntityContract $value, $key = null)
    {
        $this->items = Arr::prepend($this->items, $value, $key);
        
        return $this;
    }
    
    /**
     * Get and remove an item from the collection.
     *
     * @param mixed $key
     * @param mixed $default
     *
     * @return mixed
     */
    public function pull($key, $default = null)
    {
        return Arr::pull($this->items, $key, $default);
    }
    
    /**
     * Put an item in the collection by key.
     *
     * @param mixed $key
     * @param mixed $value
     *
     * @return $this
     */
    public function put($key, EntityContract $value)
    {
        $this->offsetSet($key, $value);
        
        return $this;
    }
    
    /**
     * Reverse items order.
     *
     * @return static
     */
    public function reverse()
    {
        return new static(array_reverse($this->items, true));
    }
    
    /**
     * Get and remove the first item from the collection.
     *
     * @return mixed
     */
    public function shift()
    {
        return array_shift($this->items);
    }
    
    /**
     * Reset the keys on the underlying array.
     *
     * @return static
     */
    public function values()
    {
        return new static(array_values($this->items));
    }
    
    /**
     * Determine if an item exists at an offset.
     *
     * @param mixed $key
     *
     * @return bool
     */
    public function offsetExists($key)
    {
        return array_key_exists($key, $this->items);
    }
    
    /**
     * Get an item at a given offset.
     *
     * @param mixed $key
     *
     * @return mixed
     */
    public function offsetGet($key)
    {
        return $this->items[$key];
    }
    
    /**
     * Set the item at a given offset.
     *
     * @param mixed $key
     * @param mixed $value
     *
     * @return void
     */
    public function offsetSet($key, $value)
    {
        $value = $this->makeNestedItem($value);
        if (is_null($key)) {
            $this->items[] = $value;
        } else {
            $this->items[$key] = $value;
        }
    }
    
    /**
     * Unset the item at a given offset.
     *
     * @param string $key
     *
     * @return void
     */
    public function offsetUnset($key)
    {
        unset($this->items[$key]);
    }
    
    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray()
    {
        return array_map(function ($value) {
            return $value instanceof Arrayable ? $value->toArray() : $value;
        }, $this->items);
    }
    
    /**
     * Convert the object to its JSON representation.
     *
     * @param int $options
     *
     * @return string
     */
    public function toJson($options = 0)
    {
        return json_encode($this->jsonSerialize(), $options);
    }
    
    /**
     * Convert the collection to its string representation.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->toJson();
    }
    
    /**
     * Specify data which should be serialized to JSON
     *
     * @link https://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     * @since 5.4.0
     */
    public function jsonSerialize()
    {
        return array_map(function ($value) {
            if ($value instanceof JsonSerializable) {
                return $value->jsonSerialize();
            } else if ($value instanceof Arrayable) {
                return $value->toArray();
            } else if ($value instanceof Jsonable) {
                return json_decode($value->toJson(), true);
            }
            
            return $value;
        }, $this->items);
    }
    
    /**
     * Count elements of an object
     *
     * @link https://php.net/manual/en/countable.count.php
     * @return int The custom count as an integer.
     * </p>
     * <p>
     * The return value is cast to an integer.
     * @since 5.1.0
     */
    public function count()
    {
        return count($this->items);
    }
    
    /**
     * Retrieve an external iterator
     *
     * @link https://php.net/manual/en/iteratoraggregate.getiterator.php
     * @return Traversable An instance of an object implementing <b>Iterator</b> or
     * <b>Traversable</b>
     * @since 5.0.0
     */
    public function getIterator()
    {
        return new ArrayIterator($this->items);
    }
}
