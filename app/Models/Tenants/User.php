<?php

namespace App\Models\Tenants;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * @mixin IdeHelperUser
 */
class User extends Authenticatable implements FilamentUser, HasAvatar, HasName
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'pin',
        'fcm_token',
        'is_owner',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'pin',
        'remember_token',
        'fcm_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    public function secureInitialPrice(): HasOne
    {
        return $this->hasOne(SecureInitialPrice::class);
    }

    public function cashDrawer(): HasOne
    {
        return $this->hasOne(CashDrawer::class);
    }

    public function sellings(): HasMany
    {
        return $this->hasMany(Selling::class);
    }

    public function getFilamentName(): string
    {
        return $this->name ?? '';
    }

    public function getFullNameAttribute()
    {
        return $this->name ?? '';
    }

    public function getFilamentAvatarUrl(): ?string
    {
        return $this->profile?->photo ? Storage::disk(config('filesystems.default'))->url($this->profile->photo) : null;
    }

    public function cashierName(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->name ?? $this->email
        );
    }

    public function routeNotificationForFcm()
    {
        return $this->fcm_token;
    }

    public function scopeOwner(Builder $builder)
    {
        return $builder->whereIsOwner(true);
    }

    public function canManageStorePin(): bool
    {
        if ($this->is_owner) {
            return true;
        }

        $roleNames = $this->roles->pluck('name')->map(fn ($r) => strtolower(trim($r)))->all();
        foreach (['admin', 'superadmin', 'super admin', 'kepala toko', 'head of store', 'store manager'] as $allowedRole) {
            if (in_array($allowedRole, $roleNames, true)) {
                return true;
            }
        }

        return $this->can('access store pin');
    }

    public function canEditSellingNote(): bool
    {
        if ($this->canManageStorePin()) {
            return true;
        }

        $roleNames = $this->roles->pluck('name')->map(fn ($r) => strtolower(trim($r)))->all();
        foreach (['kasir', 'cashier'] as $allowedRole) {
            if (in_array($allowedRole, $roleNames, true)) {
                return true;
            }
        }

        return $this->can('create selling') || $this->can('update selling') || $this->can('read selling');
    }

    public function hasPin(): bool
    {
        return filled($this->pin);
    }

    public function verifyPin(?string $inputPin): bool
    {
        if (blank($inputPin) || blank($this->pin)) {
            return false;
        }

        if (str_starts_with($this->pin, '$2y$') || str_starts_with($this->pin, '$argon2')) {
            return Hash::check($inputPin, $this->pin);
        }

        return hash_equals((string) $this->pin, (string) $inputPin);
    }

    public function isAdminPanelUser(): bool
    {
        return $this->canManageStorePin();
    }

    public static function hasConfiguredAdminPin(?self $preferredUser = null): bool
    {
        if ($preferredUser && $preferredUser->isAdminPanelUser() && $preferredUser->hasPin()) {
            return true;
        }

        return self::query()
            ->whereNotNull('pin')
            ->with('roles')
            ->get()
            ->contains(fn (self $user): bool => $user->isAdminPanelUser() && $user->hasPin());
    }

    public static function verifyAdminPin(?string $inputPin, ?self $preferredUser = null): ?self
    {
        if (blank($inputPin)) {
            return null;
        }

        if ($preferredUser && $preferredUser->isAdminPanelUser() && $preferredUser->hasPin()) {
            return $preferredUser->verifyPin($inputPin) ? $preferredUser : null;
        }

        return self::query()
            ->whereNotNull('pin')
            ->with('roles')
            ->get()
            ->first(fn (self $user): bool => $user->isAdminPanelUser() && $user->verifyPin($inputPin));
    }
}
