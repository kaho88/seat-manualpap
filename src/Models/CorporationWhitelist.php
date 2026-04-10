<?php

namespace Seat\ManualPap\Models;

use Illuminate\Database\Eloquent\Model;

class CorporationWhitelist extends Model
{
    protected $table = 'manualpap_corporations';

    protected $primaryKey = 'corporation_id';

    public $incrementing = false;

    public $timestamps = true;

    protected $fillable = ['corporation_id'];
}
