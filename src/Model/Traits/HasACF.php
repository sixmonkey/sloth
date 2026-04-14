<?php

declare(strict_types=1);

namespace Sloth\Model\Traits;

use Sloth\Model\Casts\ACF;

trait HasACF
{
    protected static array $acfFieldCache = [];

    public static function bootHasACF(): void
    {
        self::retrieved(function (self $model) {
            die;
        });
    }

    abstract private function getAcfKey(): ?string;
}
