<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Company extends Model
{
    protected $fillable = [
        'logo',
        'signature',
        'company_name',
        'if',
        'ice',
        'rc',
        'patente',
        'cnss',
        'email',
        'phone',
        'address',
        'city',
        'postal_code',
        'country',
        'website',
        'template_id',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_companies')
            ->using(UserCompany::class)
            ->withPivot('role_id')
            ->withTimestamps();
    }

    public function getRoleForUser(User $user): ?Role
    {
        $pivot = $this->users()->where('user_id', $user->id)->first()?->pivot;
        return $pivot ? Role::find($pivot->role_id) : null;
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function taxRates(): HasMany
    {
        return $this->hasMany(TaxRate::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function productCategories(): HasMany
    {
        return $this->hasMany(ProductCategory::class);
    }

    public function paymentConditions(): HasMany
    {
        return $this->hasMany(PaymentCondition::class);
    }

    public function paymentModes(): HasMany
    {
        return $this->hasMany(PaymentMode::class);
    }

    public function lateFeeInterests(): HasMany
    {
        return $this->hasMany(LateFeeInterest::class);
    }

    public function numberingSerie(): HasOne
    {
        return $this->hasOne(NumberingSerie::class);
    }

    public function documentSettings(): HasMany
    {
        return $this->hasMany(DocumentSetting::class);
    }

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(BankAccount::class);
    }

    public function quotes(): HasManyThrough
    {
        return $this->hasManyThrough(
            Quote::class,
            Document::class,
            'company_id',
            'id',
            'id',
            'documentable_id'
        )->where('documents.documentable_type', Quote::class)
            ->whereNotNull('documents.number');
    }

}