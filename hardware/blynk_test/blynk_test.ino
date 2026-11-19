/*
 * =============================================================================
 * KABUTECH — BLYNK TEST SKETCH (TEMPERATURE & HUMIDITY ONLY)
 * =============================================================================
 * Hardware : ESP32 DevKit + SHT31 Sensor (I2C)
 * Sensor   : SHT31  ->  SDA = GPIO 21, SCL = GPIO 22, VCC = 3.3V, GND = GND
 *
 * Virtual Pin Mapping in Blynk App / Console:
 *   - V0 : Temperature (°C)  [Datastream: Double, Min: 0, Max: 60]
 *   - V1 : Humidity (%)      [Datastream: Double, Min: 0, Max: 100]
 *
 * Libraries required (Install via Arduino Library Manager):
 *   1. "Blynk" (by Volodymyr Shymanskyy / Blynk)
 *   2. "Adafruit SHT31 Library" (by Adafruit)
 *   3. "Adafruit BusIO"
 * =============================================================================
 */

#define BLYNK_TEMPLATE_ID "TMPL6guFhQzYu"
#define BLYNK_TEMPLATE_NAME "Temp and Humid"
#define BLYNK_AUTH_TOKEN "3aWYrksujcWVp87PYmdayxY8iB70PGFx"

#define BLYNK_PRINT Serial

#include <WiFi.h>
#include <WiFiClient.h>
#include <BlynkSimpleEsp32.h>
#include <Wire.h>
#include <Adafruit_SHT31.h>

// 2. FILL IN YOUR WI-FI CREDENTIALS
char ssid[] = "PLDTHOMEFIBR6cdf8";         // Replace with your Wi-Fi name
char pass[] = "Ideahub2026!fast";     // Replace with your Wi-Fi password

// Sensor & Timer objects
Adafruit_SHT31 sht31 = Adafruit_SHT31();
BlynkTimer timer;
bool sensorFound = false;

// Function to read sensor and push to Blynk
void sendSensorData() {
  float temperature = 0.0;
  float humidity = 0.0;

  if (sensorFound) {
    temperature = sht31.readTemperature();
    humidity = sht31.readHumidity();

    if (isnan(temperature) || isnan(humidity)) {
      Serial.println("[WARN] Failed to read from SHT31 sensor!");
      return;
    }
  } else {
    // If sensor hardware is not detected, send simulated data for testing
    temperature = 26.5;
    humidity = 85.2;
    Serial.println("[DEMO] SHT31 not connected. Sending simulated test data.");
  }

  // Print to Serial Monitor
  Serial.printf("[Sensor Data] Temp: %.2f °C | Humidity: %.2f %%\n", temperature, humidity);

  // Send to Blynk Virtual Pins
  Blynk.virtualWrite(V0, temperature);
  Blynk.virtualWrite(V1, humidity);
}

void setup() {
  Serial.begin(115200);
  delay(1000);

  Serial.println("\n========================================");
  Serial.println("   KABUTECH - BLYNK SENSOR TEST NODE   ");
  Serial.println("========================================");

  // Initialize I2C for SHT31 (Default ESP32: SDA=21, SCL=22)
  Wire.begin(21, 22);
  delay(100);

  Serial.println("Checking SHT31 Sensor...");
  if (sht31.begin(0x44)) {
    Serial.println("✓ SHT31 sensor found at I2C address 0x44");
    sensorFound = true;
  } else if (sht31.begin(0x45)) {
    Serial.println("✓ SHT31 sensor found at I2C address 0x45");
    sensorFound = true;
  } else {
    Serial.println("✗ SHT31 sensor NOT detected!");
    Serial.println("  Please check wiring: SDA->GPIO21, SCL->GPIO22, VCC->3.3V, GND->GND");
    Serial.println("  (The sketch will send simulated readings to Blynk for testing)");
    sensorFound = false;
  }

  // Connect to Wi-Fi and Blynk Cloud
  Serial.println("\nConnecting to Blynk Cloud...");
  Blynk.begin(BLYNK_AUTH_TOKEN, ssid, pass);

  // Set timer to read sensor and update Blynk every 2 seconds (2000 ms)
  timer.setInterval(2000L, sendSensorData);

  Serial.println("✓ Setup complete. Publishing data to Blynk every 2 seconds.\n");
}

void loop() {
  Blynk.run();
  timer.run();
}
