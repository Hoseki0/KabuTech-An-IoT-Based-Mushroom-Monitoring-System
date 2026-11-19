# KabuTech User's Manual
**IoT Mushroom Incubator Monitoring, Control & ML Harvest Forecasting System**

---

## 1. System Overview
**KabuTech** is a Progressive Web App (PWA) and IoT dashboard designed to monitor, automate, and forecast oyster mushroom cultivation inside compact incubator boxes (**1–5 fruiting bags max per box**). It integrates:
- **ESP32 Sensors & Relays:** Real-time temperature (°C), humidity (% RH), automated misting pump, and ventilation fan control.
- **ESP32-CAM:** Live camera stream and snapshot gallery for visual monitoring.
- **Machine Learning Harvest Forecasting:** Thermal-Time Growth Regression trained on your harvest logs and live climate telemetry.
- **PWA & Push Notifications:** Installable on Android, iOS, and desktop browsers with offline caching and real-time push alerts.

---

## 2. Getting Started

### 2.1 Logging In
1. Open the KabuTech URL in your browser (e.g., `http://<server-ip>:8000` or your local XAMPP URL).
2. Sign in with your email and password (or click **Register** to create a new user account, or **Forgot Password** to reset).
3. Administrators have access to **Admin Panel** from the top-right **Profile** dropdown.

### 2.2 Installing as a Mobile / Desktop App (PWA)
- **Android (Chrome):** Tap the browser menu (`⋮`) → **Add to Home screen** / **Install app**.
- **Desktop (Chrome / Edge):** Click the **Install** icon in the address bar.
- **Offline Mode:** If Wi-Fi drops, KabuTech displays an **Offline Mode** banner and continues showing your last cached farm readings.

### 2.3 Enabling Push Alerts
1. Click the **Bell** icon in the top-right header.
2. Under **Push Alerts**, click **Enable** and allow browser notifications.
3. Click **Test** to verify notifications are working on your device.

---

## 3. Navigation

### Desktop (Left Sidebar)
- **Dashboard:** Live climate summary, Crop Growth & Harvest Forecast, quick batch registration, Misting/Fan controls, and live charts.
- **Sensor Data:** Tabular history of recorded temperature, humidity, and relay activity.
- **Export & Analytics:** Harvest logs, yield analytics, biological efficiency (`% BE`), and CSV exports.
- **Incubator & Growth:** Incubator box configuration, stage tracking, and full batch management.
- **Hardware Health:** ESP32 connectivity, sensor diagnostics, and device uptime.
- **Live Camera:** Live ESP32-CAM video feed and captured snapshots.

### Mobile (Bottom Navigation Bar)
- **Home** → Dashboard
- **Data** → Sensor Data
- **Incubator** → Incubator & Growth
- **Health** → Hardware Health
- **More** → Opens the full menu for **Export & Analytics** and **Live Camera**

---

## 4. Dashboard & Daily Workflow

### 4.1 Registering a New Mushroom Batch (1-Go Registration)
You can register a batch directly from the **Crop Growth & Harvest Forecast** card on the **Dashboard** (or in **Incubator & Growth**):
1. Click **Register Batch** in the **Crop Growth & Harvest Forecast** card header.
2. Fill in:
   - **Batch Name** (e.g., `Batch-2026-01`)
   - **Mushroom Type** (`White Oyster Mushroom` or `Brown Oyster Mushroom`)
   - **Incubator Box** (e.g., `Incubator Box A`)
   - **Bags Count (Max 5):** Enter `1` to `5` (each incubator box holds a maximum of 5 fruiting bags).
3. Click **Register**. The batch is saved to the database and linked to the selected incubator box.

### 4.2 Tracking Growth & ML Harvest Forecast
1. Select your active **Mushroom Type** (`White Oyster Mushroom` or `Brown Oyster Mushroom`).
2. Start **Incubation** when bags are newly placed in the box, and switch to **Fruiting** once mycelial colonization is complete and pinning begins.
3. The ML model analyzes your species profile, flush number, and live temperature/humidity telemetry to display:
   - **Growth Progress (%)**
   - **Estimated Days Remaining & Predicted Harvest Date**
   - **Forecast Reliability / Confidence (%)**
   - **Climate Efficiency Note** (e.g., whether temperature or humidity is accelerating or delaying growth)

### 4.3 Misting & Fan Controls
- **AUTO Mode (Recommended):** Automatically triggers the misting pump and ventilation fan based on the active species/box target temperature and humidity ranges.
- **MANUAL Mode:** Lets you toggle the misting pump or fan directly ON/OFF from the dashboard.

---

## 5. Mushroom Species & Climate Targets

| Species | Incubation Temp | Incubation Humidity | Incubation Duration | Pinning Window | Fruiting Temp | Fruiting Humidity | Harvest Window |
| :--- | :---: | :---: | :---: | :---: | :---: | :---: | :---: |
| **White Oyster** (`oyster_white`) | 24.0 – 27.0 °C | 60 – 75 % RH | 10 – 21 days | 3 – 10 days | 15.0 – 24.0 °C | 80 – 95 % RH | 5 – 14 days |
| **Brown Oyster** (`oyster_brown`) | 25.0 – 30.0 °C | 65 – 80 % RH | 14 – 28 days | 4 – 10 days | 22.0 – 30.0 °C | 85 – 95 % RH | 5 – 12 days |

---

## 6. Incubator Boxes & Stage Management
In **Incubator & Growth**:
- **Incubator Boxes:** Configure **Incubator Box A**, **Box B**, **Box C**, or add custom boxes with their own species, stage (`incubation` / `fruiting`), temperature/humidity bounds, and misting mode.
- **Batch Table:** View all active batches, assigned box, bag count (`1–5 bags`), start date, and current stage (`inoculation`, `incubation`, `fruiting`, `spent`).

---

## 7. Logging Harvests & Retraining the ML Model
Recording every harvest improves the accuracy of your ML forecast:
1. Open the **Log Harvest** modal (from **Export & Analytics** / Harvest section).
2. Enter:
   - **Batch Name / ID** & **Mushroom Type**
   - **Flush Number** (`1`, `2`, `3`, etc.)
   - **Yield Weight (Grams):** Total fresh mushroom weight harvested (e.g., `1850` g for 4–5 bags on 1st flush).
   - **Substrate Weight (g):** Total substrate weight in the box (e.g., `4000` g for 4 bags or `5000` g for 5 bags at ~1 kg/bag).
   - **Batch Avg Temp (°C) & Humidity (%):** Enter manually or click **Use Live** to pull current sensor readings.
   - **Harvest Date** & **Notes**
3. Click **Save Harvest Entry**. KabuTech automatically computes **Biological Efficiency (`% BE`)** and updates the ML training dataset.

---

## 8. Live Camera Monitoring
1. Open **Live Camera** from the sidebar (or **More → Live Camera** on mobile).
2. View the real-time ESP32-CAM stream to inspect bag colonization, pinhead formation, and cap maturity without opening the incubator box.
3. Capture or review saved camera snapshots.

---

## 9. Troubleshooting
- **Dashboard shows old UI after an update:** Press `Ctrl + F5` (desktop) or close and reopen the PWA to load the latest Service Worker cache.
- **Cannot register more than 5 bags:** Each incubator box is physically limited to **1–5 fruiting bags**; split larger batches across multiple boxes (e.g., `Box A` and `Box B`).
- **Sensor readings not updating:** Check **Hardware Health**, confirm the ESP32 is connected to Wi-Fi, and verify the Laravel backend is running (`start-server.bat`).
- **Camera stream not loading:** Confirm the ESP32-CAM is powered on, on the same local network, and its IP address matches your configuration.
