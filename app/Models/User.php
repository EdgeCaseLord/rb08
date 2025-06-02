<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Filament\Panel;
use Filament\Models\Contracts\FilamentUser;


class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable;

    protected $table = 'users';

    protected $fillable = [
        'name', 'email', 'avatar', 'email_verified_at', 'password', 'username',
        'trial_ends_at', 'verification_code', 'verified', 'phone', 'address1',
        'address2', 'zip', 'city', 'country', 'state', 'language', 'timezone',
        'currency', 'stripe_id', 'card_brand', 'card_last_four', 'role',
        'lab_id', 'doctor_id', 'patient_code', 'birthdate', 'threshold',
        'settings','recipe_totals',
        'title', 'first_name',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'id' => 'integer',
        'email_verified_at' => 'timestamp',
        'trial_ends_at' => 'datetime',
        'verified' => 'integer',
        'lab_id' => 'integer',
        'doctor_id' => 'integer',
        'birthdate' => 'date',
        'password' => 'hashed',
        'threshold' => 'float',
        'settings' => 'array',
        'recipe_totals' => 'array',
    ];

    public function scopeLabs($query) { return $query->where('role', 'lab'); }
    public function scopeDoctors($query) { return $query->where('role', 'doctor'); }
    public function scopePatients($query) { return $query->where('role', 'patient'); }
    public function scopeAdmins($query) { return $query->where('role', 'admin'); }

    public function isLab(): bool { return $this->role === 'lab'; }
    public function isDoctor(): bool { return $this->role === 'doctor'; }
    public function isPatient(): bool { return $this->role === 'patient'; }
    public function isAdmin(): bool { return $this->role === 'admin'; }

    public function books(): HasMany
    {
        return $this->hasMany(Book::class, 'patient_id');
    }
    public function analyses(): HasMany
    {
        return $this->hasMany(Analysis::class, 'patient_id')->orWhere('doctor_id', $this->id);
    }

    public function allergens()
    {
        return $this->belongsToMany(Allergen::class, 'allergen_user', 'user_id', 'allergen_id');
    }

    public function lab(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lab_id')->where('role', 'lab');
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id')->where('role', 'doctor');
    }

    public function initials(): string
    {
        return Str::of($this->name)->explode(' ')->map(fn ($name) => Str::of($name)->substr(0, 1))->implode('');
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    public function canViewEditUserList()
    {
        return $this->isAdmin() || $this->isLab();
    }
    public function canEditLabSettings()
    {
        return $this->isAdmin() || $this->isLab();
    }

    /**
     * Add a recipe to the user's favorites (settings JSON)
     */
    public function addToFavorites($recipeId)
    {
        $settings = $this->settings ?? [];
        $favorites = isset($settings['favorites']) && is_array($settings['favorites']) ? $settings['favorites'] : [];
        if (!in_array($recipeId, $favorites)) {
            $favorites[] = $recipeId;
        }
        $settings['favorites'] = $favorites;
        $this->settings = array_merge($this->settings ?? [], $settings);
        $this->save();
    }

    /**
     * Remove a recipe from the user's favorites (settings JSON)
     */
    public function removeFromFavorites($recipeId)
    {
        $settings = $this->settings ?? [];
        $favorites = isset($settings['favorites']) && is_array($settings['favorites']) ? $settings['favorites'] : [];
        $favorites = array_filter($favorites, fn($id) => $id != $recipeId);
        $settings['favorites'] = array_values($favorites);
        $this->settings = array_merge($this->settings ?? [], $settings);
        $this->save();
    }

    /**
     * Get the user's diet preferences from settings.
     */
    public function getDietPreferences(): array
    {
        $settings = $this->settings ?? [];
        return isset($settings['diet_preferences']) && is_array($settings['diet_preferences']) ? $settings['diet_preferences'] : [];
    }

    /**
     * Set the user's diet preferences in settings.
     */
    public function setDietPreferences(array $diets): void
    {
        $settings = $this->settings ?? [];
        $settings['diet_preferences'] = $diets;
        $this->settings = $settings;
        $this->save();
    }
}
