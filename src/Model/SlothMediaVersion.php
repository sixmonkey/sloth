<?php

namespace Sloth\Model;

class SlothMediaVersion extends Model
{
    public function getOptionsAttribute()
    {
        return json_decode($this->post_excerpt, true);
    }
}
