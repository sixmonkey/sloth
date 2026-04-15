<?php

declare(strict_types=1);

namespace Sloth\Model;

use Corcel\Model\User as CorcelUser;
use Sloth\Model\Traits\HasACF;
use Sloth\Model\Traits\HasAliases;
use Sloth\Model\Traits\HasMetaFields;

/**
 * WordPress user model.
 *
 * This model extends Corcel's User model and uses Sloth's own trait
 * implementations for meta fields and aliases, providing full control
 * over attribute resolution without depending on Corcel's internal traits.
 *
 * @since 1.0.0
 * @see https://developer.wordpress.org/reference/classes/wp_user/
 */
class User extends CorcelUser
{
    use HasACF;
    use HasAliases;
    use HasMetaFields;

    public const CREATED_AT = 'user_registered';
    public const UPDATED_AT = null;

    /**
     * Get the ACF key for this user.
     *
     * Returns the WordPress user meta key format: 'user_{id}'.
     *
     * @return string The ACF field group key
     */
    public function getAcfKey(): ?string
    {
        return 'user_' . $this->ID;
    }
}
