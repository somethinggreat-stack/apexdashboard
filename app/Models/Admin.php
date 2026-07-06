<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Admin extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = ['email', 'password', 'full_name', 'role', 'parent_admin_id'];
    protected $hidden = ['password', 'remember_token'];
    protected $casts = ['password' => 'hashed'];

    /** Super admins see everything; VAs only work on the business owners. */
    public function isSuper(): bool
    {
        return ($this->role ?? 'super') === 'super';
    }

    public function isVa(): bool
    {
        return !$this->isSuper();
    }

    /**
     * The admin whose data (business owners, clients) this user operates on.
     * VAs share their parent super admin's data; a super admin owns their own.
     */
    public function dataOwnerId(): int
    {
        return $this->parent_admin_id ?: $this->id;
    }

    public function clients()
    {
        return $this->hasMany(Client::class);
    }

    public function processSteps()
    {
        return $this->hasMany(ProcessStep::class, 'created_by_admin_id');
    }
}
