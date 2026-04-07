<?php

declare(strict_types=1);

namespace Sloth\Model\Builder;

use Corcel\Model\Builder\PostBuilder as CorcelPostBuilder;

class PostBuilder extends CorcelPostBuilder
{
    public function type($type)
    {
        if ($this->isQueryingRevisions()) {
            return $this;
        }

        return parent::type($type);
    }

    protected function isQueryingRevisions(): bool
    {
        $wheres = $this->query->wheres ?? [];

        foreach ($wheres as $where) {
            if (isset($where['column'], $where['value']) &&
                $where['value'] === 'revision' &&
                ($where['column'] === 'post_type' || $where['column'] === 'posts.post_type')) {
                return true;
            }
        }

        return false;
    }
}
