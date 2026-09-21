# Energy Monitoring System API Documentation

Welcome to the Energy Monitoring System API documentation. This document provides details about the available endpoints, their request formats, and exact response structures as returned by the controllers.

## Base URL
`http://your-domain/api`

---

## Authentication
Some endpoints require a secret key for access. This key can be provided via a query parameter or a request header.

*   **Query Parameter:** `?secret=YOUR_SYNC_SECRET`
*   **Request Header:** `X-Sync-Secret: YOUR_SYNC_SECRET`

---

## Response Format
The API follows a standard response structure for all controller-based endpoints:

### Success Response
```json
{
  "status": "success",
  "data": { ... },
  "message": "Action completed successfully"
}
```

### Error Response
```json
{
  "status": "error",
  "message": "Description of the error",
  "code": 404
}
```

---

## 1. Devices

### `GET /devices`
Retrieve a list of all registered devices.

**Response Example:**
```json
{
  "status": "success",
  "data": [
    {
      "id": "1",
      "device_id": "energy",
      "device_name": "Main Meter",
      "location": "Main Panel",
      "status": "active",
      "created_at": "2026-05-12 10:00:00",
      "updated_at": "2026-05-12 10:00:00"
    }
  ],
  "message": "Devices retrieved successfully"
}
```

### `GET /devices/{device_id}`
Retrieve details for a specific device.

**Response Example:**
```json
{
  "status": "success",
  "data": {
    "id": "1",
    "device_id": "energy",
    "device_name": "Main Meter",
    "location": "Main Panel",
    "status": "active",
    "created_at": "2026-05-12 10:00:00",
    "updated_at": "2026-05-12 10:00:00"
  },
  "message": ""
}
```

### `GET /devices/{device_id}/status`
Retrieve the latest status and sensor reading for a specific device.

**Response Example:**
```json
{
  "status": "success",
  "data": {
    "device": {
      "id": "1",
      "device_id": "energy",
      "device_name": "Main Meter",
      "location": "Main Panel",
      "status": "active",
      "created_at": "2026-05-12 10:00:00",
      "updated_at": "2026-05-12 10:00:00"
    },
    "latest_reading": {
      "id": "500",
      "device_id": "energy",
      "current": "1.45",
      "voltage": "230.1",
      "temperature": "35.2",
      "power_watt": "333.645",
      "energy": "12.5",
      "kwh": "0.12",
      "power": "0.33",
      "recorded_at": "2026-05-12 12:00:00"
    }
  },
  "message": ""
}
```

### `GET /devices/{device_id}/export`
Export energy consumption, billing predictions, or AI alerts data as CSV or PDF files.

**Parameters (can be passed via Query String OR JSON Request Body):**
*   `type` (required): The type of report to generate. Valid values: `consumption`, `billing`, `alerts`.
*   `format` (required): The file format to return. Valid values: `csv`, `pdf`.
*   `start_date` (optional): Filter data from this date (format: `YYYY-MM-DD`).
*   `end_date` (optional): Filter data up to this date (format: `YYYY-MM-DD`).

**Response for `format=csv`:**
Returns raw binary stream downloading as `smart_energy_report.csv` with `Content-Type: text/csv`.

**Response for `format=pdf`:**
Returns raw binary stream downloading as `smart_energy_invoice.pdf` or report with `Content-Type: application/pdf`.

**Error Response Example (404 Not Found or 400 Bad Request):**
```json
{
  "status": "error",
  "message": "No data found for this device or date range.",
  "code": 404
}
```

---

## 2. Sensors

### `GET /sensors/latest`
Retrieve the most recent reading for all devices.

**Response Example:**
```json
{
  "status": "success",
  "data": [
    {
      "id": "500",
      "device_id": "energy",
      "current": "1.45",
      "voltage": "230.1",
      "temperature": "35.2",
      "power_watt": "333.645",
      "energy": "12.5",
      "kwh": "0.12",
      "power": "0.33",
      "recorded_at": "2026-05-12 12:00:00"
    }
  ],
  "message": ""
}
```

### `GET /sensors/{device_id}/latest`
Retrieve the latest reading for a specific device.

**Response Example:**
```json
{
  "status": "success",
  "data": {
    "id": "500",
    "device_id": "energy",
    "current": "1.45",
    "voltage": "230.1",
    "temperature": "35.2",
    "power_watt": "333.645",
    "energy": "12.5",
    "kwh": "0.12",
    "power": "0.33",
    "recorded_at": "2026-05-12 12:00:00"
  },
  "message": ""
}
```

### `GET /sensors/{device_id}/history`
Retrieve historical readings for a specific device.
Query parameters: `?from=YYYY-MM-DD&to=YYYY-MM-DD&limit=100`

**Response Example:**
```json
{
  "status": "success",
  "data": [
    {
      "id": "500",
      "device_id": "energy",
      "current": "1.45",
      "voltage": "230.1",
      "temperature": "35.2",
      "power_watt": "333.645",
      "energy": "12.5",
      "kwh": "0.12",
      "power": "0.33",
      "recorded_at": "2026-05-12 12:00:00"
    }
  ],
  "message": ""
}
```

### `GET /sensors/{device_id}/stats`
Retrieve pre-aggregated time-series statistics for the 4 analytics graph cards (energy, voltage, current, temperature).
Query parameters: `?period=daily|weekly|monthly` (defaults to `daily`).

- **`daily`**: one entry per day (`Mon`..`Sun`) of the current week.
- **`weekly`**: one entry per week (`Week 1`..`Week N`) of the current month.
- **`monthly`**: one entry per month (`Jan`..`Dec`) of the current year.

An interval with no stored readings (device offline / backend down) returns `0.00` for all its metrics. `averages.avg_voltage`, `avg_current` and `avg_temperature` are computed only over intervals that had real readings; `avg_kwh`/`total_kwh` include every interval, since a `0.00` day genuinely means zero consumption.

**Response Example:**
```json
{
  "status": "success",
  "data": {
    "period": "daily",
    "averages": {
      "avg_kwh": 0.26,
      "avg_voltage": 228.5,
      "avg_current": 1.15,
      "avg_temperature": 29.2,
      "total_kwh": 1.84
    },
    "series": [
      { "label": "Mon", "date": "2026-09-14", "kwh": 0.00, "voltage": 0.00, "current": 0.00, "temperature": 0.00 },
      { "label": "Tue", "date": "2026-09-15", "kwh": 0.00, "voltage": 0.00, "current": 0.00, "temperature": 0.00 },
      { "label": "Wed", "date": "2026-09-16", "kwh": 0.45, "voltage": 229.1, "current": 1.12, "temperature": 28.9 },
      { "label": "Thu", "date": "2026-09-17", "kwh": 0.32, "voltage": 227.8, "current": 0.95, "temperature": 29.5 },
      { "label": "Fri", "date": "2026-09-18", "kwh": 0.85, "voltage": 230.4, "current": 1.54, "temperature": 30.1 },
      { "label": "Sat", "date": "2026-09-19", "kwh": 0.22, "voltage": 228.0, "current": 0.88, "temperature": 28.4 },
      { "label": "Sun", "date": "2026-09-20", "kwh": 0.00, "voltage": 0.00, "current": 0.00, "temperature": 0.00 }
    ]
  },
  "message": ""
}
```

### `POST /sensors/test-insert`
Insert a manual test reading. **Requires Authentication.**

**Request Body:**
```json
{
  "device_id": "energy",
  "current": 1.2,
  "voltage": 220.5,
  "temperature": 32.0,
  "kwh": 0.05,
  "power": 0.0,
  "energy": 10.5,
  "recorded_at": "2026-05-12 15:00:00"
}
```

**Response Example:**
```json
{
  "status": "success",
  "data": {
    "inserted_id": 501
  },
  "message": ""
}
```

---

## 3. Bills & Predictions

### `GET /bills/all`
Retrieve the latest bill prediction for each device.

**Response Example:**
```json
{
  "status": "success",
  "data": [
    {
      "id": "10",
      "device_id": "energy",
      "month": "2026-05",
      "predicted_kwh": "150.5",
      "predicted_cost": "45.15",
      "currency": "USD",
      "generated_at": "2026-05-12 10:00:00"
    }
  ],
  "message": ""
}
```

### `POST /bills/{device_id}/predict`
Generate a new AI-assisted bill prediction. Gemini AI guesses `predicted_kwh` from the last 30 raw sensor readings (a rolling window, not bounded to the calendar/billing month); `predicted_cost` and `currency` are always calculated by the backend from the device's server-managed tariff (flat rate or slabs), never from the AI's own guess. `rate_per_kwh` is included so the mobile client can verify the tariff that was applied. Each call also appends a row to `bill_predictions` (history log, not a per-month ledger).

**This is distinct from `GET /bills/{device_id}/mtd` below**: `/predict`'s `predicted_kwh` is an LLM estimate with no elapsed/remaining-day math; `/mtd` is fully deterministic, computed from actual recorded `kwh` over the real billing cycle. Use `/mtd` wherever a reproducible, explainable number is required; `/predict` remains for the existing AI-assisted flow and is unchanged by this feature.

**Response Example:**
```json
{
  "status": "success",
  "data": {
    "device_id": "energy",
    "month": "2026-05",
    "predicted_kwh": 150.5,
    "predicted_cost": 4891.25,
    "currency": "PKR",
    "generated_at": "2026-05-12 20:30:00",
    "id": 11,
    "rate_per_kwh": 32.50,
    "summary": "Based on the last 30 readings, your usage is stable..."
  },
  "message": ""
}
```

### `GET /bills/{device_id}/config`
Retrieve the server-managed billing tariff for a device (read-only on the mobile client). Falls back to the global default tariff if the device has no tariff of its own configured.

**Response Example:**
```json
{
  "status": "success",
  "data": {
    "device_id": "energy",
    "rate_per_kwh": 32.50,
    "currency": "PKR",
    "currency_symbol": "Rs.",
    "billing_cycle_start_day": 1,
    "tariff_type": "flat",
    "slabs": null,
    "last_updated": "2026-09-21 00:00:00"
  },
  "message": ""
}
```

### `GET /bills/{device_id}/mtd`
Deterministic Month-to-Date billing + end-of-cycle forecast. Unlike `/predict`, this endpoint **never calls Gemini** — `mtd_units`/`predicted_units` come from `SUM(sensor_readings.kwh)` over the resolved billing cycle, and `mtd_bill`/`predicted_bill` are both computed by the same `BillingTariffModel::calculateCost()` used everywhere else (flat or progressive slab). The billing cycle is resolved from `billing_tariffs.billing_cycle_start_day` (see `/config`); it does not have to be the calendar month.

`predicted_bill` is always calculated by running `predicted_units` through `calculateCost()` directly — never by scaling `mtd_bill` — because progressive slab tariffs are non-linear (`bill(2x) != bill(x) * 2`).

**Response Example:**
```json
{
  "status": "success",
  "data": {
    "device_id": "energy",
    "billing_period_start": "2026-09-01",
    "billing_period_end": "2026-09-30",
    "current_date": "2026-09-15",
    "elapsed_days": 15,
    "total_days": 30,
    "remaining_days": 15,
    "mtd_units": 180.0,
    "avg_daily_units": 12.0,
    "mtd_bill": 5850.0,
    "predicted_units": 360.0,
    "predicted_bill": 11700.0,
    "remaining_estimated_bill": 5850.0,
    "currency": "PKR",
    "currency_symbol": "Rs.",
    "rate_per_kwh": 32.50,
    "tariff_type": "flat",
    "mtd_bill_note": "Calculated accrued bill based on recorded consumption so far. It is not necessarily an official bill already issued by the utility.",
    "predicted_bill_note": "Estimate only: assumes the average daily consumption rate observed so far continues for the remainder of the billing cycle, priced through the currently configured tariff."
  },
  "message": ""
}
```

**Known limitations:**
- The tariff used is whatever `BillingTariffModel::getTariffForDevice()` currently resolves — there is no historical/effective-dated tariff versioning, so a mid-cycle rate change is applied retroactively to the whole cycle.
- `mtd_units`/`predicted_units` are only as accurate as the upstream `kwh` values pushed via Firebase sync; the backend does not independently validate or derive them from current/voltage.
- `mtd_bill` is a **calculated accrued estimate**, not an officially issued utility bill.

### `GET /bills/{device_id}/history`
Retrieve historical predictions for a device.
Query parameters: `?limit=12`

**Response Example:**
```json
{
  "status": "success",
  "data": [
    {
      "id": "11",
      "device_id": "energy",
      "month": "2026-05",
      "predicted_kwh": "150.5",
      "predicted_cost": "45.15",
      "currency": "USD",
      "generated_at": "2026-05-12 20:30:00"
    }
  ],
  "message": ""
}
```

---

## 4. AI Tips

### `GET /tips/all`
Retrieve the latest AI-generated tip for each device.

**Response Example:**
```json
{
  "status": "success",
  "data": [
    {
      "id": "25",
      "device_id": "energy",
      "tip_text": "Consider turning off appliances during peak hours.",
      "category": "energy saving",
      "generated_at": "2026-05-12 10:00:00"
    }
  ],
  "message": ""
}
```

### `POST /tips/{device_id}/generate`
Manually trigger new tips generation. **Requires Authentication.**

**Response Example:**
```json
{
  "status": "success",
  "data": [
    {
      "device_id": "energy",
      "tip_text": "Your AC temperature is set too low.",
      "category": "maintenance",
      "generated_at": "2026-05-12 20:35:00",
      "id": 26
    }
  ],
  "message": ""
}
```

### `GET /tips/{device_id}/history`
Retrieve history of tips for a device.
Query parameters: `?limit=20`

**Response Example:**
```json
{
  "status": "success",
  "data": [
    {
      "id": "26",
      "device_id": "energy",
      "tip_text": "Your AC temperature is set too low.",
      "category": "maintenance",
      "generated_at": "2026-05-12 20:35:00"
    }
  ],
  "message": ""
}
```

---

## 5. Firebase Synchronization

Endpoints for syncing data from Firebase. **Requires Authentication.** These return a non-standard success structure (no `data` wrapper).

### `GET /sync/sensors`
**Response:**
```json
{
  "synced": 5,
  "status": "ok"
}
```

### `GET /sync/devices`
**Response:**
```json
{
  "synced": 2,
  "status": "ok"
}
```
