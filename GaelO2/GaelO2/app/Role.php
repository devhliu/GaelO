<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    public function study(){
        return $this->hasOne('App\Study', 'name', 'study_name');
    }

    public function user(){
        return $this->hasOne('App\User', 'id', 'user_id');
    }

}
