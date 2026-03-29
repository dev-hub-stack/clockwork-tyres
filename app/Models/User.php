<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use App\Modules\Accounts\Models\Account;
use App\Modules\Accounts\Models\AccountMembership;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, \Spatie\Permission\Traits\HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'last_login_at',
        'last_login_ip',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the user's login history.
     */
    public function loginHistory(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(UserLoginHistory::class)->orderBy('logged_in_at', 'desc');
    }

    /**
     * Accounts the user belongs to.
     */
    public function accounts(): BelongsToMany
    {
        return $this->belongsToMany(Account::class, 'account_user')
            ->using(AccountMembership::class)
            ->withPivot(['role', 'is_default'])
            ->withTimestamps();
    }

    /**
     * Default account for the user, if one has been marked.
     */
    public function defaultAccount(): ?Account
    {
        return $this->accounts()->wherePivot('is_default', true)->first();
    }

    /**
     * Accounts created by the user.
     */
    public function createdAccounts(): HasMany
    {
        return $this->hasMany(Account::class, 'created_by_user_id');
    }

    /**
     * Get recent login history for the user.
     */
    public function getRecentLoginHistory(int $days = 30)
    {
        return $this->loginHistory()
            ->where('logged_in_at', '>=', now()->subDays($days))
            ->get();
    }

    /**
     * Determine if the user can access the Filament admin panel.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        // Allow all authenticated users to access the admin panel
        // You can add more specific checks here if needed (e.g., check for admin role)
        return true;
    }
}
