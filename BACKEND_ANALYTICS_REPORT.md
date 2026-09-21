# Backend Analytics API Requirement Report

**Author:** Mobile App Development Team  
**Date:** September 2026  
**Target:** Backend Development Team  
**Endpoint in Scope:** `GET /sensors/{device_id}/stats?period={daily|weekly|monthly}` (or `GET /sensors/{device_id}/analytics`)

---

## 1. Executive Summary & Required Layout

The mobile app's **Analytics Screen** has been updated to feature **4 separate graph cards**:

1. **Card 1: Energy Consumption Graph** (Unit: `kWh`) → Footer: **Average kWh**
2. **Card 2: Voltage Graph** (Unit: `V`) → Footer: **Average Voltage**
3. **Card 3: Current Graph** (Unit: `A`) → Footer: **Average Current**
4. **Card 4: Temperature Graph** (Unit: `°C`) → Footer: **Average Temperature**

### Dynamic Intervals:
- **Daily View**:
  - Displays all 7 days of the week: `Mon`, `Tue`, `Wed`, `Thu`, `Fri`, `Sat`, `Sun`.
- **Weekly View**:
  - Displays `Week 1`, `Week 2`, `Week 3`, `Week 4` of the active month (e.g. "Weeks of September 2026"), evenly spanning the card width.
- **Monthly View**:
  - Displays all 12 months of the year (`Jan`, `Feb`, `Mar`, `Apr`, `May`, `Jun`, `Jul`, `Aug`, `Sep`, `Oct`, `Nov`, `Dec`).

### Core Backend Requirements (User Corrections):
1. **Aggregate on Every Save (Correction 2)**:
   - On every sensor reading saved to the database, the backend should immediately update/maintain the rollup records for **daily**, **weekly**, and **monthly** periods.
   - The backend must return the data pre-aggregated and structured exactly as specified in the JSON contract so that **no further frontend changes are required**.
2. **Data Availability & Zero Handling (Correction 1)**:
   - **Ideal Case**: Under normal operating conditions, readings are actively stored by the backend, so active intervals will have real recorded values and will **not be empty**.
   - **Zero Handling**: Return `0.00` (which renders as an empty bar space) **only** if the backend was down, the hardware device was offline, or no reading was saved during that specific interval.

In **each of the 4 cards**, the card footer prominently displays the **Average** across the recorded intervals.

---

## 2. Current Backend Flaws

Currently, when calling `GET /sensors/energy/stats?period=daily|weekly|monthly`, the backend returns:
```json
{
  "avg_current": 0.0577,
  "avg_power": 2.8914,
  "avg_temperature": 28.9488,
  "avg_voltage": 9.6620,
  "count": 41127,
  "max_current": "1715.2200",
  "max_power": "1715.2200",
  "min_current": "0.0000",
  "period": "daily"
}
```

### Issues:
1. **The SQL query is missing the date boundary (`WHERE recorded_at >= ...`)**: `count: 41,127` and all values are 100% identical whether asking for `daily`, `weekly`, or `monthly`.
2. **Missing Time-Series Array**: The backend only sends 1 overall scalar object, rather than a time-series breakdown of days/weeks/months needed to render the 4 graphs.

---

## 3. Required JSON Response Contract

To feed all 4 graph cards seamlessly, the endpoint should return a `series` array containing the metrics for each interval, along with the calculated averages:

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
      {
        "label": "Mon",
        "date": "2026-09-14",
        "kwh": 0.00,
        "voltage": 0.00,
        "current": 0.00,
        "temperature": 0.00
      },
      {
        "label": "Tue",
        "date": "2026-09-15",
        "kwh": 0.00,
        "voltage": 0.00,
        "current": 0.00,
        "temperature": 0.00
      },
      {
        "label": "Wed",
        "date": "2026-09-16",
        "kwh": 0.45,
        "voltage": 229.1,
        "current": 1.12,
        "temperature": 28.9
      },
      {
        "label": "Thu",
        "date": "2026-09-17",
        "kwh": 0.32,
        "voltage": 227.8,
        "current": 0.95,
        "temperature": 29.5
      },
      {
        "label": "Fri",
        "date": "2026-09-18",
        "kwh": 0.85,
        "voltage": 230.4,
        "current": 1.54,
        "temperature": 30.1
      },
      {
        "label": "Sat",
        "date": "2026-09-19",
        "kwh": 0.22,
        "voltage": 228.0,
        "current": 0.88,
        "temperature": 28.4
      },
      {
        "label": "Sun",
        "date": "2026-09-20",
        "kwh": 0.00,
        "voltage": 0.00,
        "current": 0.00,
        "temperature": 0.00
      }
    ]
  },
  "message": ""
}
```

---

## 4. SQL Implementation for Backend Developer

Assuming the database table `sensor_readings` has columns `device_id`, `power_watt`, `voltage`, `current`, `temperature`, `recorded_at`:

### A. Daily Query (Monday – Sunday):
```sql
SELECT 
  DATE_FORMAT(recorded_at, '%a') AS label,
  DATE(recorded_at) AS date,
  -- Calculate kWh based on reading sampling rate (e.g. 10s per reading):
  ROUND(SUM(power_watt * 10 / 3600) / 1000, 2) AS kwh,
  ROUND(AVG(voltage), 2) AS voltage,
  ROUND(AVG(current), 2) AS current,
  ROUND(AVG(temperature), 2) AS temperature
FROM sensor_readings
WHERE device_id = ? 
  AND YEARWEEK(recorded_at, 1) = YEARWEEK(CURDATE(), 1)
GROUP BY DATE(recorded_at), DATE_FORMAT(recorded_at, '%a')
ORDER BY date ASC;
```
*(In PHP controller: merge the query rows into the 7 days of the week `['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']`, setting values to `0.00` for days with no readings).*

### B. Weekly Query (Weeks of Month):
```sql
SELECT 
  CONCAT('Week ', FLOOR((DAY(recorded_at) - 1) / 7) + 1) AS label,
  FLOOR((DAY(recorded_at) - 1) / 7) + 1 AS week_number,
  ROUND(SUM(power_watt * 10 / 3600) / 1000, 2) AS kwh,
  ROUND(AVG(voltage), 2) AS voltage,
  ROUND(AVG(current), 2) AS current,
  ROUND(AVG(temperature), 2) AS temperature
FROM sensor_readings
WHERE device_id = ? 
  AND YEAR(recorded_at) = YEAR(CURDATE())
  AND MONTH(recorded_at) = MONTH(CURDATE())
GROUP BY week_number, label
ORDER BY week_number ASC;
```

### C. Monthly Query (Months of Current Year):
```sql
SELECT 
  DATE_FORMAT(recorded_at, '%b') AS label,
  MONTH(recorded_at) AS month_number,
  ROUND(SUM(power_watt * 10 / 3600) / 1000, 2) AS kwh,
  ROUND(AVG(voltage), 2) AS voltage,
  ROUND(AVG(current), 2) AS current,
  ROUND(AVG(temperature), 2) AS temperature
FROM sensor_readings
WHERE device_id = ? 
  AND YEAR(recorded_at) = YEAR(CURDATE())
GROUP BY month_number, label
ORDER BY month_number ASC;
```

---

## 5. Mobile App Status

The frontend is already built and ready:
- It supports `series` with `kwh`, `voltage`, `current`, `temperature`.
- It renders all 4 cards with their respective colors, units, and footer averages.
- When `0.00` is received for inactive days, it automatically renders an empty spot (no bar).
- Supports pull-to-refresh.
