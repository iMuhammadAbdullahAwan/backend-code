<?php

namespace App\Services;

use App\Models\BillingTariffModel;
use App\Models\SensorReadingModel;

/**
 * Composes the deterministic Month-to-Date billing + end-of-cycle forecast.
 *
 * This is pure orchestration: billing-period math lives in
 * BillingPeriodResolver, consumption aggregation lives in
 * SensorReadingModel::sumKwhBetween(), and cost calculation lives in the
 * existing BillingTariffModel::calculateCost() (flat or progressive-slab).
 * None of those calculations are duplicated here.
 *
 * Forecasting always predicts total units first and then runs that total
 * through calculateCost() a second time — never by scaling the MTD bill —
 * because calculateCost() is progressive for slab tariffs, so
 * bill(2x) != bill(x) * 2 in general.
 */
class BillingForecastService
{
    protected BillingPeriodResolver $periodResolver;
    protected SensorReadingModel $sensorReadingModel;
    protected BillingTariffModel $billingTariffModel;

    public function __construct(
        ?BillingPeriodResolver $periodResolver = null,
        ?SensorReadingModel $sensorReadingModel = null,
        ?BillingTariffModel $billingTariffModel = null
    ) {
        $this->periodResolver = $periodResolver ?? new BillingPeriodResolver();
        $this->sensorReadingModel = $sensorReadingModel ?? new SensorReadingModel();
        $this->billingTariffModel = $billingTariffModel ?? new BillingTariffModel();
    }

    public function forecast(string $deviceId, ?\DateTimeImmutable $now = null): array
    {
        $tariff = $this->billingTariffModel->getTariffForDevice($deviceId);
        $period = $this->periodResolver->resolve((int) $tariff['billing_cycle_start_day'], $now);

        $mtdUnits = $this->sensorReadingModel->sumKwhBetween(
            $deviceId,
            $period->startDateTimeString(),
            $period->nowDateTimeString()
        );

        $mtdBill = $this->billingTariffModel->calculateCost($mtdUnits, $tariff);

        // avg_daily_units and predicted_units are kept at full precision
        // internally; rounding happens only once, at response assembly.
        $avgDailyUnits = $period->elapsedDays > 0 ? $mtdUnits / $period->elapsedDays : 0.0;
        $predictedRemainingUnits = $avgDailyUnits * $period->remainingDays;
        $predictedUnits = $mtdUnits + $predictedRemainingUnits;

        $predictedBill = $this->billingTariffModel->calculateCost($predictedUnits, $tariff);

        return [
            'device_id'                => $deviceId,
            'billing_period_start'     => $period->start->format('Y-m-d'),
            'billing_period_end'       => $period->endDateString(),
            'current_date'             => $period->todayDateString(),
            'elapsed_days'             => $period->elapsedDays,
            'total_days'               => $period->totalDays,
            'remaining_days'           => $period->remainingDays,
            'mtd_units'                => round($mtdUnits, 4),
            'avg_daily_units'          => round($avgDailyUnits, 4),
            'mtd_bill'                 => round($mtdBill, 2),
            'predicted_units'          => round($predictedUnits, 4),
            'predicted_bill'           => round($predictedBill, 2),
            // Both operands already rounded to 2dp by calculateCost(); a
            // final round() here absorbs any binary floating-point noise
            // from the subtraction itself.
            'remaining_estimated_bill' => round($predictedBill - $mtdBill, 2),
            'currency'                 => $tariff['currency'],
            'currency_symbol'          => $tariff['currency_symbol'],
            'rate_per_kwh'             => (float) $tariff['rate_per_kwh'],
            'tariff_type'              => $tariff['tariff_type'],
            'mtd_bill_note'            => 'Calculated accrued bill based on recorded consumption so far. It is not necessarily an official bill already issued by the utility.',
            'predicted_bill_note'      => 'Estimate only: assumes the average daily consumption rate observed so far continues for the remainder of the billing cycle, priced through the currently configured tariff.',
        ];
    }
}
