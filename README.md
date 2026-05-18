# 🍄 KABUTECH: IoT-Based Mushroom Monitoring System

**KABUTECH** is a comprehensive, IoT-powered web application designed to monitor and automate the environmental conditions of a mushroom farm. Built with Laravel and integrated with ESP32 hardware, the system ensures optimal growth conditions by tracking real-time sensor data and automating irrigation systems.

---

## ✨ Key Features

* **📊 Real-Time Dashboard:** A responsive, modern UI to monitor live sensor data (Temperature, Humidity, Soil Moisture).
* **💧 Automated Misting System:** Server-driven misting profiles that communicate with an ESP32 to trigger a 12V DC diaphragm water pump via relays.
* **📷 Visual Monitoring:** Integration with ESP32-CAM for live visual feedback of the mushroom farm.
* **🔐 Secure Authentication:** Role-based access control (Admin & Standard Members) with a secure, Gmail-SMTP powered password recovery system.
* **🔔 System Notifications & Feedback:** Asynchronous AJAX-based feedback mechanism and real-time system alerts.
* **📱 Mobile Responsive:** Optimized dashboard interface designed for seamless use on both desktop and mobile devices.

---

## 🛠️ Technology Stack

### Software (Web Application)
* **Framework:** Laravel (PHP)
* **Frontend:** Blade Templates, HTML, CSS, JavaScript (AJAX)
* **Database:** MySQL (`kabutech_iot`)
* **Server Environment:** XAMPP (Apache/MySQL)

### Hardware (IoT Integrations)
* **Microcontrollers:** ESP32, ESP32-CAM
* **Sensors:** DHT (Temperature & Humidity), Soil Moisture Sensors
* **Actuators:** 12V DC Diaphragm Water Pump, Relay Modules

---

## 🚀 Getting Started (Local Development)

### Prerequisites
* PHP >= 8.1
* Composer
* XAMPP (or any local Apache/MySQL server)
* Node.js & NPM

### Installation Steps

1. **Clone the repository** (if pulling from GitHub):
   ```bash
   git clone <YOUR_GITHUB_REPO_URL>
   cd thesis-ui
   ```

2. **Install PHP Dependencies:**
   ```bash
   composer install
   ```

3. **Install Frontend Dependencies:**
   ```bash
   npm install
   npm run build
   ```

4. **Environment Setup:**
   * Copy the example environment file:
     ```bash
     cp .env.example .env
     ```
   * Generate the application key:
     ```bash
     php artisan key:generate
     ```
   * Update your `.env` file with your database and SMTP credentials:
     ```env
     DB_CONNECTION=mysql
     DB_HOST=127.0.0.1
     DB_PORT=3306
     DB_DATABASE=kabutech_iot
     DB_USERNAME=root
     DB_PASSWORD=

     MAIL_MAILER=smtp
     MAIL_HOST=smtp.gmail.com
     MAIL_PORT=465
     MAIL_USERNAME=your_email@gmail.com
     MAIL_PASSWORD=your_app_password
     MAIL_ENCRYPTION=tls
     ```

5. **Run Migrations & Seeders:**
   ```bash
   php artisan migrate --seed
   ```

6. **Start the Local Development Server:**
   ```bash
   php artisan serve
   ```
   *The application will be accessible at `http://localhost:8000`*

---

## 🤖 Hardware Setup Overview
* The ESP32 requires specific firmware to connect to the local network and communicate with the Laravel backend API.
* The 12V Water Pump is wired to a 5V relay module controlled by the ESP32 GPIO pins.
* Ensure the ESP32-CAM has a stable power supply to prevent SCCB initialization errors.

---

## 📜 License
This project was developed for academic/thesis purposes. All rights reserved.
