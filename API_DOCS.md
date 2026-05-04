# API Documentation

BASE_URL: https://yourdomain.com/api

**Standard Response Format:**
Every API endpoint returns responses in this standard format:

*Success Response:*
```json
{
  "status": "success",
  "data": <array or object>,
  "message": "optional message"
}
```

*Error Response:*
```json
{
  "status": "error",
  "message": "Human readable error message",
  "code": 404
}
```

---

## DEVICES

### `GET /devices`
Returns a list of all devices.
**Response `data` Example:**
```json
[
  {
    "id": "1",
    "device_id": "DEV001",
    "device_name": "Living Room AC",
    "location": "Living Room",
    "status": "active",
    "created_at": "2026-05-04 10:00:00",
    "updated_at": "2026-05-04 10:00:00"
  }
]
```

### `GET /devices/{id}`
Returns details for a single device.
**Response `data` Example:**
```json
{
  "id": "1",
  "device_id": "DEV001",
  "device_name": "Living Room AC",
  "location": "Living Room",
  "status": "active",
  "created_at": "2026-05-04 10:00:00",
  "updated_at": "2026-05-04 10:00:00"
}
```

### `GET /devices/{id}/status`
Returns the device details along with its latest sensor reading.
**Response `data` Example:**
```json
{
  "device": {
    "id": "1",
    "device_id": "DEV001",
    "device_name": "Living Room AC",
    "location": "Living Room",
    "status": "active"
  },
  "latest_reading": {
    "id": "1",
    "device_id": "DEV001",
    "current": "5.2000",
    "voltage": "220.0000",
    "temperature": "24.5000",
    "power_watt": "1144.0000",
    "recorded_at": "2026-05-04 10:05:00",
    "created_at": "2026-05-04 10:05:00"
  }
}
```

---

## SENSORS

### `GET /sensors/latest`
Returns the most recent reading for all active devices.
**Response `data` Example:**
```json
[
  {
    "id": "1",
    "device_id": "DEV001",
    "current": "5.2000",
    "voltage": "220.0000",
    "temperature": "24.5000",
    "power_watt": "1144.0000",
    "recorded_at": "2026-05-04 10:05:00",
    "created_at": "2026-05-04 10:05:00"
  }
]
```

### `GET /sensors/{id}/latest`
Returns the last recorded reading for a specific device.
**Response `data` Example:**
```json
{
  "id": "1",
  "device_id": "DEV001",
  "current": "5.2000",
  "voltage": "220.0000",
  "temperature": "24.5000",
  "power_watt": "1144.0000",
  "recorded_at": "2026-05-04 10:05:00",
  "created_at": "2026-05-04 10:05:00"
}
```

### `GET /sensors/{id}/history`
Returns historical readings. Supports query params: `?from=YYYY-MM-DD&to=YYYY-MM-DD&limit=100`.
**Response `data` Example:**
```json
[
  {
    "id": "1",
    "device_id": "DEV001",
    "current": "5.2000",
    "voltage": "220.0000",
    "temperature": "24.5000",
    "power_watt": "1144.0000",
    "recorded_at": "2026-05-04 10:05:00",
    "created_at": "2026-05-04 10:05:00"
  }
]
```

### `GET /sensors/{id}/stats`
Returns aggregated statistics for a device. Supports query param `?period=daily|weekly|monthly`.
**Response `data` Example:**
```json
{
  "period": "daily",
  "count": 144,
  "avg_current": 4.5,
  "max_current": 6.1,
  "min_current": 1.2,
  "avg_voltage": 220.5,
  "avg_temperature": 25.2,
  "avg_power": 992.25,
  "max_power": 1342.0
}
```

---

## BILLS

### `GET /bills/{id}/predict`
Calls Gemini AI to generate a bill prediction based on the last 30 readings and saves it.
**Response `data` Example:**
```json
{
  "device_id": "DEV001",
  "month": "2026-05",
  "predicted_kwh": 120.5,
  "predicted_cost": 36.15,
  "currency": "USD",
  "generated_at": "2026-05-04 12:00:00",
  "summary": "Based on current usage trends, your estimated bill will be around $36.15."
}
```

### `GET /bills/{id}/history`
Returns past bill predictions for the given device.
**Response `data` Example:**
```json
[
  {
    "id": "1",
    "device_id": "DEV001",
    "month": "2026-04",
    "predicted_kwh": "115.0000",
    "predicted_cost": "34.50",
    "currency": "USD",
    "generated_at": "2026-04-01 12:00:00",
    "created_at": "2026-04-01 12:00:00"
  }
]
```

### `GET /bills/all`
Returns the most recent prediction for each device.
**Response `data` Example:**
```json
[
  {
    "id": "2",
    "device_id": "DEV001",
    "month": "2026-05",
    "predicted_kwh": "120.5000",
    "predicted_cost": "36.15",
    "currency": "USD",
    "generated_at": "2026-05-04 12:00:00",
    "created_at": "2026-05-04 12:00:00"
  }
]
```

---

## AI TIPS

### `GET /tips/{id}`
Calls Gemini AI to generate practical energy-saving tips or maintenance alerts based on the last 10 readings and saves them.
**Response `data` Example:**
```json
[
  {
    "device_id": "DEV001",
    "tip_text": "Your AC is drawing higher current than usual. Consider cleaning the filters.",
    "category": "maintenance alert",
    "generated_at": "2026-05-04 12:00:00"
  },
  {
    "device_id": "DEV001",
    "tip_text": "Running the AC at 24°C instead of 22°C can save up to 10% on your energy bill.",
    "category": "energy saving",
    "generated_at": "2026-05-04 12:00:00"
  }
]
```

### `GET /tips/{id}/history`
Returns previously generated tips for a device.
**Response `data` Example:**
```json
[
  {
    "id": "1",
    "device_id": "DEV001",
    "tip_text": "Your AC is drawing higher current than usual. Consider cleaning the filters.",
    "category": "maintenance alert",
    "generated_at": "2026-05-04 12:00:00",
    "created_at": "2026-05-04 12:00:00"
  }
]
```

### `GET /tips/all`
Returns the most recent tip for each device.
**Response `data` Example:**
```json
[
  {
    "id": "5",
    "device_id": "DEV001",
    "tip_text": "Running the AC at 24°C instead of 22°C can save up to 10% on your energy bill.",
    "category": "energy saving",
    "generated_at": "2026-05-04 12:00:00",
    "created_at": "2026-05-04 12:00:00"
  }
]
```
