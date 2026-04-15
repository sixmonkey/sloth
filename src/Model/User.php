<?php

declare(strict_types=1);

namespace Sloth\Model;

use Corcel\Model as CorcelModel;
use Corcel\Model\Comment;
use Corcel\Model\Meta\UserMeta;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Sloth\Model\Traits\HasACF;
use Sloth\Model\Traits\HasAliases;
use Sloth\Model\Traits\HasMetaFields;
use Sloth\Model\Traits\HasOrderScopes;

/**
 * WordPress user model.
 *
 * This model extends Corcel\Model directly to provide WordPress user functionality.
 * It uses Sloth's own trait implementations for meta fields and aliases.
 *
 * ## Independence from Corcel
 *
 * This model does NOT extend Corcel\Model\User. Instead, it implements all
 * necessary features directly, ensuring full control over attribute resolution.
 *
 * @since 1.0.0
 * @see https://developer.wordpress.org/reference/classes/wp_user/
 */
class User extends CorcelModel
{
    use HasACF;
    use HasAliases;
    use HasMetaFields;
    use HasOrderScopes;

    public const CREATED_AT = 'user_registered';
    public const UPDATED_AT = null;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'users';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'ID';

    /**
     * Attributes that should be hidden for serialization.
     *
     * @var array<string>
     */
    protected $hidden = ['user_pass'];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'user_registered' => 'datetime',
    ];

    /**
     * The relationships to eager-load on every query.
     *
     * @var array<string>
     */
    protected $with = ['meta'];

    /**
     * Aliases for attribute access.
     *
     * Maps alternative property names to their original database columns or meta fields.
     *
     * @var array<string, string|array>
     */
    protected static array $aliases = [
        'login' => 'user_login',
        'email' => 'user_email',
        'slug' => 'user_nicename',
        'url' => 'user_url',
        'nickname' => ['meta' => 'nickname'],
        'first_name' => ['meta' => 'first_name'],
        'last_name' => ['meta' => 'last_name'],
        'description' => ['meta' => 'description'],
        'created_at' => 'user_registered',
    ];

    /**
     * Accessors to append to array/JSON representation.
     *
     * @var array<string>
     */
    protected $appends = [
        'login',
        'email',
        'slug',
        'url',
        'nickname',
        'first_name',
        'last_name',
        'avatar',
        'created_at',
    ];

    /**
     * Get the posts authored by this user.
     *
     * @return HasMany The posts relationship
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'post_author');
    }

    /**
     * Get the comments made by this user.
     *
     * @return HasMany The comments relationship
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class, 'user_id');
    }

    /**
     * Get the avatar URL from Gravatar.
     *
     * @return string The Gravatar URL
     */
    public function getAvatarAttribute(): string
    {
        $hash = !empty($this->email) ? md5(strtolower(trim($this->email))) : '';

        return sprintf('//secure.gravatar.com/avatar/%s?d=mm', $hash);
    }

    /**
     * Set the updated_at timestamp (no-op for users).
     *
     * WordPress users don't have an updated_at timestamp.
     *
     * @param mixed $value The timestamp value
     */
    public function setUpdatedAt($value): void
    {
    }

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

    /**
     * Get the meta class for this model.
     *
     * @return string The fully qualified class name of the meta model
     */
    protected function getMetaClass(): string
    {
        return UserMeta::class;
    }

    /**
     * Get the foreign key for the meta relationship.
     *
     * @return string The foreign key name
     */
    protected function getMetaForeignKey(): string
    {
        return 'user_id';
    }
}
