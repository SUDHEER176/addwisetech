# 🛰️ AddWise: Role-Based GPS Device Management & Tracking System

A modern PHP web application for secure, role-based management of GPS devices, featuring QR code generation, real-time location tracking, and interactive route visualization with Leaflet.js.

---

## 📌 Objective

Build a web-based platform where Admins, Super Admins, and Users can manage GPS devices, generate QR codes, store device location updates, and visualize movement paths on an interactive map. The system ensures secure authentication, session management, and clean UX for all roles.

---

## 🧱 Key Features

### 🔐 1. Role-Based Login & Secure Access
- Login with Email and Password (Users), or Email/Username and Password (Admins/Super Admins)
- Role-based session management using PHP sessions
- Role-specific dashboards and access control:
  - **User:** `login.php` → `dashboard.php`
  - **Admin:** `admin-login.php` → `admin-dashboard.php`
  - **Super Admin:** `super-admin-login.php` → `super-admin-dashboard.php`
- Email verification for new users
- Google OAuth login for users (optional)

### 🧰 2. Admin Functionalities
- View, assign, and manage all devices (`admin-devices.php`)
- Generate unique 16-digit device codes
- Generate and download QR codes for devices
- Assign devices to users with location
- View all users and manage their status (`admin-users.php`)
- Live device tracking and map visualization (`admin-tracking.php`)

### 🦸‍♂️ 3. Super Admin Functionalities
- Manage Admin accounts
- View all devices and system analytics
- all device routes (`super-admin-dashboard.php`)
 * admin→ Admin123
  

### 👤 4. User Functionalities
- Register, verify email, and login
- View only devices assigned to them (`dashboard.php`)
- See own device locations and movement paths on a map
- Scan QR codes to claim/assign devices

### 🗺️ 5. Location & Route Tracking (Leaflet.js)
- Devices store latitude & longitude in the MySQL database
- Leaflet.js map displays:
  - Current location (markers)
  - Movement history (polylines)
- Each location update is stored (not overwritten) for route reconstruction
- Real-time tracking and route visualization for both users and admins

### 🧾 6. Custom API: `api-device-location.php`
- **GET**: Fetch current or full route for a device
- **POST**: Update device location (device_code, latitude, longitude)
- Validates input and updates device location history
- Used by dashboard and tracking pages for live updates

---

## 📦 Database Overview

| Table              | Fields                                                      |
|--------------------|------------------------------------------------------------|
| users              | id, email, password, role, is_active, is_verified, ...      |
| admins             | id, username, email, password, role, is_active, ...         |
| devices            | id, device_code, status, user_id, latitude, longitude, ...  |
| device_locations   | id, device_code, latitude, longitude, location_updated_at    |

Relational design ensures secure ownership, route tracking, and access control.

---

## 🧑‍💻 Technology Stack

| Layer                | Tools Used                                  |
|----------------------|---------------------------------------------|
| Frontend             | HTML, CSS, JavaScript, Leaflet.js           |
| Backend              | PHP 7.4+                                    |
| Authentication       | PHP Sessions, Email Verification, Google OAuth|
| Database             | MySQL                                       |
| QR Code              | JavaScript QR libraries (frontend)           |
| Email                | PHPMailer                                   |
| API                  | Custom PHP (api-device-location.php)         |

---

## 🚀 Installation

1. **Clone the repository:**
   ```bash
   git clone https://github.com/SUDHEER176/addwisetech.git
   cd addwise
   ```
2. **Install PHP dependencies:**
   ```bash
   composer install
   ```
3. **Environment setup:**
   - Create a `.env` file in the root directory (for Google OAuth):
     ```
     GOOGLE_CLIENT_ID=your_client_id_here
     GOOGLE_CLIENT_SECRET=your_client_secret_here
     GOOGLE_REDIRECT_URI=http://localhost/addwise/google-callback.php
     ```
   - Update database credentials in `config.php` or `db.php`
4. **Database setup:**
   - Create a new MySQL database (e.g., `addwise`)
   - Import the provided SQL schema (`database.sql` or `setup_tables.sql`)
5. **Web server config:**
   - Point your web server (XAMPP, WAMP, etc.) to the project directory
   - Ensure PHP has write permissions for session handling

---

## 🗺️ Usage

### 1. **User Registration & Login**
- Visit `signup.php` to register
- Verify your email via the link sent
- Login via `login.php` (email, password)
- Google login via `google-login.php` (if enabled)

### 2. **Device Management**
- Admins: Manage devices in `admin-devices.php`, assign to users, generate QR codes
- Users: Scan/view assigned devices and their locations in `dashboard.php`

### 3. **Location Tracking**
- Devices send location updates via `api-device-location.php` (POST)
- All location history is visualized on the dashboard map (Leaflet.js)
- Real-time tracking for both users and admins

### 4. **Role-Based Dashboards**
- Admin: `admin-dashboard.php` (manage devices, users, tracking)
- Super Admin: `super-admin-dashboard.php` (system analytics, admin management)
- User: `dashboard.php` (view only own devices and routes)

---

## 🧭 API Reference

### `api-device-location.php`
- **GET**: `?device_code=XXXX` (current location), `?device_code=XXXX&route=true` (full route)
- **POST**: `{ device_code, latitude, longitude }` (update location)
- Returns JSON with status and data

---

## 🎯 Why This Project Stands Out

- Built entirely with PHP and MySQL for easy deployment on common web hosts
- Combines device management, QR generation, mapping, and security in one full-stack app
- Clean UX with role-based navigation and dashboards
- Scalable for real GPS tracking, IoT, or delivery use cases
- Real-world implementation of session handling, API security, and location visualization

---

Happy device tracking with **AddWise**!
