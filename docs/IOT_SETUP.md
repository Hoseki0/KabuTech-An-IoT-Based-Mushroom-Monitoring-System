# IoT Mushroom Farm Monitoring System - Setup Guide

## Overview
This Laravel-based dashboard provides real-time monitoring and control for your IoT mushroom farm system. It displays temperature, humidity, and allows remote control of the misting system.

## Features
- ✅ Real-time sensor data display (Temperature & Humidity)
- ✅ Remote misting system control
- ✅ Connection status indicator
- ✅ Auto-refresh every 3 seconds
- ✅ Modern, responsive UI with white and green theme
- ✅ RESTful API for IoT device integration

## Installation Steps

### 1. Run Database Migration
```bash
php artisan migrate
```
This creates the `sensor_data` table to store sensor readings.

### 2. Start Laravel Development Server
```bash
php artisan serve
```
The dashboard will be available at `http://localhost:8000`

## API Endpoints

### Get Latest Sensor Data
```
GET /api/sensor-data/latest
```
Returns the most recent sensor readings.

**Response:**
```json
{
    "temperature": 25.5,
    "humidity": 65.2,
    "misting_system": false,
    "recorded_at": "2026-01-29T14:35:39Z"
}
```

### Send Sensor Data (IoT Device)
```
POST /api/sensor-data
Content-Type: application/json

{
    "temperature": 25.5,
    "humidity": 65.2,
    "misting_system": false
}
```

**Response:**
```json
{
    "success": true,
    "message": "Sensor data received",
    "data": {...}
}
```

### Control Misting System
```
POST /api/misting/control
Content-Type: application/json
X-CSRF-TOKEN: {token}

{
    "status": true
}
```

### Get Sensor History
```
GET /api/sensor-data/history?limit=50
```
Returns historical sensor data.

## IoT Device Integration (ESP32)

### ESP32 (Arduino IDE / PlatformIO)
See `esp32.ino` for the ESP32 sketch.

**Key Steps:**
1. Install required libraries:
   - `DHT sensor library` (Adafruit)
   - `Adafruit Unified Sensor`
   - `ArduinoJson`
2. Update WiFi credentials in the sketch
3. Set `BASE_URL` to your Laravel server address (your PC LAN IP is recommended)
4. Wire your DHT22 to `DHTPIN` (default: GPIO 4)
5. Wire your relay to `RELAY_PIN` (default: GPIO 5) if you want misting control
6. Upload the sketch to the ESP32

## Testing the System

### 1. Test API with cURL

**Send test sensor data:**
```bash
curl -X POST http://localhost:8000/api/sensor-data \
  -H "Content-Type: application/json" \
  -d '{"temperature": 25.5, "humidity": 65.2, "misting_system": false}'
```

**Get latest data:**
```bash
curl http://localhost:8000/api/sensor-data/latest
```

**Control misting system:**
```bash
# First get CSRF token
curl http://localhost:8000/api/csrf-token

# Then control misting (replace {token} with actual token)
curl -X POST http://localhost:8000/api/misting/control \
  -H "Content-Type: application/json" \
  -H "X-CSRF-TOKEN: {token}" \
  -d '{"status": true}'
```

### 2. Test Dashboard
1. Open `http://localhost:8000` in your browser
2. The dashboard will automatically fetch data every 3 seconds
3. Use the "Turn ON/OFF" button to control the misting system

## Configuration

### Update Server URL in IoT Code
Replace `http://your-domain.com` with your actual server address:
- Local development: `http://localhost:8000`
- Local network: `http://192.168.1.100:8000` (your computer's IP)
- Production: `https://yourdomain.com`

### Adjust Update Interval
In the dashboard JavaScript, change:
```javascript
updateInterval = setInterval(fetchSensorData, 3000); // 3 seconds
```

In IoT device code, adjust the delay/interval accordingly.

## Troubleshooting

### Dashboard shows "Disconnected"
- Check if Laravel server is running
- Verify API endpoint: `http://localhost:8000/api/sensor-data/latest`
- Check browser console for errors

### IoT device can't connect
- Verify WiFi credentials
- Check server URL is correct
- Ensure Laravel server is accessible from device's network
- Check firewall settings

### CSRF Token Errors
- The dashboard automatically fetches CSRF token
- For IoT devices, ensure proper headers are sent
- Check Laravel logs: `storage/logs/laravel.log`

## File Structure

```
thesis-ui/
├── app/
│   ├── Http/Controllers/Api/
│   │   └── IoTController.php      # API endpoints
│   └── Models/
│       └── SensorData.php        # Sensor data model
├── database/migrations/
│   └── ..._create_sensor_data_table.php
├── routes/
│   └── web.php                    # API routes
├── resources/views/
│   └── dashboard.php              # Main dashboard UI
└── esp32.ino         # ESP32 example
```

## Next Steps

1. **Deploy to Production**: Update server URL in IoT code
2. **Add Authentication**: Protect API endpoints if needed
3. **Add Alerts**: Implement notifications for threshold breaches
4. **Data Visualization**: Add charts for historical data
5. **Multiple Sensors**: Extend to support multiple farm locations

## Support

For issues or questions, check:
- Laravel documentation: https://laravel.com/docs
- Arduino documentation: https://www.arduino.cc/reference
- ESP32 guides: https://randomnerdtutorials.com
