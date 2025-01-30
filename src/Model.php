<?php namespace Jenssegers\Model;

use ArrayAccess;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Collection as BaseCollection;
use JsonException;
use JsonSerializable;

abstract class Model implements ArrayAccess, Arrayable, Jsonable, JsonSerializable
{

    /**
     * The model's attributes.
     *
     * @var array
     */
    protected array $attributes = [];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected array $hidden = [];

    /**
     * The attributes that should be visible in arrays.
     *
     * @var array
     */
    protected array $visible = [];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected array $appends = [];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected array $fillable = [];

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected array $guarded = [];

    /**
     * The attributes that should be casted to native types.
     *
     * @var array
     */
    protected array $casts = [];

    /**
     * Indicates whether attributes are snake cased on arrays.
     *
     * @var bool
     */
    public static bool $snakeAttributes = true;

    /**
     * Indicates if all mass assignment is enabled.
     *
     * @var bool
     */
    protected static bool $unguarded = false;

    /**
     * The cache of the mutated attributes for each class.
     *
     * @var array
     */
    protected static array $mutatorCache = [];

    /**
     * Create a new Eloquent model instance.
     *
     * @param array $attributes
     * @return void
     */
    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
    }

    /**
     * Fill the model with an array of attributes.
     *
     * @param array $attributes
     * @return $this
     *
     * @throws MassAssignmentException
     */
    public function fill(array $attributes): static
    {
        $totallyGuarded = $this->totallyGuarded();

        foreach ($this->fillableFromArray($attributes) as $key => $value) {
            // The developers may choose to place some attributes in the "fillable"
            // array, which means only those attributes may be set through mass
            // assignment to the model, and all others will just be ignored.
            if ($this->isFillable($key)) {
                $this->setAttribute($key, $value);
            } elseif ($totallyGuarded) {
                throw new MassAssignmentException($key);
            }
        }

        return $this;
    }

    /**
     * Fill the model with an array of attributes. Force mass assignment.
     *
     * @param array $attributes
     * @return $this
     */
    public function forceFill(array $attributes): static
    {
        // Since some versions of PHP have a bug that prevents it from properly
        // binding the late static context in a closure, we will first store
        // the model in a variable, which we will then use in the closure.
        $model = $this;

        return static::unguarded(static function () use ($model, $attributes) {
            return $model->fill($attributes);
        });
    }

    /**
     * Get the fillable attributes of a given array.
     *
     * @param  array  $attributes
     * @return array
     */
    protected function fillableFromArray(array $attributes): array
    {
        if (!static::$unguarded && count($this->fillable) > 0) {
            return array_intersect_key($attributes, array_flip($this->fillable));
        }

        return $attributes;
    }

    /**
     * Create a new instance of the given model.
     *
     * @param array $attributes
     * @return Model
     */
    public function newInstance(array $attributes = []): static
    {
        return new static($attributes);
    }

    /**
     * Create a collection of models from plain arrays.
     *
     * @param array $items
     * @return array
     */
    public static function hydrate(array $items): array
    {
        $instance = new static();

        return array_map(static function ($item) use ($instance) {
            return $instance->newInstance($item);
        }, $items);
    }

    /**
     * Get the hidden attributes for the model.
     *
     * @return array
     */
    public function getHidden(): array
    {
        return $this->hidden;
    }

    /**
     * Set the hidden attributes for the model.
     *
     * @param  array  $hidden
     * @return $this
     */
    public function setHidden(array $hidden): static
    {
        $this->hidden = $hidden;

        return $this;
    }

    /**
     * Add hidden attributes for the model.
     *
     * @param array|string|null $attributes
     * @return void
     */
    public function addHidden(null|array|string $attributes = null): void
    {
        $attributes = is_array($attributes) ? $attributes : func_get_args();

        $this->hidden = array_merge($this->hidden, $attributes);
    }

    /**
     * Make the given, typically hidden, attributes visible.
     *
     * @param array|string $attributes
     * @return $this
     */
    public function withHidden(array|string $attributes): static
    {
        $this->hidden = array_diff($this->hidden, (array) $attributes);

        return $this;
    }

    /**
     * Get the visible attributes for the model.
     *
     * @return array
     */
    public function getVisible(): array
    {
        return $this->visible;
    }

    /**
     * Set the visible attributes for the model.
     *
     * @param  array  $visible
     * @return $this
     */
    public function setVisible(array $visible): self
    {
        $this->visible = $visible;

        return $this;
    }

    /**
     * Add visible attributes for the model.
     *
     * @param array|string|null $attributes
     * @return void
     */
    public function addVisible(null|array|string $attributes = null): void
    {
        $attributes = is_array($attributes) ? $attributes : func_get_args();

        $this->visible = array_merge($this->visible, $attributes);
    }

    /**
     * Set the accessors to append to model arrays.
     *
     * @param  array  $appends
     * @return $this
     */
    public function setAppends(array $appends): self
    {
        $this->appends = $appends;

        return $this;
    }

    /**
     * Get the fillable attributes for the model.
     *
     * @return array
     */
    public function getFillable(): array
    {
        return $this->fillable;
    }

    /**
     * Set the fillable attributes for the model.
     *
     * @param  array  $fillable
     * @return $this
     */
    public function fillable(array $fillable): self
    {
        $this->fillable = $fillable;

        return $this;
    }

    /**
     * Get the guarded attributes for the model.
     *
     * @return array
     */
    public function getGuarded(): array
    {
        return $this->guarded;
    }

    /**
     * Set the guarded attributes for the model.
     *
     * @param  array  $guarded
     * @return $this
     */
    public function guard(array $guarded): self
    {
        $this->guarded = $guarded;

        return $this;
    }

    /**
     * Disable all mass assignable restrictions.
     *
     * @param bool $state
     * @return void
     */
    public static function unguard(bool $state = true): void
    {
        static::$unguarded = $state;
    }

    /**
     * Enable the mass assignment restrictions.
     *
     * @return void
     */
    public static function reguard(): void
    {
        static::$unguarded = false;
    }

    /**
     * Determine if current state is "unguarded".
     *
     * @return bool
     */
    public static function isUnguarded(): bool
    {
        return static::$unguarded;
    }

    /**
     * Run the given callable while being unguarded.
     *
     * @param callable $callback
     *
     * @return mixed
     */
    public static function unguarded(callable $callback): mixed
    {
        if (static::$unguarded) {
            return $callback();
        }

        static::unguard();

        $result = $callback();

        static::reguard();

        return $result;
    }

    /**
     * Determine if the given attribute may be mass assigned.
     *
     * @param string $key
     * @return bool
     */
    public function isFillable(string $key): bool
    {
        if (static::$unguarded) {
            return true;
        }

        // If the key is in the "fillable" array, we can of course assume that it's
        // a fillable attribute. Otherwise, we will check the guarded array when
        // we need to determine if the attribute is black-listed on the model.
        if (in_array($key, $this->fillable, true)) {
            return true;
        }

        if ($this->isGuarded($key)) {
            return false;
        }

        return empty($this->fillable);
    }

    /**
     * Determine if the given key is guarded.
     *
     * @param string $key
     * @return bool
     */
    public function isGuarded(string $key): bool
    {
        return in_array($key, $this->guarded, true) || $this->guarded === ['*'];
    }

    /**
     * Determine if the model is totally guarded.
     *
     * @return bool
     */
    public function totallyGuarded(): bool
    {
        return count($this->fillable) === 0 && $this->guarded === ['*'];
    }

    /**
     * Convert the model instance to JSON.
     *
     * @param int $options
     * @return string
     * @throws JsonException
     */
    public function toJson($options = 0): string
    {
        return json_encode($this->jsonSerialize(), JSON_THROW_ON_ERROR | $options);
    }

    /**
     * Convert the object into something JSON serializable.
     *
     * @return array
     * @throws JsonException
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Convert the model instance to an array.
     *
     * @return array
     * @throws JsonException
     */
    public function toArray(): array
    {
        return $this->attributesToArray();
    }

    /**
     * Convert the model's attributes to an array.
     *
     * @return array
     * @throws JsonException
     */
    public function attributesToArray(): array
    {
        $attributes = $this->getArrayableAttributes();

        $mutatedAttributes = $this->getMutatedAttributes();

        // We want to spin through all the mutated attributes for this model and call
        // the mutator for the attribute. We cache off every mutated attributes so
        // we don't have to constantly check on attributes that actually change.
        foreach ($mutatedAttributes as $key) {
            if (! array_key_exists($key, $attributes)) {
                continue;
            }

            $attributes[$key] = $this->mutateAttributeForArray(
                $key, $attributes[$key]
            );
        }

        // Next we will handle any casts that have been setup for this model and cast
        // the values to their appropriate type. If the attribute has a mutator we
        // will not perform the cast on those attributes to avoid any confusion.
        foreach ($this->casts as $key => $value) {
            if (! array_key_exists($key, $attributes) ||
                in_array($key, $mutatedAttributes, true)) {
                continue;
            }

            $attributes[$key] = $this->castAttribute(
                $key, $attributes[$key]
            );
        }

        // Here we will grab all of the appended, calculated attributes to this model
        // as these attributes are not really in the attributes array, but are run
        // when we need to array or JSON the model for convenience to the coder.
        foreach ($this->getArrayableAppends() as $key) {
            $attributes[$key] = $this->mutateAttributeForArray($key, null);
        }

        return $attributes;
    }

    /**
     * Get an attribute array of all arrayable attributes.
     *
     * @return array
     */
    protected function getArrayableAttributes(): array
    {
        return $this->getArrayableItems($this->attributes);
    }

    /**
     * Get all of the appendable values that are arrayable.
     *
     * @return array
     */
    protected function getArrayableAppends(): array
    {
        if (! count($this->appends)) {
            return [];
        }

        return $this->getArrayableItems(
            array_combine($this->appends, $this->appends)
        );
    }

    /**
     * Get an attribute array of all arrayable values.
     *
     * @param  array  $values
     * @return array
     */
    protected function getArrayableItems(array $values): array
    {
        if (count($this->getVisible()) > 0) {
            return array_intersect_key($values, array_flip($this->getVisible()));
        }

        return array_diff_key($values, array_flip($this->getHidden()));
    }

    /**
     * Get an attribute from the model.
     *
     * @param string $key
     * @return mixed
     */
    public function getAttribute(string $key)
    {
        try {
            return $this->getAttributeValue($key);
        } catch (JsonException) {
            return null;
        }
    }

    /**
     * Get a plain attribute (not a relationship).
     *
     * @param string $key
     * @return bool|float|BaseCollection|int|mixed|string|null
     * @throws JsonException
     */
    protected function getAttributeValue(string $key)
    {
        $value = $this->getAttributeFromArray($key);

        // If the attribute has a get mutator, we will call that then return what
        // it returns as the value, which is useful for transforming values on
        // retrieval from the model to a form that is more useful for usage.
        if ($this->hasGetMutator($key)) {
            return $this->mutateAttribute($key, $value);
        }

        // If the attribute exists within the cast array, we will convert it to
        // an appropriate native PHP type dependant upon the associated value
        // given with the key in the pair. Dayle made this comment line up.
        if ($this->hasCast($key)) {
            $value = $this->castAttribute($key, $value);
        }

        return $value;
    }

    /**
     * Get an attribute from the $attributes array.
     *
     * @param string $key
     * @return mixed
     */
    protected function getAttributeFromArray(string $key)
    {
        if (array_key_exists($key, $this->attributes)) {
            return $this->attributes[$key];
        }
    }

    /**
     * Determine if a get mutator exists for an attribute.
     *
     * @param string $key
     * @return bool
     */
    public function hasGetMutator(string $key): bool
    {
        return method_exists($this, 'get'.Str::studly($key).'Attribute');
    }

    /**
     * Get the value of an attribute using its mutator.
     *
     * @param string $key
     * @param mixed $value
     * @return mixed
     */
    protected function mutateAttribute(string $key, mixed $value)
    {
        return $this->{'get'.Str::studly($key).'Attribute'}($value);
    }

    /**
     * Get the value of an attribute using its mutator for array conversion.
     *
     * @param string $key
     * @param mixed $value
     * @return array|mixed
     */
    protected function mutateAttributeForArray(string $key, mixed $value)
    {
        $value = $this->mutateAttribute($key, $value);

        return $value instanceof Arrayable ? $value->toArray() : $value;
    }

    /**
     * Determine whether an attribute should be casted to a native type.
     *
     * @param string $key
     * @return bool
     */
    protected function hasCast(string $key): bool
    {
        return array_key_exists($key, $this->casts);
    }

    /**
     * Determine whether a value is JSON castable for inbound manipulation.
     *
     * @param string $key
     * @return bool
     */
    protected function isJsonCastable(string $key): bool
    {
        $castables = ['array', 'json', 'object', 'collection'];
        return $this->hasCast($key) &&
               in_array($this->getCastType($key), $castables, true);
    }

    /**
     * Get the type of cast for a model attribute.
     *
     * @param string $key
     * @return string
     */
    protected function getCastType(string $key): string
    {
        return strtolower(trim($this->casts[$key]));
    }

    /**
     * Cast an attribute to a native PHP type.
     *
     * @param string $key
     * @param mixed $value
     * @return mixed
     * @throws JsonException
     */
    protected function castAttribute(string $key, mixed $value): mixed
    {
        if (is_null($value)) {
            return null;
        }

        return match ($this->getCastType($key)) {
            'int', 'integer' => (int)$value,
            'real', 'float', 'double' => (float)$value,
            'string' => (string)$value,
            'bool', 'boolean' => (bool)$value,
            'object' => $this->fromJson($value, true),
            'array', 'json' => $this->fromJson($value),
            'collection' => new BaseCollection($this->fromJson($value)),
            default => $value,
        };
    }

    /**
     * Set a given attribute on the model.
     *
     * @param string $key
     * @param mixed $value
     * @return $this
     */
    public function setAttribute(string $key, mixed $value): static
    {
        // First we will check for the presence of a mutator for the set operation
        // which simply lets the developers tweak the attribute as it is set on
        // the model, such as "json_encoding" an listing of data for storage.
        if ($this->hasSetMutator($key)) {
            $method = 'set'.Str::studly($key).'Attribute';

            return $this->{$method}($value);
        }

        if (!is_null($value) && $this->isJsonCastable($key) && ($jsonCastedValue = $this->asJson($value))) {
            $value = $jsonCastedValue;
        }

        $this->attributes[$key] = $value;

        return $this;
    }

    /**
     * Determine if a set mutator exists for an attribute.
     *
     * @param string $key
     * @return bool
     */
    public function hasSetMutator(string $key): bool
    {
        return method_exists($this, 'set'.Str::studly($key).'Attribute');
    }

    /**
     * Encode the given value as JSON.
     *
     * @param mixed $value
     * @return string|null
     */
    protected function asJson(mixed $value): ?string
    {
        try {
            return json_encode($value, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }
    }

    /**
     * Decode the given JSON back into an array or object.
     *
     * @param string $value
     * @param bool $asObject
     * @return mixed
     * @throws JsonException
     */
    public function fromJson(string $value, bool $asObject = false)
    {
        return json_decode($value, !$asObject, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Clone the model into a new, non-existing instance.
     *
     * @param array|null $except
     * @return Model
     */
    public function replicate(null|array $except = null): Model
    {
        $except = $except ?: [];

        $attributes = Arr::except($this->attributes, $except);

        return with(new static())->fill($attributes);
    }

    /**
     * Get all of the current attributes on the model.
     *
     * @return array
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Get the mutated attributes for a given instance.
     *
     * @return array
     */
    public function getMutatedAttributes(): array
    {
        $class = get_class($this);

        if (! isset(static::$mutatorCache[$class])) {
            static::cacheMutatedAttributes($class);
        }

        return static::$mutatorCache[$class];
    }

    /**
     * Extract and cache all the mutated attributes of a class.
     *
     * @param string $class
     * @return void
     */
    public static function cacheMutatedAttributes(string $class): void
    {
        $mutatedAttributes = [];

        // Here we will extract all of the mutated attributes so that we can quickly
        // spin through them after we export models to their array form, which we
        // need to be fast. This'll let us know the attributes that can mutate.
        if (preg_match_all('/(?<=^|;)get([^;]+?)Attribute(;|$)/', implode(';', get_class_methods($class)), $matches)) {
            foreach ($matches[1] as $match) {
                if (static::$snakeAttributes) {
                    $match = Str::snake($match);
                }

                $mutatedAttributes[] = lcfirst($match);
            }
        }

        static::$mutatorCache[$class] = $mutatedAttributes;
    }

    /**
     * Dynamically retrieve attributes on the model.
     *
     * @param string $key
     * @return mixed
     */
    public function __get(string $key)
    {
        return $this->getAttribute($key);
    }

    /**
     * Dynamically set attributes on the model.
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function __set(string $key, mixed $value)
    {
        $this->setAttribute($key, $value);
    }

    /**
     * Determine if the given attribute exists.
     *
     * @param  mixed  $offset
     * @return bool
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->$offset);
    }

    /**
     * Get the value for a given offset.
     *
     * @param  mixed  $offset
     * @return mixed
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->$offset;
    }

    /**
     * Set the value for a given offset.
     *
     * @param  mixed  $offset
     * @param  mixed  $value
     * @return void
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->$offset = $value;
    }

    /**
     * Unset the value for a given offset.
     *
     * @param  mixed  $offset
     * @return void
     */
    public function offsetUnset(mixed $offset): void
    {
        unset($this->$offset);
    }

    /**
     * Determine if an attribute exists on the model.
     *
     * @param string $key
     * @return bool
     * @throws JsonException
     */
    public function __isset(string $key)
    {
        return (isset($this->attributes[$key]) || isset($this->relations[$key])) ||
                ($this->hasGetMutator($key) && ! is_null($this->getAttributeValue($key)));
    }

    /**
     * Unset an attribute on the model.
     *
     * @param string $key
     * @return void
     */
    public function __unset(string $key)
    {
        unset($this->attributes[$key]);
    }

    /**
     * Handle dynamic static method calls into the method.
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public static function __callStatic(string $method, array $parameters)
    {
        $instance = new static();

        return call_user_func_array([$instance, $method], $parameters);
    }

    /**
     * Convert the model to its string representation.
     *
     * @return string
     * @throws JsonException
     */
    public function __toString()
    {
        return $this->toJson();
    }
}
