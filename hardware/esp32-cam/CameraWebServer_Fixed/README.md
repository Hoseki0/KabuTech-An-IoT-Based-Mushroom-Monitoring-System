# ESP32-CAM fix for AI Thinker (OV2640)

This folder is a **complete, ready-to-upload** Camera Web Server sketch with the camera fix applied.

## What’s fixed

- **board_config.h** – Selects **CAMERA_MODEL_AI_THINKER** (OV2640).
- **camera_pins.h** – Correct GPIO pins for AI Thinker ESP32-CAM.
- **CameraWebServer_Fixed.ino** – Drives GPIO 32 (PWDN) **before** camera init so the probe succeeds.
- **Wi-Fi** – Uses **WiFiManager** (same idea as `esp32/esp32.ino`): first-time setup via captive portal, then **saved credentials** so the camera reconnects to your Wi-Fi on every boot — use the **same SSID and password** as your home router / main KABUTECH ESP32 so everything is on one LAN.
- **app_httpd.cpp** + **camera_index.h** – Full web server and UI so the sketch compiles and runs.

## How to use

1. **Arduino IDE libraries**
   - **Sketch → Include Library → Manage Libraries** → search **WiFiManager** (by tzapu) → Install.  
   - Same ESP32 board support as usual.

2. **Open the sketch**
   - **File → Open** → `thesis-ui\esp32-cam\CameraWebServer_Fixed\CameraWebServer_Fixed.ino`.

3. **Board and port**
   - **Tools → Board** → **ESP32 Arduino** → **AI Thinker ESP32-CAM** (or your exact board).
   - **Tools → Port** → COM port of the ESP32-CAM (USB).

4. **First upload — Wi-Fi setup**
   - After upload, open **Serial Monitor** (115200).
   - The board creates a setup network **`KABUTECH-ESP32CAM-SETUP`**. On your phone/laptop, connect to it and open **http://192.168.4.1** (captive portal).
   - Enter your **normal Wi-Fi name and password** — the same network your PC and main ESP32 use (2.4 GHz if your router splits bands).
   - Credentials are stored on the camera module; next boots connect **automatically** without the portal (unless you erase them — see below).

5. **Change Wi-Fi or start over**
   - Hold the **BOOT** button (~3 seconds) when the serial prompt asks, to clear saved Wi-Fi and show the setup AP again.

6. **Laravel dashboard**
   - Serial Monitor prints the camera IP. Set in `.env`, e.g.  
     `ESP32_CAM_STREAM_URL=http://192.168.x.x:81/stream`

---

## If you still get "probe device timeout"

- Try **GPIO 32 HIGH** instead of LOW in the .ino (some boards invert PWDN):
  ```cpp
  digitalWrite(32, HIGH);  // instead of LOW
  ```
- Use a **5V 2A** USB power supply for the ESP32-CAM.
- In **Tools → Board**, ensure **AI Thinker ESP32-CAM** (or your module) is selected.

## Same Wi-Fi as the main ESP32?

Each ESP32 stores Wi-Fi only on **its own** chip. There is no automatic “copy” from the sensor board to the camera. Configure the portal once with the **same SSID/password** as your router (the same values you used for **KABUTECH-ESP32-SETUP** on the main ESP32). After that, both devices reconnect to that network on their own.
