<?php

declare(strict_types=1);

namespace Sloth\Model\Traits;

use Sloth\Model\Casts\AcfBase;

trait HasACF
{
    public static array $acfFieldCache = [];

    public static function bootHasACF(): void
    {
        static::retrieved(function (self $model) {
            $key = $model->getAcfKey();

            if (!isset(static::$acfFieldCache[$key])) {
                static::$acfFieldCache[$key] = collect(get_fields($key) ?? []);
            }

            $acf_fields = static::$acfFieldCache[$key]->keys();
            $native_fields = collect($model->getAttributes())->keys();

            $acf_casts = $acf_fields->diff($native_fields)->mapWithKeys(function ($item) {
                return [$item => AcfBase::class];
            });

            $model->mergeCasts($acf_casts->toArray());
        });
    }

    abstract public function getAcfKey(): ?string;
}
