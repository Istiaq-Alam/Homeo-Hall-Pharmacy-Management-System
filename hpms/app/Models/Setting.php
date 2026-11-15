<?php

namespace App\Models;

use App\Models\Concerns\BelongsToChamber;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use BelongsToChamber;

    protected $fillable = ['chamber_id', 'key', 'value'];
}
