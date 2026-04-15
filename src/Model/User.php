<?php

declare(strict_types=1);

namespace Sloth\Model;

use Corcel\Model\User as CorcelUser;
use Sloth\Model\Traits\HasACF;

/**
 * WordPress user model.
 *
 * @see https://developer.wordpress.org/reference/classes/wp_user/
 */
class User extends CorcelUser
{
    use HasACF;

    public const CREATED_AT = 'user_registered';
    public const UPDATED_AT = null;

    public function getAcfKey(): ?string
    {
        return 'user_' . $this->ID;
    }
}
