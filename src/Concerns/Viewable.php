<?php

declare(strict_types=1);

namespace Zing\LaravelView\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use function is_a;

/**
 * @property-read \Illuminate\Database\Eloquent\Collection|\Zing\LaravelView\View[] $views
 * @property-read \Illuminate\Database\Eloquent\Collection|\Zing\LaravelView\Concerns\Viewer[] $viewers
 * @property-read int|null $views_count
 * @property-read int|null $viewers_count
 *
 * @method static static|\Illuminate\Database\Eloquent\Builder whereViewedBy(\Illuminate\Database\Eloquent\Model $user)
 * @method static static|\Illuminate\Database\Eloquent\Builder whereNotViewedBy(\Illuminate\Database\Eloquent\Model $user)
 */
trait Viewable
{
    /**
     * @param \Illuminate\Database\Eloquent\Model $user
     *
     * @return bool
     */
    public function isViewedBy(Model $user): bool
    {
        if (! is_a($user, config('view.models.user'))) {
            return false;
        }

        if ($this->relationLoaded('viewers')) {
            return $this->viewers->contains($user);
        }

        return tap($this->relationLoaded('views') ? $this->views : $this->views())
            ->where(config('view.column_names.user_foreign_key'), $user->getKey())->count() > 0;
    }

    public function isNotViewedBy(Model $user): bool
    {
        return ! $this->isViewedBy($user);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function views(): MorphMany
    {
        return $this->morphMany(config('view.models.view'), 'viewable');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function viewers(): BelongsToMany
    {
        return $this->morphToMany(
            config('view.models.user'),
            'viewable',
            config('view.models.view'),
            null,
            config('view.column_names.user_foreign_key')
        )->withTimestamps();
    }

    public function viewsCount(): int
    {
        if ($this->views_count !== null) {
            return (int) $this->views_count;
        }

        $this->loadCount('views');

        return (int) $this->views_count;
    }

    public function viewsCountForHumans($precision = 1, $mode = PHP_ROUND_HALF_UP, $divisors = null): string
    {
        $number = $this->viewsCount();

        return $this->numberForHumans($number, $precision, $mode, $divisors);
    }

    public function loadViewersCount()
    {
        $view = app(config('view.models.view'));
        $column = $view->qualifyColumn(config('view.column_names.user_foreign_key'));
        $this->loadAggregate('views as viewers_count', "COUNT(DISTINCT('{$column}'))");

        return $this;
    }

    public function viewersCount(): int
    {
        if ($this->viewers_count !== null) {
            return (int) $this->viewers_count;
        }

        $this->loadViewersCount();

        return (int) $this->viewers_count;
    }

    protected function numberForHumans($number, $precision = 1, $mode = PHP_ROUND_HALF_UP, $divisors = null): string
    {
        $divisors = collect($divisors ?? config('view.divisors'));
        $divisor = $divisors->keys()->filter(
            function ($divisor) use ($number) {
                return $divisor <= abs($number);
            }
        )->last() ?? 1;

        if ($divisor === 1) {
            return (string) $number;
        }

        return number_format(round($number / $divisor, $precision, $mode), $precision) . $divisors->get($divisor);
    }

    public function viewersCountForHumans($precision = 1, $mode = PHP_ROUND_HALF_UP, $divisors = null): string
    {
        $number = $this->viewersCount();

        return $this->numberForHumans($number, $precision, $mode, $divisors);
    }

    public function scopeWhereViewedBy(Builder $query, Model $user): Builder
    {
        return $query->whereHas(
            'viewers',
            function (Builder $query) use ($user) {
                return $query->whereKey($user->getKey());
            }
        );
    }

    public function scopeWhereNotViewedBy(Builder $query, Model $user): Builder
    {
        return $query->whereDoesntHave(
            'viewers',
            function (Builder $query) use ($user) {
                return $query->whereKey($user->getKey());
            }
        );
    }
}