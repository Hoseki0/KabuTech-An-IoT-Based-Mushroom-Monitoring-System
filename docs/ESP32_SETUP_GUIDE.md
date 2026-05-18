# ESP32 Temperature & Humidity Setup Guide (SHT31 Sensor)

## Step-by-Step Instructions to Get Sensor Readings

### 📦 **Step 1: Hardware Wiring**

Connect your **SHT31** sensor to ESP32 (I2C communication):

```
SHT31 Pin    →    ESP32 Pin
─────────────────────────────
VCC (Power)  →    3.3V
GND          →    GND
SDA          →    GPIO 21 (I2C Data)
SCL          →    GPIO 22 (I2C Clock)
```

**Important Notes:**
- SHT31 uses **I2C** communication (not single-wire like DHT22)
- Default I2C address: **0x44** (0x45 if ADDR pin is connected to VCC)
- No pull-up resistors needed (ESP32 has built-in pull-ups)
- SHT31 is more accurate and faster than DHT22

---

### 📚 **Step 2: Install Required Libraries**

Open **Arduino IDE** and install these libraries:

1. **Go to:** `Tools` → `Manage Libraries`
2. **Search and install:**
   - `Adafruit SHT31 Library` by Adafruit
   - `Adafruit Unified Sensor` (dependency - install if prompted)
   - `ArduinoJson` by Benoit Blanchon (version 6.x)

**Or install via Library Manager:**
- Search: `Adafruit SHT31` → Install
- Search: `ArduinoJson` → Install

**Note:** `Wire` library is built-in for ESP32, no need to install separately.

---

### ⚙️ **Step 3: Configure the Code**

Open `esp32.ino` and update these lines:

```cpp
// Line 19-20: Your WiFi credentials
const char* WIFI_SSID = "PLDTHOMEFIBRdrCq8_EXT";
const char* WIFI_PASSWORD = "PLDTWIFIpxQP4";

// Line 25: Your Laravel server address
// If Laravel runs on your PC, find your PC's IP address:
// Windows: Open CMD → type: ipconfig → look for "IPv4 Address"
// Example: http://192.168.1.100:8000
const char* BASE_URL = "http://192.168.1.100:8000";
```

**To find your PC's IP address:**
- **Windows:** Open Command Prompt → type `ipconfig` → look for "IPv4 Address"
- **Mac/Linux:** Open Terminal → type `ifconfig` or `ip addr`

---

### 🔌 **Step 4: Upload Code to ESP32**

1. **Select Board:** `Tools` → `Board` → `ESP32 Arduino` → Select your ESP32 model
2. **Select Port:** `Tools` → `Port` → Select COM port (Windows) or `/dev/ttyUSB0` (Linux/Mac)
3. **Upload:** Click the Upload button (→) or press `Ctrl+U`

---

### ✅ **Step 5: Test Sensor Readings**

1. **Open Serial Monitor:**
   - Click `Tools` → `Serial Monitor`
   - Set baud rate to **115200**

2. **What you should see:**
   ```
   Connecting to WiFi...
   Connected. IP: 192.168.1.50
   POST /api/sensor-data -> 201
   Sensor data sent OK
   ```

3. **If you see errors:**
   - `Failed to read from DHT sensor` → Check wiring (especially DATA pin)
   - `WiFi disconnected` → Check WiFi credentials
   - `POST /api/sensor-data -> -1` → Check BASE_URL (server not reachable)

---

### 🖥️ **Step 6: Start Laravel Server**

**On your PC (where Laravel is installed):**

**Option A – Windows (easiest):** Double-click **`start-server.bat`** in the `thesis-ui` folder.  
It starts the API so the ESP32 can reach it from your network.

**Option B – Command line:**
```bash
cd c:\xampp\htdocs\thesis-ui
php artisan serve --host=0.0.0.0 --port=8000
```

**Important:** You must use `--host=0.0.0.0` (or run `start-server.bat`).  
If you use plain `php artisan serve`, it only listens on 127.0.0.1 and the ESP32 will get "Failed to send sensor data".

---

### 📊 **Step 7: View Data on Dashboard**

1. **Open browser:** `http://localhost:8000` (or your PC's IP: `http://192.168.1.100:8000`)
2. **Dashboard should show:**
   - Real-time temperature (updates every 3 seconds)
   - Real-time humidity
   - Connection status: "Connected" (green)

---

### 🔧 **Troubleshooting**

#### ❌ **Problem: "Couldn't find SHT31 sensor!" or "Failed to read from SHT31 sensor!"**
**Solutions:**
- Check I2C wiring (VCC, GND, SDA → GPIO 21, SCL → GPIO 22)
- Verify I2C address in code (default: 0x44, try 0x45 if not found)
- Check Serial Monitor for I2C scan results
- Ensure sensor is powered (3.3V, not 5V)
- Try adding external 4.7kΩ pull-up resistors on SDA/SCL (usually not needed)
- Verify sensor is SHT31 (not SHT30 or SHT35 - they use same library)

#### ❌ **Problem: "WiFi disconnected"**
**Solutions:**
- Double-check WiFi SSID and password (case-sensitive)
- Ensure ESP32 is within WiFi range
- Check if WiFi uses 2.4GHz (ESP32 doesn't support 5GHz)

#### ❌ **Problem: "POST /api/sensor-data -> -1" or "[API] ✗ Failed to send sensor data"**
**Solutions:**
- **Start the server so it listens on the network:** run **`start-server.bat`** (or `php artisan serve --host=0.0.0.0 --port=8000`). Do not use plain `php artisan serve` (that only listens on 127.0.0.1).
- Check BASE_URL in the .ino matches your PC's IP (e.g. `http://192.168.100.104:8000`).
- Ensure ESP32 and PC are on the same WiFi network.
- Check Windows Firewall: allow inbound TCP port 8000 (or temporarily disable firewall to test).

#### ❌ **Problem: Dashboard shows "--" (no data)**
**Solutions:**
- Check Serial Monitor on ESP32 - is it sending data?
- Open browser console (F12) - any errors?
- Verify API endpoint: `http://your-ip:8000/api/sensor-data/latest`
- Check Laravel logs: `storage/logs/laravel.log`

---

### 📝 **Code Explanation**

**How it reads sensors:**
```cpp
// Read from SHT31 sensor (I2C)
float temperature = sht31.readTemperature();  // Gets temperature in Celsius
float humidity = sht31.readHumidity();       // Gets humidity in %

// Validate readings
if (isnan(temperature) || isnan(humidity)) {
    Serial.println("Failed to read from SHT31 sensor!");
    return;  // Skip this cycle if sensor fails
}
```

**SHT31 Advantages:**
- ✅ More accurate (±0.3°C vs ±0.5°C for DHT22)
- ✅ Faster response time
- ✅ I2C communication (more reliable than single-wire)
- ✅ Lower power consumption

**How it sends to Laravel:**
```cpp
// Line 161: Sends data every 3 seconds
bool ok = postSensorData(temperature, humidity, mistingStatus);
```

**Update interval:**
- Default: **3 seconds** (line 40: `UPDATE_INTERVAL_MS = 3000`)
- Change to 5000 for 5 seconds, 10000 for 10 seconds, etc.

---

### 🎯 **Quick Test Without Hardware**

If you want to test the Laravel API first (without ESP32):

**Using cURL (Command Prompt):**
```bash
curl -X POST http://localhost:8000/api/sensor-data ^
  -H "Content-Type: application/json" ^
  -d "{\"temperature\": 25.5, \"humidity\": 65.2, \"misting_system\": false}"
```

**Using Postman or Browser:**
- URL: `http://localhost:8000/api/sensor-data/latest`
- Method: GET
- Should return JSON with latest sensor data

---

### ✅ **Success Checklist**

- [ ] DHT22 wired correctly to ESP32
- [ ] Libraries installed (DHT, ArduinoJson)
- [ ] WiFi credentials configured
- [ ] BASE_URL set to PC's IP address
- [ ] Code uploaded to ESP32
- [ ] Serial Monitor shows "Sensor data sent OK"
- [ ] Laravel server running with `--host=0.0.0.0`
- [ ] Dashboard shows temperature and humidity values

---

**Once all checkboxes are done, your ESP32 will automatically send temperature and humidity readings to your Laravel dashboard every 3 seconds!** 🎉
