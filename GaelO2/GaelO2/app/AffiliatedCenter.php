<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class AffiliatedCenter extends Model
{
    public function center(){
        return $this->hasOne('App\Center', 'code', 'center_code');
    }

    public function user(){
        return $this->hasOne('App\User', 'id', 'user_id');
    }
}
