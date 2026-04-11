<?php

namespace Seat\ManualPap\Models;

use Illuminate\Database\Eloquent\Model;

class CharacterBlocklist extends Model
{
    protected $table = 'manualpap_blocklist';

    protected $primaryKey = 'character_id';

    public $incrementing = false;

    public $timestamps = true;

    protected $fillable = ['character_id'];
}
