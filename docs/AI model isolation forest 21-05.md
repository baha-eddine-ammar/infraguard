# AI Model Isolation Forest 21-05

## A. What Was Created

This Laravel preparation adds the storage and display surface for an external AI predictive maintenance workflow. The Laravel application does not run the Python model. It receives an already calculated prediction from n8n, stores the result in MySQL, and displays the latest result on the dashboard.

- A migration creates the `ai_predictions` table with temperature, rack power usage, risk level, health score, failure probability, anomaly score, explanation fields, recommendation fields, and the original JSON payload.
- A model `AiPrediction` represents stored AI prediction rows.
- An API controller `AiPredictionController` receives prediction results from n8n and saves them.
- An API route `POST /api/ai-predictions` exposes the Laravel receiver endpoint.
- An API route `GET /api/ai-input/latest` exposes the latest Laravel sensor values needed by n8n before it calls the local Python model.
- A dashboard partial displays the latest AI prediction as a small predictive maintenance card.

## B. Files Created

- `database/migrations/2026_05_21_120000_create_ai_predictions_table.php` - creates the `ai_predictions` table.
- `app/Models/AiPrediction.php` - Eloquent model for AI prediction records.
- `app/Http/Controllers/Api/AiPredictionController.php` - API receiver for n8n prediction results.
- `app/Http/Controllers/Api/AiInputController.php` - API endpoint that returns the latest temperature and rack power input values for n8n.
- `resources/views/dashboard/partials/ai-prediction-card.blade.php` - dashboard card for the latest AI prediction.
- `docs/AI model isolation forest 21-05.md` - documentation for the Laravel-side AI preparation.

## C. Files Edited

- `routes/api.php` - added the `POST /api/ai-predictions` and `GET /api/ai-input/latest` routes.
- `app/Http/Controllers/DashboardController.php` - loads the latest AI prediction and passes it to the dashboard view.
- `resources/views/dashboard.blade.php` - includes the AI prediction dashboard partial.

## D. What Was Not Done

- No Python AI model was created.
- No Isolation Forest algorithm was implemented.
- Laravel does not call Python directly.
- `.env` was not modified.
- No branch was pushed to GitHub.
- No work was done on `main`.
- No unrelated features were changed.

## E. Final Architecture

SHT20 temperature and PZEM rack power readings are stored by Laravel through the existing telemetry ingestion flow. n8n requests the latest AI input values from Laravel through `/api/ai-input/latest`, calls the local Python FastAPI Isolation Forest service, receives the prediction, and posts the result back to Laravel through `/api/ai-predictions`. Laravel saves the result in the `ai_predictions` table and the dashboard displays the latest stored prediction.

```text
SHT20 temperature + PZEM power_w
-> Laravel stores sensor readings
-> n8n calls Laravel /api/ai-input/latest to get latest values
-> n8n calls local Python FastAPI Isolation Forest model
-> Python returns prediction
-> n8n posts prediction to Laravel /api/ai-predictions
-> Laravel saves result in ai_predictions
-> dashboard displays latest AI result
```

## F. Data Flow

1. The SHT20 RS485 sensor provides server-room temperature.
2. The PZEM-004T sensor provides rack power usage in watts.
3. Laravel stores those raw readings in the existing telemetry tables.
4. n8n calls `/api/ai-input/latest` to read the latest `temperature` and `power_w` values from Laravel.
5. n8n calls the external local FastAPI model.
6. The Python model returns a prediction result.
7. n8n sends that prediction result to Laravel.
8. Laravel validates and stores the prediction in `ai_predictions`.
9. The dashboard displays the latest prediction.

## G. API Contract

AI input endpoint used by n8n before calling Python:

```http
GET /api/ai-input/latest
X-Sensor-Token: existing sensor API token
Accept: application/json
```

Successful response:

```json
{
  "success": true,
  "data": {
    "temperature": 34.5,
    "power_w": 890,
    "temperature_recorded_at": "2026-05-22T10:15:00+00:00",
    "power_recorded_at": "2026-05-22T10:15:01+00:00"
  }
}
```

If one of the required sensor values does not exist yet, Laravel returns `404` with the missing fields:

```json
{
  "success": false,
  "message": "AI input data is not ready yet.",
  "missing": {
    "temperature": false,
    "power_w": true
  }
}
```

AI prediction receiver endpoint used by n8n after Python returns a prediction:

```http
POST /api/ai-predictions
X-Sensor-Token: existing sensor API token
Content-Type: application/json
```

Request body example:

```json
{
  "temperature": 27.9,
  "power_w": 850,
  "risk_level": "warning",
  "rack_health_score": 42,
  "failure_probability": 58,
  "anomaly_score": 0.66,
  "main_cause": "temperature_rising_with_high_power_usage",
  "explanation": "Temperature and rack power usage are increasing together, which may indicate cooling stress or abnormal rack load.",
  "recommendation": "Check cooling, airflow, and server rack electrical load.",
  "raw_prediction": {
    "model": "Isolation Forest",
    "version": "v1"
  }
}
```

Successful response:

```json
{
  "success": true,
  "message": "AI prediction saved successfully",
  "data": {
    "temperature": 27.9,
    "power_w": 850,
    "risk_level": "warning",
    "rack_health_score": 42,
    "failure_probability": 58,
    "anomaly_score": 0.66,
    "main_cause": "temperature_rising_with_high_power_usage",
    "explanation": "Temperature and rack power usage are increasing together, which may indicate cooling stress or abnormal rack load.",
    "recommendation": "Check cooling, airflow, and server rack electrical load.",
    "raw_prediction": {
      "model": "Isolation Forest",
      "version": "v1"
    }
  }
}
```

## H. What Is Left To Do

- Create the Python FastAPI project.
- Implement the Isolation Forest model.
- Train and save the model.
- Expose the Python `/predict` endpoint.
- Configure the n8n workflow.
- Make n8n send prediction results to Laravel.
- Test the full flow locally.
- Deploy only the Laravel changes to production after testing.

## I. How To Test The Laravel Side

1. Run `php artisan migrate`.
2. Send a GET request to `/api/ai-input/latest` with the existing `X-Sensor-Token` header and confirm Laravel returns the latest `temperature` and `power_w`.
3. Send a POST request with the sample JSON body to `/api/ai-predictions`.
4. Include the existing `X-Sensor-Token` header used by sensor ingestion.
5. Check that a row appears in the `ai_predictions` table.
6. Refresh the dashboard and confirm the latest AI prediction card is visible.
