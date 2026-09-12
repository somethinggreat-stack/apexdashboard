<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Admin extends Authenticatable
{
    use HasFactory, Notifiable;

    // NOTE: role + parent_admin_id are intentionally NOT mass-assignable — they
    // are set only via explicit assignment (seeder / UserController) so a stray
    // create()/update() can never escalate an account. Security invariant.
    protected $fillable = ['email', 'password', 'full_name'];
    protected $hidden = ['password', 'remember_token'];
    protected $casts = ['password' => 'hashed', 'last_seen_at' => 'datetime'];

    /** Considered online if seen within the last minute (poll-based presence). */
    public function isOnline(): bool
    {
        return $this->last_seen_at && $this->last_seen_at->gt(now()->subSeconds(60));
    }

    /** "last seen 5 min ago" style text, or null if never seen. */
    public function lastSeenHuman(): ?string
    {
        return $this->last_seen_at ? $this->last_seen_at->diffForHumans() : null;
    }

    /**
     * Roles: 'super' (sees everything), 'va' (business-owner workflow only),
     * 'leads' (sales leads pipeline only). Fail CLOSED — an unknown/missing
     * role is treated as least-privileged, never as super.
     */
    public function isSuper(): bool
    {
        return ($this->role ?? 'va') === 'super';
    }

    public function isVa(): bool
    {
        return ($this->role ?? 'va') === 'va';
    }

    /** Leads agent — only the prospects / prospect-leads pipeline. */
    public function isLeads(): bool
    {
        return ($this->role ?? '') === 'leads';
    }

    /**
     * May this account open the business owner Credentials vault? Super admins
     * always can; a VA only when granted the flag. Leads agents never can.
     */
    public function canManageCredentials(): bool
    {
        return $this->isSuper() || ($this->isVa() && (bool) $this->can_manage_credentials);
    }

    /**
     * The admin whose data (business owners, clients) this user operates on.
     * VAs share their parent super admin's data; a super admin owns their own.
     */
    public function dataOwnerId(): int
    {
        return $this->parent_admin_id ?: $this->id;
    }

    /** Team photos shipped in public/img/team (filename slugs, without extension). */
    private const TEAM_AVATARS = ['abid', 'mujeeburrehman', 'rajakhuram', 'ubaiddogar', 'umairarshad', 'umairsajid', 'zeeshanghazi'];

    /** Full-name (normalized) → photo slug, for names that don't map 1:1 to the filename. */
    private const AVATAR_ALIASES = [
        'abid hussain'           => 'abid',
        'mujeeb'                 => 'mujeeburrehman',
        'mujeeb ur rehman'       => 'mujeeburrehman',
        'raja khuram'            => 'rajakhuram',
        'ubaid dogar'            => 'ubaiddogar',
        'umair arshad'           => 'umairarshad',
        'muhammad umair arshad'  => 'umairarshad',
        'umair sajid'            => 'umairsajid',
        'zeeshan ghazi'          => 'zeeshanghazi',
    ];

    /**
     * Profile photo URL for the team chat, or null to fall back to a monogram.
     * An explicit `avatar` column wins; otherwise we match the person's name to
     * a bundled team photo, so no data migration is needed.
     */
    public function avatarUrl(): ?string
    {
        if (! empty($this->avatar)) {
            return asset('img/team/' . $this->avatar);
        }

        $name = strtolower(trim((string) $this->full_name));
        $name = preg_replace('/[^a-z\s]/', '', $name);   // drop punctuation / digits
        $name = preg_replace('/^mr\s+/', '', $name);     // drop a leading honorific
        $name = trim(preg_replace('/\s+/', ' ', $name));

        $slug = self::AVATAR_ALIASES[$name] ?? null;
        if (! $slug) {
            $nospace = str_replace(' ', '', $name);
            if (in_array($nospace, self::TEAM_AVATARS, true)) $slug = $nospace;
        }

        return $slug ? asset('img/team/' . $slug . '.jpg') : null;
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
