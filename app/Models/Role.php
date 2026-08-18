<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Role lookup from the legacy `pusen` MySQL database (tblRole).
 *
 * Joined to tblStaff via Role_Id to display the role description
 * (e.g. SA -> Super Administrator) in the topbar.
 */
class Role extends Model
{
    protected $connection = 'pusen';
    protected $table = 'tblRole';
    protected $primaryKey = 'Id';

    public $timestamps = false;

    protected $fillable = [
        'Role_Id',
        'Role_Desc',
    ];
}
