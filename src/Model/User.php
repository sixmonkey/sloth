<?php

namespace Sloth\Model;

use Corcel\Model\User as CorcelUser;
use Sloth\Model\Traits\HasACF;

class User extends CorcelUser
{
    use HasACF;
}
