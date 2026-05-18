/*
 * =============================================================================
 * KABUTECH — CAMERA STREAM ONLY  (ESP32-CAM MODULE — NOT THE DEVKIT)
 * =============================================================================
 * Hardware: AI Thinker ESP32-CAM (or clone with same OV2640 pinout). Has camera ribbon + OV2640.
 *
 * Arduino IDE (required):
 *   Tools → Board  → "AI Thinker ESP32-CAM"  (NOT "ESP32 Dev Module")
 *   Tools → PSRAM → Enabled (typical for this board; if PSRAM errors, try OPI / or Disabled per docs)
 *   Tools → Upload Speed → 115200  (large sketch; avoids "chip stopped responding")
 */

#include "esp_camera.h"
#include <WiFi.h>
#include <WiFiManager.h>

// Most ESP32-CAM-style modules use GPIO4 for the onboard flash LED.
// If your board doesn't have a flash LED, this is harmless.
#ifndef LED_GPIO_NUM
#define LED_GPIO_NUM 4
#endif

void startCameraServer();
void setupLedFlash();

// Same Wi-Fi UX as thesis-ui/esp32/esp32.ino — saved credentials, auto-reconnect.
static WiFiManager wifiPortal;

#ifndef WIFI_CLEAR_PIN
#define WIFI_CLEAR_PIN 0  // AI Thinker: hold BOOT ~3s during prompt to erase Wi-Fi and reopen portal
#endif

static void maybeClearSavedWiFi() {
  pinMode(WIFI_CLEAR_PIN, INPUT_PULLUP);
  Serial.println();
  Serial.println("--- Wi-Fi (ESP32-CAM) ---");
  Serial.println("To FORCE setup AP \"KABUTECH-ESP32CAM-SETUP\": HOLD BOOT ~3s.");
  Serial.println("Otherwise: use saved Wi-Fi (same network as your main ESP32 is fine).");
  Serial.println();

  uint32_t heldMs = 0;
  const uint32_t tEnd = millis() + 6000;
  while (millis() < tEnd) {
    if (digitalRead(WIFI_CLEAR_PIN) == LOW) {
      heldMs += 50;
      if (heldMs >= 2800) {
        Serial.println("Clearing saved Wi-Fi...");
        wifiPortal.resetSettings();
        delay(400);
        ESP.restart();
      }
    } else {
      heldMs = 0;
    }
    delay(50);
  }
}

static void connectWiFiWithPortal() {
  maybeClearSavedWiFi();

  WiFi.mode(WIFI_STA);
  WiFi.setSleep(false);
#if defined(ESP32)
  WiFi.setTxPower(WIFI_POWER_19_5dBm);
#endif
  wifiPortal.setConfigPortalTimeout(600);
  wifiPortal.setConnectTimeout(30);
#if defined(ESP32)
  wifiPortal.setWiFiAPChannel(1);
#endif
  wifiPortal.setAPCallback([](WiFiManager* /*wm*/) {
    Serial.println();
    Serial.println("========== ESP32-CAM Wi-Fi setup ==========");
    Serial.println("  Join Wi-Fi:  KABUTECH-ESP32CAM-SETUP");
    Serial.println("  Browser:     http://192.168.4.1");
    Serial.println("  Use the SAME SSID/password as your home Wi-Fi / main ESP32.");
    Serial.println("===========================================");
    Serial.println();
  });

  if (!wifiPortal.autoConnect("KABUTECH-ESP32CAM-SETUP")) {
    Serial.println("Wi-Fi setup failed or timed out. Restarting...");
    delay(2000);
    ESP.restart();
  }

  Serial.println("Wi-Fi connected");
  Serial.print("IP address: ");
  Serial.println(WiFi.localIP());
}

static void reconnectWiFi() {
  Serial.println("Wi-Fi disconnected. Reconnecting...");
  WiFi.mode(WIFI_STA);
  WiFi.setSleep(false);
  WiFi.reconnect();

  const uint32_t start = millis();
  while (WiFi.status() != WL_CONNECTED && (millis() - start) < 25000) {
    delay(300);
  }

  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("Reconnect failed — restarting...");
    delay(1500);
    ESP.restart();
  }
  Serial.print("Wi-Fi back. IP: ");
  Serial.println(WiFi.localIP());
}

void setup() {
  Serial.begin(115200);
  Serial.setDebugOutput(true);
  Serial.println();
  Serial.println("======== KABUTECH CAMERA (OV3660/OV2640 auto-pin) ========");
  // #region agent log (serial build marker)
  Serial.printf("[build] kabutech_cam_autopin afe3c2 %s %s\n", __DATE__, __TIME__);
  // #endregion
  Serial.println("If you expected SHT31/sensors, WRONG sketch — use esp32/esp32.ino on DevKit.");
  Serial.println("============================================================\n");

  Serial.printf("[diag] PSRAM detected: %s\n", psramFound() ? "yes" : "no — using DRAM for frames");

  struct CamPins {
    const char* name;
    int pwdn;
    int reset;
    int xclk;
    int sda;
    int scl;
    int d0;
    int d1;
    int d2;
    int d3;
    int d4;
    int d5;
    int d6;
    int d7;
    int vsync;
    int href;
    int pclk;
  };

  // Pinmaps taken from common Espressif Arduino examples.
  // We try multiple because clones/OV3660 modules often differ from AI Thinker.
  static const CamPins PINMAPS[] = {
      // AI Thinker ESP32-CAM (common; often OV2640, but some ship with OV3660)
      {"AI_THINKER", 32, -1, 0, 26, 27, 5, 18, 19, 21, 36, 39, 34, 35, 25, 23, 22},
      // AI Thinker variant without PWDN wired (some clones)
      {"AI_THINKER_NO_PWDN", -1, -1, 0, 26, 27, 5, 18, 19, 21, 36, 39, 34, 35, 25, 23, 22},
      // M5Stack PSRAM (often OV3660/OV2640 depending module)
      {"M5STACK_PSRAM", -1, 15, 27, 25, 23, 17, 35, 34, 5, 39, 18, 36, 19, 22, 26, 21},
      // M5Stack WITHOUT PSRAM
      {"M5STACK_NO_PSRAM", -1, 15, 27, 25, 23, 17, 35, 34, 5, 39, 18, 36, 19, 22, 26, 21},
  };

  auto makeConfig = [](const CamPins& p) {
    camera_config_t c;
    c.ledc_channel = LEDC_CHANNEL_0;
    c.ledc_timer = LEDC_TIMER_0;
    c.pin_d0 = p.d0;
    c.pin_d1 = p.d1;
    c.pin_d2 = p.d2;
    c.pin_d3 = p.d3;
    c.pin_d4 = p.d4;
    c.pin_d5 = p.d5;
    c.pin_d6 = p.d6;
    c.pin_d7 = p.d7;
    c.pin_xclk = p.xclk;
    c.pin_pclk = p.pclk;
    c.pin_vsync = p.vsync;
    c.pin_href = p.href;
    c.pin_sccb_sda = p.sda;
    c.pin_sccb_scl = p.scl;
    c.pin_pwdn = p.pwdn;
    c.pin_reset = p.reset;
    c.xclk_freq_hz = 20000000;
    c.pixel_format = PIXFORMAT_JPEG;
    c.frame_size = FRAMESIZE_UXGA;
    c.grab_mode = CAMERA_GRAB_WHEN_EMPTY;
    c.fb_location = psramFound() ? CAMERA_FB_IN_PSRAM : CAMERA_FB_IN_DRAM;
    c.jpeg_quality = 12;
    c.fb_count = psramFound() ? 2 : 1;
    if (!psramFound()) {
      c.frame_size = FRAMESIZE_SVGA;
    }
    return c;
  };

  esp_err_t err = ESP_FAIL;
  camera_config_t config{};
  const char* selected = nullptr;

  for (size_t i = 0; i < (sizeof(PINMAPS) / sizeof(PINMAPS[0])); i++) {
    const CamPins& p = PINMAPS[i];
    Serial.printf("\n[camera] Trying pinmap: %s\n", p.name);

    // Ensure clean deinit between tries
    esp_camera_deinit();

    if (p.pwdn >= 0) {
      pinMode(p.pwdn, OUTPUT);
      digitalWrite(p.pwdn, LOW);
      delay(120);
    }

    config = makeConfig(p);
    err = esp_camera_init(&config);
    if (err == ESP_OK) {
      selected = p.name;
      break;
    }
    Serial.printf("[camera] init failed with 0x%x on %s\n", (unsigned)err, p.name);
    delay(150);
  }

  if (err != ESP_OK) {
    Serial.printf("\n*** CAMERA INIT FAILED on all pinmaps. Last error: 0x%x ***\n", (unsigned)err);
    Serial.println("This usually means one of:");
    Serial.println("  • Power issue (use stable 5V supply, ideally 2A, into 5V pin)");
    Serial.println("  • Camera ribbon not seated / wrong orientation");
    Serial.println("  • Board pinmap not in this list (tell me your board name)");
    Serial.println("Halting.");
    while (true) delay(1000);
  }

  Serial.printf("\n[camera] OK using pinmap: %s\n", selected ? selected : "(unknown)");

  sensor_t* s = esp_camera_sensor_get(); 
  if (s->id.PID == OV3660_PID) {
    s->set_vflip(s, 1);
    s->set_brightness(s, 1);
    s->set_saturation(s, -2);
  }
  if (config.pixel_format == PIXFORMAT_JPEG) {
    s->set_framesize(s, FRAMESIZE_QVGA);   // 320x240 = higher FPS for smooth stream
    s->set_quality(s, 14);                  // Slightly lower quality = smaller frames = smoother
  }

#if defined(LED_GPIO_NUM)
  setupLedFlash();
#endif

  connectWiFiWithPortal();

  startCameraServer();
  Serial.print("Camera Ready! Use 'http://");
  Serial.print(WiFi.localIP());
  Serial.println("' to connect");
  Serial.print("MJPEG stream (Laravel .env): http://");
  Serial.print(WiFi.localIP());
  Serial.println(":81/stream");
}

void loop() {
  if (WiFi.status() != WL_CONNECTED) {
    reconnectWiFi();
  }
  delay(3000);
}
