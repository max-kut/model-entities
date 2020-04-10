<?php

namespace Entities;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeInterface;
use JsonSerializable;
use Entities\Contracts\Entity as EntityContract;
use Entities\Contracts\EntityCollection as EntityCollectionContract;
use Entities\Exceptions\NotDefinedPropertyException;
use Entities\Support\Str;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use RuntimeException;

abstract class Entity implements EntityContract, JsonSerializable, Jsonable, Arrayable
{
    /**
     * The cache of the mutated attributes for each class.
     *
     * @var array
     */
    protected $mutatorCache = [];
    
    /**
     * @var array
     */
    protected $attributes = [];
    
    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [];
    
    /**
     * @var bool $strictParams - Возмолжность добавлять свойства, которые не определены в сущности
     */
    public $strictParams = true;
    
    protected $dateFormat;
    
    /**
     * AbstractEntity constructor.
     *
     * @param array $attributes
     */
    public function __construct($attributes)
    {
        foreach ($this->makeAttributes($attributes) as $key => $value) {
            $this->setAttribute($key, $value);
        }
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
     * @param $attributes
     *
     * @return array
     */
    private function makeAttributes($attributes): array
    {
        if (is_string($attributes)) {
            return json_decode($attributes, true);
        } else if ($attributes instanceof Arrayable) {
            return $attributes->toArray();
        }
        
        return (array)$attributes;
    }
    
    /**
     * @param $name
     *
     * @return mixed
     */
    public function __get($name)
    {
        return $this->getAttribute($name);
    }
    
    /**
     * @param $name
     * @param $value
     *
     * @throws \Exception
     */
    public function __set($name, $value): void
    {
        $this->setAttribute($name, $value);
    }
    
    /**
     * @param $name
     *
     * @return bool
     */
    public function __isset($name)
    {
        return array_key_exists($name, $this->attributes) || $this->hasCast($name) ||
            method_exists($this, 'get' . Str::studly($name) . 'Attribute');
    }
    
    /**
     * @param $name
     */
    public function __unset($name)
    {
        unset($this->attributes[$name]);
    }
    
    /**
     * @param $key
     *
     * @return mixed
     */
    protected function getAttribute($key)
    {
        $value = $this->attributes[$key] ?? null;
        
        if ($this->hasGetMutator($key)) {
            return $this->mutateAttribute($key, $value);
        }
        
        if ($this->hasCast($key)) {
            return $this->castAttribute($key, $value);
        }
        
        if (!array_key_exists($key, $this->attributes) && $this->strictParams) {
            throw new NotDefinedPropertyException(
                sprintf('in %s not exists "%s" property', static::class, $key)
            );
        }
        
        return $value;
    }
    
    /**
     * @param $key
     *
     * @return bool
     */
    protected function hasGetMutator($key)
    {
        return method_exists($this, 'get' . Str::studly($key) . 'Attribute');
    }
    
    /**
     * @param $key
     * @param $value
     *
     * @return mixed
     */
    protected function mutateAttribute($key, $value)
    {
        return array_key_exists($key, $this->mutatorCache)
            ? $this->mutatorCache[$key]
            : $this->mutatorCache[$key] = $this->{'get' . Str::studly($key) . 'Attribute'}($value);
    }
    
    /**
     * Determine whether an attribute should be cast to a native type.
     *
     * @param string $key
     * @param array|string|null $types
     *
     * @return bool
     */
    protected function hasCast($key, $types = null): bool
    {
        if (array_key_exists($key, $this->casts)) {
            return $types ? in_array($this->getCastType($key), (array)$types, true) : true;
        }
        
        return false;
    }
    
    /**
     * Get the type of cast for a model attribute.
     *
     * @param string $key
     *
     * @return string
     */
    protected function getCastType($key)
    {
        if ($this->isCustomDateTimeCast($this->casts[$key])) {
            return 'custom_datetime';
        }
        
        if ($this->isDecimalCast($this->casts[$key])) {
            return 'decimal';
        }
        
        return trim(strtolower($this->casts[$key]));
    }
    
    /**
     * Determine if the cast type is a custom date time cast.
     *
     * @param string $cast
     *
     * @return bool
     */
    protected function isCustomDateTimeCast($cast)
    {
        return strncmp($cast, 'date:', 5) === 0 ||
            strncmp($cast, 'datetime:', 9) === 0;
    }
    
    /**
     * Determine if the cast type is a decimal cast.
     *
     * @param string $cast
     *
     * @return bool
     */
    protected function isDecimalCast($cast)
    {
        return strncmp($cast, 'decimal:', 8) === 0;
    }
    
    /**
     * Cast an attribute to a native PHP type.
     *
     * @param string $key
     * @param mixed $value
     *
     * @return mixed
     */
    protected function castAttribute($key, $value)
    {
        if (is_null($value)) {
            return $value;
        }
        
        switch ($this->getCastType($key)) {
            case 'int':
            case 'integer':
                return (int)$value;
            case 'real':
            case 'float':
            case 'double':
                return $this->fromFloat($value);
            case 'decimal':
                return $this->asDecimal($value, explode(':', $this->casts[$key], 2)[1]);
            case 'string':
                return (string)$value;
            case 'bool':
            case 'boolean':
                return (bool)$value;
            case 'object':
                return $this->fromJson($value, true);
            case 'array':
            case 'json':
                return $this->fromJson($value);
            case 'date':
                return $this->asDate($value);
            case 'datetime':
            case 'custom_datetime':
                return $this->asDateTime($value);
            case 'timestamp':
                return $this->asTimestamp($value);
            default:
                // Если в casts указан класс, который имплементирует EntityContract, то создадим объект этого класса
                if ($this->isNestedOptionObject($key)) {
                    return $this->casts[$key]::make($value);
                }
                
                return $value;
        }
    }
    
    /**
     * Является ли вложенный элемент объектом класса опций
     *
     * @param $key
     *
     * @return bool
     */
    protected function isNestedOptionObject($key): bool
    {
        if (class_exists($this->casts[$key])) {
            $interfaces = class_implements($this->casts[$key]);
        
            return in_array(EntityContract::class, $interfaces) ||
                in_array(EntityCollectionContract::class, $interfaces);
        }
    
        return false;
    }
    
    /**
     * Decode the given float.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    protected function fromFloat($value)
    {
        switch ((string)$value) {
            case 'Infinity':
                return INF;
            case '-Infinity':
                return -INF;
            case 'NaN':
                return NAN;
            default:
                return (float)$value;
        }
    }
    
    /**
     * Return a decimal as string.
     *
     * @param float $value
     * @param int $decimals
     *
     * @return string
     */
    protected function asDecimal($value, $decimals)
    {
        return number_format($value, $decimals, '.', '');
    }
    
    /**
     * Return a timestamp as DateTime object with time set to 00:00:00.
     *
     * @param mixed $value
     *
     * @return \Carbon\Carbon
     */
    protected function asDate($value)
    {
        return $this->asDateTime($value)->startOfDay();
    }
    
    /**
     * Return a timestamp as DateTime object.
     *
     * @param mixed $value
     *
     * @return \Carbon\Carbon
     */
    protected function asDateTime($value)
    {
        // If this value is already a Carbon instance, we shall just return it as is.
        // This prevents us having to re-instantiate a Carbon instance when we know
        // it already is one, which wouldn't be fulfilled by the DateTime check.
        if ($value instanceof Carbon || $value instanceof CarbonInterface) {
            return Carbon::instance($value);
        }
        
        // If the value is already a DateTime instance, we will just skip the rest of
        // these checks since they will be a waste of time, and hinder performance
        // when checking the field. We will just return the DateTime right away.
        if ($value instanceof DateTimeInterface) {
            return Carbon::parse(
                $value->format('Y-m-d H:i:s.u'), $value->getTimezone()
            );
        }
        
        // If this value is an integer, we will assume it is a UNIX timestamp's value
        // and format a Carbon object from this timestamp. This allows flexibility
        // when defining your date fields as they might be UNIX timestamps here.
        if (is_numeric($value)) {
            return Carbon::createFromTimestamp($value);
        }
        
        // If the value is in simply year, month, day format, we will instantiate the
        // Carbon instances from that format. Again, this provides for simple date
        // fields on the database, while still supporting Carbonized conversion.
        if ($this->isStandardDateFormat($value)) {
            return Carbon::instance(Carbon::createFromFormat('Y-m-d', $value)->startOfDay());
        }
        
        $format = $this->getDateFormat();
        
        // https://bugs.php.net/bug.php?id=75577
        if (version_compare(PHP_VERSION, '7.3.0-dev', '<')) {
            $format = str_replace('.v', '.u', $format);
        }
        
        // Finally, we will just assume this date is in the format used by default on
        // the database connection and use that format to create the Carbon object
        // that is returned back out to the developers after we convert it here.
        return Carbon::createFromFormat($format, $value);
    }
    
    /**
     * Determine if the given value is a standard date format.
     *
     * @param string $value
     *
     * @return bool
     */
    protected function isStandardDateFormat($value)
    {
        return preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $value);
    }
    
    /**
     * Convert a DateTime to a storable string.
     *
     * @param mixed $value
     *
     * @return string|null
     */
    protected function fromDateTime($value)
    {
        return empty($value)
            ? $value
            : $this->asDateTime($value)->format($this->getDateFormat());
    }
    
    /**
     * Return a timestamp as unix timestamp.
     *
     * @param mixed $value
     *
     * @return int
     */
    protected function asTimestamp($value)
    {
        return $this->asDateTime($value)->getTimestamp();
    }
    
    /**
     * Get the format for database stored dates.
     *
     * @return string
     */
    protected function getDateFormat()
    {
        return $this->dateFormat ?: 'Y-m-d H:i:s';
    }
    
    /**
     * Decode the given JSON back into an array or object.
     *
     * @param string $value
     * @param bool $asObject
     *
     * @return mixed
     */
    protected function fromJson($value, $asObject = false)
    {
        return json_decode($value, !$asObject);
    }
    
    /**
     * @param $key
     * @param $value
     */
    protected function setAttribute($key, $value): void
    {
        $this->validateSetAttribute($key);
        
        unset($this->mutatorCache[$key]);
        
        if (method_exists($this, $method = 'set' . Str::studly($key) . 'Attribute')) {
            $this->{$method}($value);
            
            return;
        } else if ($this->hasCast($key)) {
            if ($value && $this->isDateCastable($key)) {
                $value = $this->fromDateTime($value);
            }
            if ($this->isJsonCastable($key) && !is_null($value)) {
                $value = $this->castAttributeAsJson($key, $value);
            }
            if ($this->isNestedOptionObject($key)) {
                $value = $this->casts[$key]::make($value)->toArray();
            }
        }
        $this->attributes[$key] = $value;
    }
    
    /**
     * @param $key
     */
    private function validateSetAttribute($key): void
    {
        if (!$this->strictParams) {
            return;
        }
        if (!array_key_exists($key, $this->attributes) &&
            !$this->hasCast($key) &&
            !method_exists($this, 'set' . Str::studly($key) . 'Attribute')) {
            
            throw new NotDefinedPropertyException(
                sprintf('in %s not exists "%s" property', static::class, $key)
            );
        }
    }
    
    /**
     * Determine whether a value is Date / DateTime castable for inbound manipulation.
     *
     * @param string $key
     *
     * @return bool
     */
    protected function isDateCastable($key)
    {
        return $this->hasCast($key, ['date', 'datetime']);
    }
    
    /**
     * Determine whether a value is JSON castable for inbound manipulation.
     *
     * @param string $key
     *
     * @return bool
     */
    protected function isJsonCastable($key)
    {
        return $this->hasCast($key, ['array', 'json', 'object', 'collection']);
    }
    
    /**
     * Cast the given attribute to JSON.
     *
     * @param string $key
     * @param mixed $value
     *
     * @return string
     */
    protected function castAttributeAsJson($key, $value)
    {
        $value = $this->asJson($value);
        
        if ($value === false) {
            throw new RuntimeException(sprintf("Unable to encode attribute [%s] for [%s] to JSON: %s.",
                $key, static::class, json_last_error_msg()));
        }
        
        return $value;
    }
    
    /**
     * Encode the given value as JSON.
     *
     * @param mixed $value
     *
     * @return string
     */
    protected function asJson($value)
    {
        return json_encode($value);
    }
    
    /**
     * Prepare a date for array / JSON serialization.
     *
     * @param \DateTimeInterface $date
     *
     * @return string
     */
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format($this->getDateFormat());
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
        return $this->toArray();
    }
    
    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray()
    {
        $attributes = [];
        foreach (array_keys($this->attributes) as $key) {
            // mutated or casting value
            $value = $this->getAttribute($key);
            
            // If the attribute cast was a date or a datetime, we will serialize the date as
            // a string. This allows the developers to customize how dates are serialized
            // into an array without affecting how they are persisted into the storage.
            $castType = $this->hasCast($key) ? $this->getCastType($key) : null;
            if ($value) {
                if ($castType === 'date' || $castType === 'datetime') {
                    $value = $this->serializeDate($value);
                } else if ($castType == 'custom_datetime') {
                    $value = $value->format(explode(':', $this->casts[$key], 2)[1]);
                }
            }
            
            $attributes[$key] = $value;
        }
        
        return $attributes;
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
     * Convert the model to its string representation.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->toJson();
    }
}
