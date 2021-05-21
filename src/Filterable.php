<?php

namespace Fomvasss\Filterable;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

/**
 * Trait Filterable
 * @package Fomvasss\Searchable
 */
trait Filterable
{
    /**
     *
     * @param $query
     * @param array|null $filterAttributes
     * @return mixed
     */
    public function scopeFilterable($query, array $filterAttributes = null)
    {
        $filterable = $this->getFilterable();
        $attributes = $this->getFilterAttributes($filterAttributes);

        if (! empty($attributes)) {
            $query->where(function ($q) use ($attributes, $filterable) {
                foreach ($filterable as $key => $filterType) {
                    switch ($filterType) {
                        case 'equal':
                            if (array_key_exists($key, $attributes) && $attributes[$key] !== null) {
                                $this->fWhereEqual($q, $key, $attributes);
                            }
                            break;
                        case 'like':
                            if (array_key_exists($key, $attributes) && $attributes[$key] !== null) {
                                $q->where($key, 'LIKE', '%' . $attributes[$key] . '%');
                            }
                            break;
                        case 'in':
                            if (array_key_exists($key, $attributes) && $attributes[$key] !== null) {
                                $this->fWhereIn($q, $key, $attributes);
                            }
                            break;
                        case 'between':
                            $fromVal = $attributes[$key . '_from'] ?? null;
                            $toVal = $attributes[$key . '_to'] ?? null;
                            if ($fromVal != null && $toVal != null) {
                                $q->whereBetween($key, [$fromVal, $toVal]);
                            } elseif ($fromVal != null && $toVal == null) {
                                $q->where($key, '>=', $fromVal);
                            } elseif ($fromVal == null && $toVal != null) {
                                $q->where($key, '<=', $toVal);
                            }
                            break;
                        case 'equal_date':
                            if (array_key_exists($key, $attributes)) {
                                try {
                                    $date = Carbon::parse($attributes[$key])->toDateString();
                                    $q->whereDate($key, $date);
                                } catch (\Exception $exception) {
                                    \Log::error(__METHOD__ . $exception->getMessage());
                                }
                            }
                            break;
                        case 'between_date':
                            $fromVal = $attributes[$key . '_from'] ?? null;
                            $toVal = $attributes[$key . '_to'] ?? null;

                            try {
                                if ($fromVal != null && $toVal != null) {
                                    $dateFrom = Carbon::parse($fromVal);
                                    $dateTo = Carbon::parse($toVal)->addDay()->addSecond(-1);
                                    $q->whereBetween($key, [$dateFrom, $dateTo]);
                                } elseif ($fromVal != null && $toVal == null) {
                                    $dateFrom = Carbon::parse($fromVal);
                                    $q->where($key, '>=', $dateFrom);
                                } elseif ($fromVal == null && $toVal != null) {
                                    $dateTo = Carbon::parse($toVal)->addDay()->addSecond(-1);
                                    $q->where($key, '<=', $dateTo);
                                }
                            } catch (\Exception $exception) {
                                \Log::error(__METHOD__ . $exception->getMessage());
                            }
                            break;
                        case 'custom':
                            $method = 'scopeCustomFilterable';
                            if (array_key_exists($key, $attributes) && method_exists(self::class, $method)) {
                                $this->{$method}($q, $key, $attributes[$key]);
                            }
                            break;
                    }
                }
            });
        }

        return $query;
    }

    protected function customFilterable($q)
    {
        //...
    }

    protected function getFilterable(): array
    {
        return $this->filterable ?? [];
    }

    protected function getFilterAttributes(array $attributes = null)
    {
        $requestKeyName = config('filterable.input_keys.filter', 'filter');

        if (! empty($attributes)) {
            return $attributes;
        } elseif ($requestKeyName && request($requestKeyName) && is_array(request($requestKeyName))) {
            return request($requestKeyName);
        }

        return [];
    }

    /**
     * https://site.test/post?filter[status]=post_published
     * @param $q
     * @param $key
     * @param array $attributes
     * @return mixed
     */
    protected function fWhereEqual($q, $key, array $attributes)
    {
        $value = $attributes[$key];

        if (preg_match('/\./', $key)) {
            $has = preg_replace("/\.\w+$/", '', $key);
            $key = preg_replace("/\w+\./", '', $key);

            $q->whereHas($has, function ($qq) use ($key, $value) {
                $qq->where($key, $value);
            });
        } else {
            $q->where($key, $value);
        }

        return $q;
    }

    /**
     * https://site.test/post?filter[status][]=post_moderation&filter[status][]=post_published
     * or if set $filterSeparator
     * https://site.test/post?filter[status]=post_moderation|post_published
     * or
     * https://site.test/post?filter[status]=post_moderation
     * @param $q
     * @param $key
     * @param array $attributes
     * @return mixed
     */
    protected function fWhereIn($q, $key, array $attributes)
    {
        //$value = is_array($attributes[$key]) ? $attributes[$key] : [$attributes[$key]];

        if (is_array($attributes[$key])) {
            $value = $attributes[$key];
        } elseif ($filterSeparator = '|') {
            $value = explode($filterSeparator, $attributes[$key]);
        } else {
            $value = [$attributes[$key]];
        }

        if (preg_match('/\./', $key)) {
            $has = preg_replace("/\.\w+$/", '', $key);
            $key = preg_replace("/\w+\./", '', $key);

            $q->whereHas($has, function ($qq) use ($key, $value) {
                $qq->whereIn($key, $value);
            });
        } else {
            $q->whereIn($key, $value);
        }

        return $q;
    }

    /**
     * https://site.test/post?q=запрос
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string|null $search
     */
    public function scopeSearchable(Builder $query, string $search = null)
    {
        $search = $this->getSearchStr($search);

        $query->when($search !== null, function () use ($query, $search) {
            $query->where(function ($query2) use ($search) {
                foreach ($this->getSearchable() as $key) {
                    if (preg_match('/\./', $key)) {
                        $has = preg_replace("/\.\w+$/", '', $key);
                        $key = preg_replace("/\w+\./", '', $key);
                        $query2->orWhereHas($has, function ($qq) use ($key, $search) {
                            $qq->where($key, 'LIKE', "%$search%");
                        });
                    } else {
                        $query2->orWhere($key, 'LIKE', "%$search%");
                    }
                }
            });
        });
    }

    /**
     * @return array
     */
    public function getSearchable(): array
    {
        return $this->searchable ?? [];
    }

    /**
     * @param string|null $search
     * @return array|\Illuminate\Http\Request|null|string
     */
    protected function getSearchStr(string $search = null)
    {
        $requestKeyName = config('filterable.input_keys.search', 'q');

        if ($search !== null) {
            return $search;
        } elseif ($requestKeyName && request($requestKeyName) && is_string(request($requestKeyName))) {
            return request($requestKeyName);
        }

        return null;
    }

    /**
     * @return bool
     */
    public static function isFilterable(): bool
    {
        return (new self())->getFilterable() ? true : false;
    }

    /**
     * @return bool
     */
    public static function isSearchable(): bool
    {
        return (new self())->getSearchable() ? true : false;
    }
}