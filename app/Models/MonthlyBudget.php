<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MonthlyBudget extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'year',
        'month',
        'total_budget_pkr',
        'number_of_employees',
        'number_of_contractors',
        'employee_pool_input',
        'employee_bonus_pool_pkr',
        'contractor_bonus_pool_pkr',
        'consistent_contributor_pool_pkr',
        'high_achiever_pool_pkr',
        'team_total_points',
        'points_value_pkr',
        'most_improved_award_pkr',
        'first_place_award_pkr',
        'second_place_award_pkr',
        'third_place_award_pkr',
        'contractor_of_the_month_award_pkr',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
        'total_budget_pkr' => 'decimal:2',
        'number_of_employees' => 'integer',
        'number_of_contractors' => 'integer',
        'employee_bonus_pool_pkr' => 'decimal:2',
        'contractor_bonus_pool_pkr' => 'decimal:2',
        'consistent_contributor_pool_pkr' => 'decimal:2',
        'high_achiever_pool_pkr' => 'decimal:2',
        'team_total_points' => 'decimal:2',
        'points_value_pkr' => 'decimal:4',
        'most_improved_award_pkr' => 'decimal:2',
        'first_place_award_pkr' => 'decimal:2',
        'second_place_award_pkr' => 'decimal:2',
        'third_place_award_pkr' => 'decimal:2',
        'contractor_of_the_month_award_pkr' => 'decimal:2',
    ];

    /**
     * Get the monthly points records for this budget period.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function monthlyPoints()
    {
        return $this->hasMany(MonthlyPoint::class, ['year', 'month'], ['year', 'month']);
    }

    /**
     * Scope a query to only include budgets for a specific month and year.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  int  $year
     * @param  int  $month
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForPeriod($query, $year, $month)
    {
        return $query->where('year', $year)->where('month', $month);
    }
}
