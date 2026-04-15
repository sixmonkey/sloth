<?php

declare(strict_types=1);

namespace Sloth\Model\Traits;

use Illuminate\Support\Collection;
use Sloth\ACF\AcfProxy;
use Sloth\Model\Casts\AcfBase;

trait HasACF
{
    public static array $acfFieldCache = [];

    public static function bootHasACF(): void
    {
        static::retrieved(function (self $model) {
            $key = $model->getAcfKey();
            $fields = $model->getFields($model);
            $acf_fields = $fields->keys();
            $native_fields = collect($model->getAttributes())->keys();

            $acf_casts = $acf_fields->diff($native_fields)->mapWithKeys(function ($item) {
                return [$item => AcfBase::class];
            });

            $model->mergeCasts($acf_casts->toArray());
        });
    }

    private function getFields($model): Collection
    {
        $key = $model->getAcfKey();
        if (!isset(static::$acfFieldCache[$key])) {
            static::$acfFieldCache[$key] = collect(get_fields($key) ?? []);
        }
        return static::$acfFieldCache[$key];
    }

    abstract public function getAcfKey(): ?string;

    public function getAcfAttribute()
    {
        return new AcfProxy($this->getFields($this));
    }
}
