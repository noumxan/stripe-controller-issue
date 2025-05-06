<?php

namespace App\Models;

use App\Enums\MediaCollections;
use App\Enums\RoleNames;
use App\Enums\Subscription\SubscriptionStripeStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Cashier\Billable;
use Laravel\Cashier\Subscription;
use Spatie\MediaLibrary\Exceptions\InvalidConversion;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use App\Models\SubscriptionItem;
use App\Models\SubscriptionPlan;

/**
 * Class Practice
 * @property int $id
 * @property string $unique_id
 * @property string $name
 * @property string $phone_number
 * @property string $email
 * @property string $website
 * @property bool $booking_portal_active
 * @property bool $sms_enabled
 * @property string $connected_stripe_account_id
 * @property string $stripe_id
 * @property string $mailerlite_api_key
 * @property string $rehabmypatient_api_key
 * @property string $pm_type
 * @property string $pm_last_four
 * @property string $trial_ends_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 *
 * @property-read Collection|null $users
 * @property-read Address|null $address
 * @property-read Collection|null $practice_staff
 * @property-read Collection|null $admins
 * @property-read Collection|null $practitioners
 * @property-read Collection|null $reception
 * @property-read Collection|null $patients
 * @property-read Collection|null $parentServices
 * @property-read Collection|null $services
 * @property-read Collection|null $sms
 *
 * @property-read Subscription $eligible_for_trial
 * @property-read Collection $subscriptions
 * @property-read Subscription $active_subscription
 * @property-read bool $has_active_subscription
 * @property-read StripeSubscriptionDetail|null $stripe_subscription_detail
 * @property-read StripeSmsSubscriptionDetail|null $stripe_sms_subscription_detail
 * @property-read Carbon|null $subscription_renewal_date
 * @property-read Subscription|null $resumable_subscription
 * @property-read bool $has_resumable_subscription
 * @property-read int $monthly_subscription_price
 * @property-read int $monthly_sms_count
 * @property-read int $monthly_sms_price
 * @property-read int $total_monthly_price
 * @property-read bool $subscription_can_add_users
 * @property-read string|null $logo_as_base64
 * @property-read array|null $logo_as_base64_key
 * @property-read string $logo
 * @property-read string|null $terms_as_base64
 * @property-read array|null $terms_as_base64_key
 * @property-read string|null $terms_file_name
 * @property-read string $terms
 * @property-read string|null $policy_as_base64
 * @property-read array|null $policy_as_base64_key
 * @property-read string|null $policy_file_name
 * @property-read string $privacy_policy
 * @property-read string|null $service_email_address
 */
class Practice extends Model implements HasMedia
{
    use HasFactory;
    use SoftDeletes;
    use Billable;
    use InteractsWithMedia;
    use Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'unique_id',
        'name',
        'email',
        'booking_portal_active',
        'booking_reminders_enabled',
        'sms_enabled',
        'connected_stripe_account_id',
        'stripe_id',
        'mailerlite_api_key',
        'rehabmypatient_api_key',
        'pm_type',
        'pm_last_four',
        'trial_ends_at',
        'currency',
        'disable_notifications',
        'timezone',
        'sms_from_name',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = ['connected_stripe_account_id'];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'booking_portal_active' => 'boolean',
        'booking_reminders_enabled' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get all of the locations for the Practice
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    /**
     * Practice::user();
     *
     * @return HasMany
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Practice::treatmentPlans;
     *
     * @return HasMany
     */
    public function treatmentPlans(): HasMany
    {
        return $this->hasMany(TreatmentPlan::class);
    }

    /**
     * Practice::treatmentPlanTemplates;
     *
     * @return HasMany
     */
    public function treatmentPlanTemplates(): HasMany
    {
        return $this->hasMany(TreatmentPlanTemplate::class);
    }

    /**
     * Practice::appointments();
     *
     * @return HasManyThrough
     */
    public function appointments(): HasManyThrough
    {
        return $this->hasManyThrough(Appointment::class, User::class, 'practice_id', 'practitioner_id', 'id', 'id');
    }

    /**
     * Practice::getPracticeStaffAttribute()
     *
     * @return Builder[]|Collection|HasMany[]
     */
    public function getPracticeStaffAttribute()
    {
        return $this->users()
            ->whereHas('roles', function ($query) {
                $query->where(function ($query) {
                    $query->orWhere('name', RoleNames::PRACTICE_ADMIN);
                    $query->orWhere('name', RoleNames::PRACTITIONER);
                    $query->orWhere('name', RoleNames::RECEPTION);
                });
            })
            ->get();
    }

    /**
     * Practice::getAdminsAttribute()
     *
     * @return Builder[]|Collection|HasMany[]
     */
    public function getAdminsAttribute()
    {
        return $this->users()
            ->whereHas('roles', function ($query) {
                $query->where('name', RoleNames::PRACTICE_ADMIN);
            })
            ->get();
    }

    /**
     * Practice::getPractitionersAttribute()
     *
     * @return Builder[]|Collection|HasMany[]
     */
    public function getPractitionersAttribute()
    {
        return $this->users()
            ->whereHas('roles', function ($query) {
                $query->where('name', RoleNames::PRACTITIONER);
            })
            ->get();
    }

    /**
     * Practice::getReceptionAttribute()
     *
     * @return Builder[]|Collection|HasMany[]
     */
    public function getReceptionAttribute()
    {
        return $this->users()
            ->whereHas('roles', function ($query) {
                $query->where('name', RoleNames::RECEPTION);
            })
            ->get();
    }

    /**
     * Practice::getPatientsAttribute()
     *
     * @return Builder[]|Collection|HasMany[]
     */
    public function getPatientsAttribute()
    {
        return $this->users()
            ->whereHas('roles', function ($query) {
                $query->where('name', RoleNames::PATIENT);
            })
            ->get();
    }

    /**
     * Practice::getPatientsAttribute()
     *
     * @return Builder[]|HasMany[]
     */
    public function patientsRelation(): HasMany
    {
        return $this->hasMany(User::class)->whereHas('roles', function ($query) {
            $query->where('name', RoleNames::PATIENT);
        });
    }

    /**
     * Practice::parentServices()
     *
     * @return HasMany
     */
    public function parentServices(): HasMany
    {
        return $this->hasMany(ParentService::class);
    }

    /**
     * Practice::services()
     *
     * @return HasManyThrough
     */
    public function services(): HasManyThrough
    {
        return $this->hasManyThrough(
            Service::class,
            ParentService::class,
            'practice_id',
            'parent_service_id',
            'id',
            'id',
        );
    }

    /**
     * Practice::sms()
     *
     * @return HasMany
     */
    public function sms(): HasMany
    {
        return $this->hasMany(Sms::class);
    }

        /**
         * Get all custom subscription items attached to this practice.
         *
         * @return HasMany
         */
        public function subscriptionItems(): HasMany
        {
            return $this->hasMany(SubscriptionItem::class);
        }

        /**
         * Get the active subscription item for this practice.
         *
         * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
         */
        public function activeSubscriptionItem()
        {
            return $this->belongsTo(SubscriptionItem::class, 'active_subscription_id');
        }

        /**
         * Get the subscription plan this practice is assigned to.
         *
         * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
         */
        public function subscriptionPlan()
        {
            return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
        }

    public function getIsOnTrialSubscriptionAttribute(): bool
    {
        return !$this->active_subscription && $this->trial_days_remaining > 0;
    }

    public function getTrialDaysRemainingAttribute(): int
    {
        if (in_array($this->unique_id, config('app.infinite_trial'))) {
            return 365;
        }

        if ($this->created_at->addDays(config('app.trial_days'))->lt(now())) {
            return 0;
        }

        return now()->diffInDays($this->created_at->addDays(config('app.trial_days')));
    }

    /**
     * Practice::getEligibleForTrialAttribute()
     *
     * @return bool
     */
    public function getEligibleForTrialAttribute(): bool
    {
        return count($this->subscriptions) === 0;
    }

    /**
     * Practice::getActiveSubscriptionAttribute()
     *
     * @return mixed
     */
    public function getActiveSubscriptionAttribute()
    {
        $activeSubscription = $this->subscriptions()->active()->first();

        if (blank($activeSubscription)) {
            return null;
        }

        $stripePlans = StripeSubscriptionDetail::all();
        foreach ($stripePlans as $plan) {
            foreach ($activeSubscription->items as $item) {
                if ($plan->stripe_product_id === $item->stripe_price) {
                    $activeSubscription->stripe_price = $item->stripe_price;
                    break;
                }
            }

            if (filled($activeSubscription->stripe_price)) {
                break;
            }
        }

        return $activeSubscription;
    }

    /**
     * Practice::getHasActiveSubscriptionAttribute()
     *
     * @return bool
     */
    public function getHasActiveSubscriptionAttribute(): bool
    {
        return filled($this->active_subscription);
    }

    /**
     * Practice::getStripeSubscriptionDetailAttribute()
     *
     * @return null
     */
    public function getStripeSubscriptionDetailAttribute()
    {
        $subscription = $this->active_subscription;

        if (blank($subscription)) {
            return null;
        }

        $stripeSubscriptionPlan = null;
        $stripePlans = StripeSubscriptionDetail::all();

        foreach ($stripePlans as $plan) {
            foreach ($subscription->items as $item) {
                if ($plan->stripe_product_id === $item->stripe_price) {
                    $stripeSubscriptionPlan = $plan;
                    break 2;
                }
            }
        }

        return $stripeSubscriptionPlan;
    }

    /**
     * Practice::getStripeSmsSubscriptionDetailAttribute()
     *
     * @return null
     */
    public function getStripeSmsSubscriptionDetailAttribute()
    {
        $subscription = $this->active_subscription;

        if (blank($subscription)) {
            return null;
        }

        $stripeSmsSubscriptionPlan = null;
        $stripePlans = StripeSmsSubscriptionDetail::all();
        foreach ($stripePlans as $plan) {
            foreach ($subscription->items as $item) {
                if ($plan->stripe_product_id === $item->stripe_price) {
                    $stripeSmsSubscriptionPlan = $plan;
                    break;
                }
            }

            if (filled($stripeSmsSubscriptionPlan)) {
                break;
            }
        }

        return $stripeSmsSubscriptionPlan;
    }

    /**
     * Practice::getSubscriptionRenewalDateAttribute()
     *
     * @return Carbon|null
     */
    public function getSubscriptionRenewalDateAttribute(): ?Carbon
    {
        $subscription = $this->active_subscription;

        if (blank($subscription)) {
            return null;
        }

        return Carbon::createFromTimeStamp($subscription->asStripeSubscription()->current_period_end);
    }

    /**
     * Practice::getResumableSubscriptionAttribute()
     *
     * @return null
     */
    public function getResumableSubscriptionAttribute()
    {
        $subscription = $this->subscriptions()->cancelled()->orderBy('ends_at', 'DESC')->first();

        return blank($subscription) ? null : $subscription;
    }

    /**
     * Practice::getHasResumableSubscriptionAttribute()
     *
     * @return bool
     */
    public function getHasResumableSubscriptionAttribute(): bool
    {
        return filled($this->resumable_subscription);
    }

    /**
     * Practice::getMonthlySmsPriceAttribute()
     *
     * @return int
     */
    public function getMonthlySubscriptionPriceAttribute(): int
    {
        /** @var StripeSubscriptionDetail $subscriptionDetails */
        $subscriptionDetails = StripeSubscriptionDetail::where(
            'stripe_product_id',
            '=',
            $this->active_subscription->stripe_price,
        )->first();

        $baseSubscriptionCost = $subscriptionDetails->price;
        $additionalSubscriptionCost = ($this->user_count - 1) * $subscriptionDetails->additional_user_price;

        return $baseSubscriptionCost + $additionalSubscriptionCost;
    }

    /**
     * Practice::getMonthlySmsCountAttribute()
     *
     * @return int
     */
    public function getMonthlySmsCountAttribute(): int
    {
        $activeSubscription = $this->active_subscription;
        $stripe = $this->stripe_sms_subscription_detail;

        try {
            $records = $activeSubscription->usageRecordsFor($stripe->stripe_product_id);
        } catch (\Exception $e) {
            return 0;
        }

        $currentCount = 0;
        foreach ($records as $record) {
            if (blank($record->invoice)) {
                $currentCount = $record->total_usage;
                break;
            }
        }

        return $currentCount;
    }

    /**
     * Practice:getMonthlySmsPriceAttribute()
     *
     * @return int
     */
    public function getMonthlySmsPriceAttribute(): int
    {
        $stripe = $this->stripe_sms_subscription_detail;

        return $this->monthly_sms_count * $stripe->price;
    }

    /**
     * Practice::getTotalMonthlyPriceAttribute()
     *
     * @return int
     */
    public function getTotalMonthlyPriceAttribute(): int
    {
        return $this->monthly_subscription_price + $this->monthly_sms_price;
    }

    /**
     * Practice::getSubscriptionCanAddUsersAttribute()
     *
     * @return false
     */
    public function getSubscriptionCanAddUsersAttribute(): bool
    {
        $subscription = $this->active_subscription;

        if (!$subscription) {
            return $this->trialDaysRemaining >= 0;
        }

        switch ($subscription->stripe_status) {
            case SubscriptionStripeStatus::CANCELLED:
                return false;
            case SubscriptionStripeStatus::ACTIVE:
            case SubscriptionStripeStatus::TRIAL:
            default:
                return true;
        }
    }

    public function getUserCountAttribute(): int
    {
        return $this->users()
            ->leftJoin('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
            ->leftJoin('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->where('roles.name', '<>', 'Patient')
            ->where('roles.name', '<>', 'Reception')
            ->whereNull('users.discounted')
            ->count();
    }

    public function getPatientCountAttribute(): int
    {
        return $this->users()
            ->leftJoin('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
            ->leftJoin('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->where('roles.name', 'Patient')
            ->count();
    }

    public function getForms(): Collection
    {
        $practiceUsers = $this->practitioners->pluck('id')->merge($this->admins->pluck('id'));

        return AppointmentForm::whereIn('created_by', $practiceUsers)->get();
    }

    public function getCurrencies(): \Illuminate\Support\Collection
    {
        return collect(config('currencies'))->filter(function ($currency) {
            return $currency['enabled'];
        });
    }

    public function getTimezones(): \Illuminate\Support\Collection
    {
        return collect(config('timezones'))->filter(function ($currency) {
            return $currency['enabled'];
        });
    }

    /**
     * Handle multiple currencies
     */
    public function getCurrencyTemplateAttribute(): string
    {
        $currency = collect(config('currencies'))
            ->filter(function ($currency) {
                return $currency['code'] === $this->currency;
            })
            ->first();

        return $currency['template'];
    }

    public function priceFormat($price): string
    {
        return str_replace('%amount%', $price, $this->currency_template);
    }
}
