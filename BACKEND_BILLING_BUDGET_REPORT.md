# Backend Billing & Tariff API Requirement Report

**Author:** Mobile App Development Team  
**Date:** September 2026  
**Target:** Backend Development Team  
**Endpoints in Scope:**
- `POST /bills/{device_id}/predict` *(Bill prediction & cost calculation)*
- `GET /bills/{device_id}/config` *(Utility tariff & unit rate endpoint)*

---

## 1. Architecture & Division of Responsibility

Following updated product requirements, the division of responsibility between the Mobile Client and Backend is structured as follows:

| Parameter | Managed By | Description |
|---|---|---|
| **Monthly Budget (Rs.)** | **Frontend (User-Controlled)** | The user configures their personal monthly spending target directly in the mobile app (Settings). It is persisted locally in MMKV and dynamically dictates the budget progress bar, thresholds, and "Over Budget" status on the **Bill Prediction** screen. |
| **Rate per kWh (Rs.)** | **Backend (Server-Managed Tariff)** | Official unit cost tariff determined by the electricity utility / company. This is **read-only** on the mobile client and cannot be manipulated by end-users. |

---

## 2. API Contract Requirements

### Endpoint 1: Bill Prediction
* **Method:** `POST`
* **Path:** `/bills/{device_id}/predict`
* **Headers:** `Authorization: Bearer <token>`

#### Current Backend Response:
```json
{
  "status": "success",
  "data": {
    "predicted_cost": 4250.00,
    "predicted_kwh": 130.75,
    "summary": "Consumption is within safe limits."
  }
}
```

#### Required Updated Response:
The backend should return `rate_per_kwh` alongside the prediction so the mobile client can verify the tariff applied:
```json
{
  "status": "success",
  "data": {
    "predicted_cost": 4250.00,
    "predicted_kwh": 130.75,
    "rate_per_kwh": 32.50,
    "currency": "Rs.",
    "summary": "Consumption is within safe limits."
  }
}
```

> **Calculation Formula on Backend:**  
> `predicted_cost = predicted_kwh * rate_per_kwh` (or accumulated slab tariffs).

---

### Endpoint 2: Tariff / Billing Configuration
* **Method:** `GET`
* **Path:** `/bills/{device_id}/config`
* **Headers:** `Authorization: Bearer <token>`

#### Expected Response (`200 OK`):
```json
{
  "status": "success",
  "data": {
    "device_id": "esp32_device_001",
    "rate_per_kwh": 32.50,
    "currency": "PKR",
    "currency_symbol": "Rs.",
    "billing_cycle_start_day": 1,
    "tariff_type": "flat",
    "slabs": [
      { "min_kwh": 0, "max_kwh": 100, "rate": 22.00 },
      { "min_kwh": 101, "max_kwh": 300, "rate": 32.50 },
      { "min_kwh": 301, "max_kwh": null, "rate": 45.00 }
    ],
    "last_updated": "2026-09-15T10:00:00Z"
  }
}
```

---

## 3. How the Mobile App Operates

1. **Settings Screen (`settings.tsx`)**:
   - **Monthly Budget**: User can enter their target budget anytime (e.g. Rs. 5,000, 10,000). Stored immediately in MMKV via Zustand `useSettingsStore`.
   - **Rate per kWh**: Displayed as a locked / read-only badge indicating *"Official utility tariff (Server Managed)"*.

2. **Bill Prediction Screen (`bill.tsx`)**:
   - Fetches the predicted consumption (`predicted_kwh`) and cost (`predicted_bill`) from `POST /bills/{device_id}/predict`.
   - Compares `predictedBill` against the user's reactive `monthlyBudget`:
     - **Safe**: `predictedBill <= monthlyBudget * 0.8` (Blue gradient)
     - **Warning**: `predictedBill > monthlyBudget * 0.8 && predictedBill <= monthlyBudget` (Yellow gradient)
     - **Over Budget**: `predictedBill > monthlyBudget` (Red gradient)
   - Real-time reactivity: Changing the budget in Settings instantly updates the progress bar and status badge in the Bill Prediction screen without needing a separate network request.
