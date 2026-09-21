<?php

namespace App\Models;

use CodeIgniter\Model;

class BillingTariffModel extends Model
{
    protected $table = 'billing_tariffs';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'device_id',
        'rate_per_kwh',
        'currency',
        'currency_symbol',
        'billing_cycle_start_day',
        'tariff_type',
        'slabs',
    ];

    protected $beforeInsert = ['validateSlabConfig'];
    protected $beforeUpdate = ['validateSlabConfig'];

    protected function validateSlabConfig(array $data): array
    {
        if (!isset($data['data'])) {
            return $data;
        }

        $tariffType = $data['data']['tariff_type'] ?? null;

        // If tariff_type is explicitly 'flat', we don't validate slabs.
        if ($tariffType === 'flat') {
            return $data;
        }

        // If tariff_type is 'slab' or we are updating slabs without explicitly changing tariff_type.
        $isSlabType = $tariffType === 'slab';
        $hasSlabsData = array_key_exists('slabs', $data['data']);
        
        if ($isSlabType && $hasSlabsData && $data['data']['slabs'] === null) {
            throw new \InvalidArgumentException('Slab tariff must contain at least one slab.');
        }

        if (($isSlabType && $hasSlabsData) || ($hasSlabsData && $data['data']['slabs'] !== null)) {
            $slabs = $data['data']['slabs'];

            if (is_string($slabs)) {
                $decoded = json_decode($slabs, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \InvalidArgumentException('Invalid slab configuration.');
                }
                $slabs = $decoded;
            }

            if (!is_array($slabs)) {
                throw new \InvalidArgumentException('Invalid slab configuration.');
            }

            if (empty($slabs)) {
                throw new \InvalidArgumentException('Slab tariff must contain at least one slab.');
            }

            if (array_keys($slabs) !== range(0, count($slabs) - 1)) {
                throw new \InvalidArgumentException('Invalid slab configuration.');
            }

            $previousMax = null;

            foreach ($slabs as $i => $slab) {
                if (!is_array($slab)) {
                    throw new \InvalidArgumentException('Invalid slab configuration.');
                }

                if (!array_key_exists('min_kwh', $slab) || !array_key_exists('max_kwh', $slab) || !array_key_exists('rate', $slab)) {
                    throw new \InvalidArgumentException('Invalid slab configuration.');
                }

                if (!is_numeric($slab['rate']) || $slab['rate'] < 0) {
                    throw new \InvalidArgumentException('Slab rate must be a non-negative number.');
                }

                $isLast = ($i === count($slabs) - 1);

                if ($slab['max_kwh'] !== null) {
                    if (!is_numeric($slab['max_kwh'])) {
                        throw new \InvalidArgumentException('Invalid slab configuration.');
                    }
                    if ($slab['max_kwh'] < 0) {
                        throw new \InvalidArgumentException('Invalid slab configuration.');
                    }
                    if ($previousMax !== null && $slab['max_kwh'] <= $previousMax) {
                        throw new \InvalidArgumentException('Slab maximum values must be strictly increasing.');
                    }
                    $previousMax = (float) $slab['max_kwh'];
                } else {
                    if (!$isLast) {
                        throw new \InvalidArgumentException('Slab maximum values must be strictly increasing.');
                    }
                }
            }

            $lastSlab = end($slabs);
            if ($lastSlab['max_kwh'] !== null) {
                throw new \InvalidArgumentException('Slab tariff must end with an open-ended slab.');
            }

            // Ensure JSON encoding if it came in as array
            if (is_array($data['data']['slabs'])) {
                $data['data']['slabs'] = json_encode($slabs);
            }
        }

        return $data;
    }

    /**
     * Returns the tariff for the given device, falling back to the global
     * default tariff (device_id IS NULL), and finally to a hardcoded default
     * if no tariff has been configured in the database at all.
     */
    public function getTariffForDevice(string $deviceId): array
    {
        $tariff = $this->where('device_id', $deviceId)->first();

        if (!$tariff) {
            $tariff = $this->where('device_id', null)->first();
        }

        if (!$tariff) {
            $tariff = [
                'device_id'               => $deviceId,
                'rate_per_kwh'            => 32.50,
                'currency'                => 'PKR',
                'currency_symbol'         => 'Rs.',
                'billing_cycle_start_day' => 1,
                'tariff_type'             => 'flat',
                'slabs'                   => null,
                'updated_at'              => date('Y-m-d H:i:s'),
            ];
        }

        $tariff['slabs'] = $tariff['slabs'] ? json_decode($tariff['slabs'], true) : null;

        return $tariff;
    }

    /**
     * Calculates the cost for a given consumption using the tariff's rate,
     * applying cumulative slab pricing when tariff_type is 'slab'.
     *
     * Slab bounds are inclusive, human-readable bracket labels (e.g.
     * [0,100], [101,300], [301,null] means 100, 200, then open-ended
     * units) — this is the convention used throughout the app's tariff
     * planning docs and JSON. A bounded slab's capacity is therefore
     * `max_kwh - previousSlab.max_kwh` (starting from 0), NOT
     * `max_kwh - min_kwh`: using this slab's own min_kwh undercounts by
     * exactly 1 unit, because min_kwh is set to `previousMax + 1` purely
     * for human-readable labeling. min_kwh is never read here — it does
     * not affect pricing, by design.
     *
     * @throws \RuntimeException if consumption exceeds every defined slab
     *                           and no slab has max_kwh = null to absorb
     *                           the remainder — an incomplete tariff
     *                           configuration must never silently underbill.
     */
    public function calculateCost(float $kwh, array $tariff): float
    {
        if ($tariff['tariff_type'] !== 'slab' || empty($tariff['slabs'])) {
            return round($kwh * (float) $tariff['rate_per_kwh'], 2);
        }

        $remaining = $kwh;
        $cost = 0.0;
        $previousMax = 0.0;

        foreach ($tariff['slabs'] as $slab) {
            if ($remaining <= 0) {
                break;
            }

            $max = $slab['max_kwh'] !== null ? (float) $slab['max_kwh'] : null;
            $slabCapacity = $max !== null ? ($max - $previousMax) : $remaining;
            
            if ($slabCapacity < 0) {
                throw new \RuntimeException('Corrupted slab configuration: negative slab capacity detected.');
            }
            
            $unitsInSlab = min($remaining, $slabCapacity);

            $cost += $unitsInSlab * (float) $slab['rate'];
            $remaining -= $unitsInSlab;

            if ($max !== null) {
                $previousMax = $max;
            }
        }

        if ($remaining > 1e-9) {
            throw new \RuntimeException(sprintf(
                'Incomplete slab tariff configuration: %.4f of %.4f kWh could not be priced because no open-ended slab (max_kwh = null) covers consumption beyond %.4f kWh.',
                $remaining,
                $kwh,
                $previousMax
            ));
        }

        return round($cost, 2);
    }
}
