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
Retrieve aggregated statistics.
Query parameters: `?period=daily|weekly|monthly`

**Response Example:**
```json
{
  "status": "success",
  "data": {
    "period": "daily",
    "count": 144,
    "avg_current": 1.25,
    "max_current": 2.1,
    "min_current": 0.5,
    "avg_voltage": 229.5,
    "avg_temperature": 34.0,
    "avg_power": 286.875,
    "max_power": 483.0
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
Generate a new bill prediction using Gemini AI.

**Response Example:**
```json
{
  "status": "success",
  "data": {
    "device_id": "energy",
    "month": "2026-05",
    "predicted_kwh": 150.5,
    "predicted_cost": 45.15,
    "currency": "USD",
    "generated_at": "2026-05-12 20:30:00",
    "id": 11,
    "summary": "Based on the last 30 readings, your usage is stable..."
  },
  "message": ""
}
```

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
