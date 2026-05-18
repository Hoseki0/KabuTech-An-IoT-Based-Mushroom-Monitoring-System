/*
 * =============================================================================
 * KABUTECH — SENSOR / MISTING FIRMWARE  (NO CAMERA — ESP32 DEVKIT ONLY)
 * =============================================================================
 * Hardware : Plain ESP32 Dev Module + SHT31 (I2C) + relay on GPIO 5.
 * Sensor   : SHT31  →  SDA=GPIO21, SCL=GPIO22, VCC=3.3V, GND=GND
 * Relay    : GPIO 5 → IN pin. HIGH = misting ON.
 *
 * Mushroom profiles (temp/hum targets) come from the server via
 *   GET /api/misting/status   →  JSON field "targets"
 * Supporting species: oyster_mushroom, straw_mushroom, milky_mushroom, wood_ear
 *
 * Libraries (install via Arduino Library Manager):
 *   Adafruit SHT31, Adafruit Unified Sensor, Wire,
 *   ArduinoJson (v6), WiFiManager (tzapu), ArduinoOTA (built-in)
 *
 * OTA password: kabutech
 *   Tools → Port → "kabutech-esp32 at <IP>"  (same LAN as the PC)
 * =============================================================================
 */

#ifndef ENABLE_OTA
#define ENABLE_OTA 1   // 1 = wireless uploads on; 0 = disable ArduinoOTA
#endif

#include <WiFi.h>
#if ENABLE_OTA
  #include <ArduinoOTA.h>
  #include "freertos/FreeRTOS.h"
  #include "freertos/task.h"
#endif
#include <HTTPClient.h>
#include <WiFiClientSecure.h>
#include <Wire.h>
#include <Adafruit_SHT31.h>
#include <ArduinoJson.h>
#include <WiFiManager.h>

// ---------------------------------------------------------------------------
// CONFIGURATION
// ---------------------------------------------------------------------------
static char BASE_URL[128] = "https://winteriest-nannie-nonpathologic.ngrok-free.dev";

const char* POST_SENSOR_URL  = "/api/sensor-data";
const char* LATEST_SENSOR_URL = "/api/sensor-data/latest";
const char* MISTING_STATUS_URL = "/api/misting/status";
const char* PING_URL          = "/api/ping";

// Relay pin (HIGH = relay energised = misting ON)
#define RELAY_PIN 5

// Send sensor reading every 5 seconds
const uint32_t UPDATE_INTERVAL_MS = 5000;

// BOOT button – hold ~3 s on power-up to erase saved Wi-Fi
#ifndef WIFI_CLEAR_PIN
  #define WIFI_CLEAR_PIN 0
#endif

// ---------------------------------------------------------------------------
// MUSHROOM PROFILE DEFAULTS (per-species fallbacks if server unreachable)
// These mirror MushroomSpeciesCatalog.php on the Laravel side.
// ---------------------------------------------------------------------------
struct MushroomProfile {
  const char* key;
  const char* label;
  // Fruiting targets
  float temp_min, temp_max;
  float hum_min,  hum_max;
  // Incubation targets
  float inc_temp_min, inc_temp_max;
  float inc_hum_min,  inc_hum_max;
  // Auto-misting timing (ms)
  uint32_t fruiting_burst_ms;
  uint32_t fruiting_cooldown_ms;
  uint32_t incubation_burst_ms;
  uint32_t incubation_cooldown_ms;
};

static const MushroomProfile PROFILES[] = {
  // key               label             f_t_min f_t_max f_h_min f_h_max  i_t_min i_t_max i_h_min i_h_max  f_burst   f_cool    i_burst   i_cool
  { "oyster_mushroom", "Oyster Mushroom", 15.0f,  24.0f,  80.0f,  95.0f,   24.0f,  27.0f,  60.0f,  75.0f,  12000UL, 45000UL,  10000UL, 45000UL },
  { "straw_mushroom",  "Straw Mushroom",  28.0f,  35.0f,  85.0f,  95.0f,   30.0f,  35.0f,  70.0f,  85.0f,  10000UL, 30000UL,   8000UL, 30000UL },
  { "milky_mushroom",  "Milky Mushroom",  22.0f,  30.0f,  80.0f,  92.0f,   25.0f,  32.0f,  65.0f,  80.0f,  12000UL, 40000UL,  10000UL, 40000UL },
  { "wood_ear",        "Wood Ear",        20.0f,  28.0f,  85.0f,  95.0f,   24.0f,  30.0f,  70.0f,  85.0f,  15000UL, 45000UL,  12000UL, 45000UL },
};
static const size_t PROFILE_COUNT = sizeof(PROFILES) / sizeof(PROFILES[0]);

// Active profile index (default: oyster)
static size_t activeProfileIdx = 0;

// Server-supplied targets override local profile values when valid
static float serverTempMin = 0.0f;
static float serverTempMax = 0.0f;
static float serverHumMin  = 0.0f;
static bool  serverTargetsValid = false;

// ---------------------------------------------------------------------------
// MISTING STATE
// ---------------------------------------------------------------------------
bool     mistingStatus   = false;
bool     autoIncubation  = false;  // false=fruiting, true=incubation

const char* mistingSource = "auto";
const char* mistingReason = "";

static uint32_t mistUntilMs      = 0;
static uint32_t lastMistEndMs    = 0;
static uint32_t mistStartMs      = 0;
static uint64_t mistingTotalMs   = 0;
static uint32_t mistingLastBurstMs = 0;

// ---------------------------------------------------------------------------
// SENSOR
// ---------------------------------------------------------------------------
Adafruit_SHT31 sht31 = Adafruit_SHT31();

// ---------------------------------------------------------------------------
// TIMING
// ---------------------------------------------------------------------------
uint32_t lastTick = 0;

// ---------------------------------------------------------------------------
// OTA BACKGROUND TASK
// ---------------------------------------------------------------------------
#if ENABLE_OTA
static bool otaDaemonStarted = false;
static void otaDaemonTask(void* /*param*/) {
  for (;;) {
    ArduinoOTA.handle();
    vTaskDelay(pdMS_TO_TICKS(25));
  }
}
#endif

// ---------------------------------------------------------------------------
// HELPERS — URL / HTTP
// ---------------------------------------------------------------------------
static void normalizeBaseUrl() {
  char* s = BASE_URL;
  size_t len = strlen(s);
  // Strip leading spaces
  size_t lead = 0;
  while (lead < len && s[lead] == ' ') lead++;
  if (lead > 0) { memmove(s, s + lead, len - lead + 1); len = strlen(s); }
  // Fix ttp:// typo
  if (strncmp(s, "ttp://", 6) == 0) {
    memmove(s + 1, s, len + 1);
    s[0] = 'h';
  }
}

static String buildUrl(const char* path) {
  String url(BASE_URL);
  if (url.length() && url.endsWith("/") && path[0] == '/') url.remove(url.length() - 1);
  url += path;
  return url;
}

static void addTunnelHeaders(HTTPClient& http, bool jsonBody) {
  if (jsonBody) http.addHeader("Content-Type", "application/json");
  if (strstr(BASE_URL, "ngrok") != nullptr) http.addHeader("ngrok-skip-browser-warning", "true");
}

static bool parseHttpOrigin(String& host, uint16_t& port, bool& https) {
  normalizeBaseUrl();
  String b = String(BASE_URL); b.trim();
  https = false;
  if      (b.startsWith("https://")) { https = true; }
  else if (!b.startsWith("http://"))  { return false; }
  String rest = b.substring(https ? 8 : 7);
  const int slash = rest.indexOf('/');
  if (slash >= 0) rest = rest.substring(0, slash);
  const int colon = rest.indexOf(':');
  if (colon >= 0) { host = rest.substring(0, colon); port = (uint16_t)rest.substring(colon + 1).toInt(); }
  else            { host = rest; port = https ? 443 : 80; }
  return host.length() > 0;
}

static void apiHttpBegin(HTTPClient& http, WiFiClient& wc, WiFiClientSecure& wcs, const char* uriPath) {
  String path = String(uriPath);
  if (!path.startsWith("/")) path = "/" + path;
  String host; uint16_t port = 80; bool tls = false;
  if (!parseHttpOrigin(host, port, tls)) { http.begin(buildUrl(uriPath)); return; }
  if (tls) { wcs.setInsecure(); wcs.setTimeout(30000); http.begin(wcs, host, port, path, true); return; }
  wc.setTimeout(30000);
  http.begin(wc, host, port, path, false);
}

// ---------------------------------------------------------------------------
// PROFILE LOOKUP
// ---------------------------------------------------------------------------
static size_t findProfileIdx(const char* key) {
  for (size_t i = 0; i < PROFILE_COUNT; i++) {
    if (strcmp(PROFILES[i].key, key) == 0) return i;
  }
  return 0; // default oyster
}

static const MushroomProfile& activeProfile() {
  return PROFILES[activeProfileIdx];
}

// ---------------------------------------------------------------------------
// MISTING LOGIC
// ---------------------------------------------------------------------------
static void applyMistingToRelay(bool on) {
  const bool was = mistingStatus;
  const uint32_t nowMs = millis();
  if (on && !mistingStatus) { mistStartMs = nowMs; }
  if (!on && mistingStatus) {
    const uint32_t burst = (mistStartMs > 0) ? (nowMs - mistStartMs) : 0;
    mistingLastBurstMs = burst;
    mistingTotalMs += burst;
    mistStartMs = 0;
  }
  mistingStatus = on;
  digitalWrite(RELAY_PIN, on ? HIGH : LOW);
  Serial.println(on ? "  [RELAY] Misting ON" : "  [RELAY] Misting OFF");
  if (was != on) {
    // ── RELAY SETTLE DELAY ────────────────────────────────────────────
    // Relay coil switching causes a voltage spike on the 5V rail.
    // This delay lets the rail recover before any WiFi/I2C activity.
    // Increase to 400–500ms if ESP32 still resets after relay toggles.
    // ─────────────────────────────────────────────────────────────────
    delay(300);
    if (!on) {
      // Extra delay when STOPPING misting — solenoid valve coils
      // produce a larger back-EMF spike than small relay coils alone.
      delay(300);
    }
  }
}

static void handleAutoMisting(float temperature, float humidity, uint32_t nowMs) {
  // Turn off burst when timer expires
  if (mistingStatus && mistUntilMs > 0 && nowMs >= mistUntilMs) {
    applyMistingToRelay(false);
    mistUntilMs = 0;
    lastMistEndMs = nowMs;
    return;
  }
  if (mistingStatus) return; // still bursting

  // Minimum gap between bursts
  if (lastMistEndMs > 0 && (nowMs - lastMistEndMs) < 8000) return;

  const MushroomProfile& p = activeProfile();

  float tempMax, tempMin, humMin;
  uint32_t burstMs, cooldownMs;

  if (autoIncubation) {
    tempMin    = serverTargetsValid ? serverTempMin  : p.inc_temp_min;
    tempMax    = serverTargetsValid ? serverTempMax  : p.inc_temp_max;
    humMin     = serverTargetsValid ? serverHumMin   : p.inc_hum_min;
    burstMs    = p.incubation_burst_ms;
    cooldownMs = p.incubation_cooldown_ms;
  } else {
    tempMin    = serverTargetsValid ? serverTempMin  : p.temp_min;
    tempMax    = serverTargetsValid ? serverTempMax  : p.temp_max;
    humMin     = serverTargetsValid ? serverHumMin   : p.hum_min;
    burstMs    = p.fruiting_burst_ms;
    cooldownMs = p.fruiting_cooldown_ms;
  }

  const bool tooHot = temperature > tempMax;
  const bool tooCold = temperature < tempMin;  // informational — misting won't warm
  const bool tooDry  = humidity < humMin;

  if (tooCold) {
    // We can't heat via misting — just log
    Serial.printf("  [AUTO] Temp %.1f°C below min %.1f°C — warm grow area!\n", temperature, tempMin);
  }

  if (tooHot || tooDry) {
    mistingSource = "auto";
    mistingReason = tooHot ? "too_hot" : "too_dry";
    // Extend burst for severe overtemp
    if (tooHot) burstMs = burstMs + 3000;
    applyMistingToRelay(true);
    mistUntilMs = nowMs + burstMs;
    Serial.printf("  [AUTO] Triggered: %s | burst=%lus\n", mistingReason, burstMs / 1000);
  }
}

// ---------------------------------------------------------------------------
// API — POST sensor data
// ---------------------------------------------------------------------------
static bool postSensorData(float temperature, float humidity, bool misting) {
  if (WiFi.status() != WL_CONNECTED) return false;

  StaticJsonDocument<256> doc;
  doc["temperature"]        = temperature;
  doc["humidity"]           = humidity;
  doc["misting_system"]     = misting;
  doc["wifi_rssi"]          = WiFi.RSSI();
  doc["misting_source"]     = mistingSource;
  doc["misting_reason"]     = mistingReason;
  doc["misting_total_ms"]   = (uint32_t)mistingTotalMs;
  doc["misting_last_burst_ms"] = mistingLastBurstMs;

  String payload;
  serializeJson(doc, payload);

  for (int attempt = 0; attempt < 4; attempt++) {
    if (WiFi.status() != WL_CONNECTED) return false;
    if (attempt > 0) { Serial.printf("  POST retry %d/4\n", attempt + 1); delay(350 * attempt); }

    HTTPClient http; WiFiClient wc; WiFiClientSecure wcs;
    apiHttpBegin(http, wc, wcs, POST_SENSOR_URL);
    addTunnelHeaders(http, true);
    http.setConnectTimeout(20000);
    http.setTimeout(20000);
    const int code = http.POST(payload);
    http.getString(); http.end();
    Serial.printf("  POST /api/sensor-data -> %d\n", code);
    if (code > 0) return (code == 200 || code == 201);
  }
  return false;
}

// ---------------------------------------------------------------------------
// API — GET misting status + mushroom targets
// ---------------------------------------------------------------------------
static bool pollMistingStatus(bool* outOn, bool* outIsAuto) {
  if (WiFi.status() != WL_CONNECTED) return false;

  int code = -1; String body;
  for (int attempt = 0; attempt < 3; attempt++) {
    if (attempt > 0) delay(250 * attempt);
    HTTPClient http; WiFiClient wc; WiFiClientSecure wcs;
    apiHttpBegin(http, wc, wcs, MISTING_STATUS_URL);
    addTunnelHeaders(http, false);
    http.setConnectTimeout(20000);
    http.setTimeout(20000);
    code = http.GET();
    body = http.getString();
    http.end();
    if (code == 200) break;
  }

  Serial.printf("  GET /api/misting/status -> %d\n", code);
  if (code != 200) return false;

  StaticJsonDocument<1024> doc;
  if (deserializeJson(doc, body)) return false;
  if (!doc.containsKey("desired_on")) return false;

  *outOn = doc["desired_on"].as<bool>();
  *outIsAuto = true;
  if (doc.containsKey("desired_mode")) {
    const char* mode = doc["desired_mode"].as<const char*>();
    *outIsAuto = (mode && strcmp(mode, "auto") == 0);
  }

  // Profile (incubation vs fruiting)
  if (doc.containsKey("desired_profile")) {
    const char* prof = doc["desired_profile"].as<const char*>();
    autoIncubation = (prof && strcmp(prof, "incubation") == 0);
  }

  // Mushroom species key
  if (doc.containsKey("mushroom_type")) {
    const char* key = doc["mushroom_type"].as<const char*>();
    if (key) {
      size_t idx = findProfileIdx(key);
      if (idx != activeProfileIdx) {
        activeProfileIdx = idx;
        Serial.printf("  [PROFILE] Switched to: %s\n", PROFILES[activeProfileIdx].label);
      }
    }
  }

  // Server-supplied temp/hum targets (override local profile)
  serverTargetsValid = false;
  if (doc.containsKey("targets") && doc["targets"].is<JsonObject>()) {
    JsonObject t = doc["targets"];
    float tmin = t["temp_min"] | -999.0f;
    float tmax = t["temp_max"] | -999.0f;
    float hmin = t["hum_min"]  | -999.0f;
    if (tmin > -50.0f && tmax < 60.0f && tmax > tmin &&
        hmin > 0.0f   && hmin < 100.0f) {
      serverTempMin = tmin;
      serverTempMax = tmax;
      serverHumMin  = hmin;
      serverTargetsValid = true;
    }
  }

  return true;
}

// ---------------------------------------------------------------------------
// Wi-Fi — portal (first boot) and reconnect
// ---------------------------------------------------------------------------
static WiFiManager wifiPortal;

static void maybeClearSavedWiFi() {
  pinMode(WIFI_CLEAR_PIN, INPUT_PULLUP);
  Serial.println("\n--- Wi-Fi setup ---");
  Serial.println("Hold BOOT button ~3 s to erase saved Wi-Fi and reopen portal.");
  Serial.println();
  uint32_t heldMs = 0;
  const uint32_t tEnd = millis() + 6000;
  while (millis() < tEnd) {
    if (digitalRead(WIFI_CLEAR_PIN) == LOW) {
      heldMs += 50;
      if (heldMs >= 2800) {
        Serial.println("Clearing saved Wi-Fi...");
        wifiPortal.resetSettings();
        delay(400); ESP.restart();
      }
    } else { heldMs = 0; }
    delay(50);
  }
}

static void connectWiFiWithPortal() {
  maybeClearSavedWiFi();
  normalizeBaseUrl();
  WiFi.mode(WIFI_STA); WiFi.setSleep(false);
  WiFi.setTxPower(WIFI_POWER_19_5dBm);
  wifiPortal.setConfigPortalTimeout(600);
  wifiPortal.setConnectTimeout(30);
  wifiPortal.setWiFiAPChannel(1);
  wifiPortal.setAPCallback([](WiFiManager*) {
    Serial.println("\n====== WiFiManager CONFIG PORTAL ======");
    Serial.println("  Join Wi-Fi:  KABUTECH-ESP32-SETUP");
    Serial.println("  Browser:     http://192.168.4.1");
    Serial.println("=======================================\n");
  });

  WiFiManagerParameter serverParam(
      "server",
      "Laravel BASE_URL  (LAN: http://IP:8000  OR  ngrok https://xxx.ngrok-free.app)",
      BASE_URL, sizeof(BASE_URL));
  wifiPortal.addParameter(&serverParam);

  if (!wifiPortal.autoConnect("KABUTECH-ESP32-SETUP")) {
    Serial.println("WiFiManager failed — restarting...");
    delay(2000); ESP.restart();
  }

  const char* newBase = serverParam.getValue();
  if (newBase && newBase[0] != '\0') strlcpy(BASE_URL, newBase, sizeof(BASE_URL));
  normalizeBaseUrl();

  Serial.print("✓ Wi-Fi connected. IP: "); Serial.println(WiFi.localIP());
  Serial.print("  RSSI: "); Serial.print(WiFi.RSSI()); Serial.println(" dBm");
  Serial.print("  Server: "); Serial.println(BASE_URL);
}

static void reconnectWiFi() {
  Serial.println("Wi-Fi lost. Reconnecting...");
  WiFi.mode(WIFI_STA); WiFi.setSleep(false); WiFi.reconnect();
  const uint32_t start = millis();
  while (WiFi.status() != WL_CONNECTED && (millis() - start) < 25000) delay(300);
  if (WiFi.status() != WL_CONNECTED) { Serial.println("Reconnect failed — restarting..."); delay(1500); ESP.restart(); }
  Serial.print("✓ Wi-Fi back. IP: "); Serial.println(WiFi.localIP());
}

// ---------------------------------------------------------------------------
// OTA
// ---------------------------------------------------------------------------
static void setupArduinoOta() {
#if ENABLE_OTA
  ArduinoOTA.setHostname("kabutech-esp32");
  ArduinoOTA.setPassword("kabutech");
  ArduinoOTA.onStart([]()   { Serial.println("[OTA] Starting..."); })
             .onEnd([]()    { Serial.println("\n[OTA] Done."); })
             .onProgress([](unsigned int p, unsigned int t) { Serial.printf("[OTA] %u%%\r", t ? (p*100/t) : 0); })
             .onError([](ota_error_t e) { Serial.printf("[OTA] Error[%u]\n", e); });
  ArduinoOTA.begin();

  otaDaemonStarted = (xTaskCreate(otaDaemonTask, "arduino_ota", 8192, nullptr, 2, nullptr) == pdPASS);
  Serial.print("[OTA] Ready. Tools → Port → kabutech-esp32 at ");
  Serial.println(WiFi.localIP());
#else
  Serial.println("[OTA] Disabled.");
#endif
}

// ---------------------------------------------------------------------------
// REACHABILITY TEST
// ---------------------------------------------------------------------------
static void testLaravelReachable() {
  Serial.println("API: Testing /api/ping ...");
  bool ok = false;
  for (int i = 0; i < 3; i++) {
    if (i > 0) delay(400);
    HTTPClient http; WiFiClient wc; WiFiClientSecure wcs;
    apiHttpBegin(http, wc, wcs, PING_URL);
    addTunnelHeaders(http, false);
    http.setConnectTimeout(20000); http.setTimeout(20000);
    const int code = http.GET(); http.end();
    Serial.printf("  GET /api/ping -> %d\n", code);
    if (code == 200) { ok = true; break; }
  }
  Serial.println(ok ? "  ✓ Laravel reachable." : "  ✗ Laravel unreachable — check BASE_URL / firewall.");
}

// ===========================================================================
// SETUP
// ===========================================================================
void setup() {
  Serial.begin(115200);
  delay(1000);

  Serial.println("\n========================================");
  Serial.println(" KABUTECH — SENSOR NODE (esp32.ino)");
  Serial.println(" Board : ESP32 DevKit");
  Serial.println(" Sensor: SHT31  (SDA=21, SCL=22)");
  Serial.println(" Relay : GPIO 5  (HIGH=misting ON)");
  Serial.println("========================================\n");

  // --- SHT31 ---
  Wire.begin();
  delay(100);
  Serial.println("Initializing SHT31...");
  if (!sht31.begin(0x44)) {
    Serial.println("  0x44 not found, trying 0x45...");
    if (!sht31.begin(0x45)) {
      Serial.println("ERROR: SHT31 not found!\n"
                     "  Check wiring: VCC->3.3V, GND->GND, SDA->GPIO21, SCL->GPIO22");
      while (1) delay(1000);
    }
    Serial.println("  ✓ SHT31 at 0x45");
  } else {
    Serial.println("  ✓ SHT31 at 0x44");
  }

  // --- Relay ---
  pinMode(RELAY_PIN, OUTPUT);
  digitalWrite(RELAY_PIN, LOW);
  Serial.println("  ✓ Relay (GPIO 5) initialised — OFF\n");

  // --- Wi-Fi + OTA ---
  connectWiFiWithPortal();
  setupArduinoOta();
  testLaravelReachable();

  Serial.printf("\nActive profile: %s (%s)\n", activeProfile().label,
                autoIncubation ? "incubation" : "fruiting");
  Serial.println("========================================\n");
}

// ===========================================================================
// LOOP
// ===========================================================================
void loop() {
#if ENABLE_OTA
  if (!otaDaemonStarted) ArduinoOTA.handle();
#endif

  if (WiFi.status() != WL_CONNECTED) reconnectWiFi();

  const uint32_t now = millis();
  if (now - lastTick < UPDATE_INTERVAL_MS) return;
  lastTick = now;

  // --- Read SHT31 ---
  // Small settle delay before I2C read — if relay just fired in the
  // previous cycle its noise can corrupt I2C and give NaN readings.
  delay(150);
  float temperature = sht31.readTemperature();
  float humidity    = sht31.readHumidity();
  if (isnan(temperature) || isnan(humidity)) {
    Serial.println("[SENSOR] Read failed — check SHT31 wiring!");
    return;
  }

  Serial.println("----------------------------------------");
  Serial.printf("[SENSOR] Temp: %.2f°C  |  Humidity: %.2f%%\n", temperature, humidity);
  Serial.printf("[PROFILE] %s  |  Phase: %s\n",
                activeProfile().label, autoIncubation ? "Incubation" : "Fruiting");
  if (serverTargetsValid) {
    Serial.printf("[TARGETS] Temp %.1f–%.1f°C  |  Hum ≥%.1f%%  (from server)\n",
                  serverTempMin, serverTempMax, serverHumMin);
  } else {
    const MushroomProfile& p = activeProfile();
    float tmin = autoIncubation ? p.inc_temp_min : p.temp_min;
    float tmax = autoIncubation ? p.inc_temp_max : p.temp_max;
    float hmin = autoIncubation ? p.inc_hum_min  : p.hum_min;
    Serial.printf("[TARGETS] Temp %.1f–%.1f°C  |  Hum ≥%.1f%%  (local fallback)\n",
                  tmin, tmax, hmin);
  }

  // --- Poll server for misting command + species targets ---
  bool serverOn = false, serverAuto = true;
  const bool hasServer = pollMistingStatus(&serverOn, &serverAuto);

  // --- POST sensor reading ---
  bool ok = postSensorData(temperature, humidity, mistingStatus);
  Serial.println(ok ? "  [API] Data sent OK" : "  [API] Send failed");

  // --- Apply misting ---
  if (hasServer && !serverAuto) {
    // MANUAL override from dashboard
    mistingSource = "manual";
    mistingReason = "";
    mistUntilMs   = 0;
    if (serverOn != mistingStatus) applyMistingToRelay(serverOn);
    Serial.printf("  [MANUAL] Relay -> %s\n", serverOn ? "ON" : "OFF");
  } else {
    // AUTO — decide based on current sensor + species profile
    handleAutoMisting(temperature, humidity, now);
  }

  Serial.printf("[STATUS] Misting: %s  |  Source: %s\n",
                mistingStatus ? "ON" : "OFF", mistingSource);
  Serial.println("----------------------------------------\n");
}
