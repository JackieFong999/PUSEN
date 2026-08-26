<?php

namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Model;

/**
 * Staff account used for login.
 *
 * Reads from the legacy `pusen` MySQL database (tblStaff).
 * Login identifier = Staff_Id, password = Password (bcrypt hash since
 * 2026-08-26 — see PusenStaffUserProvider::validateCredentials()).
 *
 * status: 0 = Enable, 1 = Disable.
 */
class Staff extends Model implements AuthenticatableContract
{
    use Authenticatable;

    protected $connection = 'pusen';
    protected $table = 'tblStaff';
    protected $primaryKey = 'Staff_Id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'Staff_Id',
        'Staff_Name',
        'Staff_Display_Name',
        'Password',
        'Role_Id',
        'Target_User_Id',
        'status',
    ];

    protected $hidden = [
        'Password',
    ];

    /**
     * Role assigned to this staff member (tblRole lookup via Role_Id).
     */
    public function role()
    {
        return $this->belongsTo(Role::class, 'Role_Id', 'Role_Id');
    }

    public function getAuthIdentifierName(): string
    {
        return 'Staff_Id';
    }

    public function getAuthPassword(): string
    {
        return $this->attributes['Password'] ?? '';
    }
}
