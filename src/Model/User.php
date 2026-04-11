<?php

declare(strict_types=1);

namespace Sloth\Model;

use Corcel\Concerns\Aliases;
use Corcel\Concerns\MetaFields;
use Corcel\Concerns\OrderScopes;
use Corcel\Model as CorcelBase;
use Sloth\Model\Traits\HasACF;

/**
 * WordPress user model.
 *
 * Rebuilt directly from Corcel\Model to avoid pulling in
 * the AdvancedCustomFields trait which requires the optional
 * corcel/acf package. ACF access is handled by HasACF instead.
 *
 * @see https://developer.wordpress.org/reference/classes/wp_user/
 */
class User extends CorcelBase
{
    use HasACF, Aliases, MetaFields, OrderScopes {
        HasACF::getAttribute insteadof Aliases;
    }

    const CREATED_AT = 'user_registered';
    const UPDATED_AT = null;

    /**
     * The database table for WordPress users.
     *
     * @var string
     */
    protected $table = 'users';

    /**
     * The primary key for the users table.
     *
     * @var string
     */
    protected $primaryKey = 'ID';

    /**
     * Attributes hidden from serialization.
     *
     * Keeps the hashed password out of API responses and toArray() output.
     *
     * @var array<string>
     */
    protected $hidden = ['user_pass'];

    /**
     * Attributes that should be cast to Carbon instances.
     *
     * @var array<string>
     */
    protected $dates = ['user_registered'];

    /**
     * Relations to eager load with every query.
     *
     * @var array<string>
     */
    protected $with = ['meta'];

    /**
     * Alias map from friendly names to database column names or meta keys.
     *
     * Allows accessing user data via $user->email instead of $user->user_email,
     * and meta fields like $user->first_name directly on the model.
     *
     * @var array<string, string|array<string, string>>
     * @see Aliases
     */
    protected static $aliases = [
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
     * Accessors to append to the model's array and JSON form.
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
     * Returns the meta model class for users.
     *
     * Overrides Corcel's instanceof guard which only allows built-in
     * model classes. Since we extend Corcel\Model directly we bypass
     * that check by declaring the meta class explicitly.
     *
     * @return string
     * @see \Corcel\Concerns\MetaFields::getMetaClass()
     */
    protected function getMetaClass(): string
    {
        return \Corcel\Model\Meta\UserMeta::class;
    }

    /**
     * Returns the foreign key used to join usermeta to users.
     *
     * @return string
     * @see \Corcel\Concerns\MetaFields::getMetaForeignKey()
     */
    protected function getMetaForeignKey(): string
    {
        return 'user_id';
    }

    /**
     * All posts authored by this user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function posts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Model::class, 'post_author');
    }

    /**
     * All comments left by this user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function comments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\Corcel\Model\Comment::class, 'user_id');
    }

    /**
     * Gravatar URL derived from the user's email address.
     *
     * Returns a mystery-man placeholder when no email is set.
     *
     * @return string Gravatar URL without scheme
     * @see https://gravatar.com/site/implement/images/
     */
    public function getAvatarAttribute(): string
    {
        $hash = !empty($this->email) ? md5(strtolower(trim($this->email))) : '';

        return sprintf('//secure.gravatar.com/avatar/%s?d=mm', $hash);
    }

    /**
     * No-op — WordPress users table has no updated_at column.
     *
     * @param mixed $value
     */
    public function setUpdatedAtAttribute(mixed $value): void
    {
    }

    /**
     * No-op — WordPress users table has no updated_at column.
     *
     * @param mixed $value
     */
    public function setUpdatedAt(mixed $value): void
    {
    }
}
