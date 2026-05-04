# ⚙️ Sher-Dor 2.0 Installation & Setup Guide

This guide will walk you through setting up the Sher-Dor 2.0 ERP system.

## 1. Prerequisites
- **PHP 8.2** or higher
- **Composer**
- **Node.js 20+** and npm
- **Python 3.10+** (For the Face-ID Microservice)

---

## 2. Laravel Backend Setup

1. **Install Dependencies**
   ```bash
   composer install
   npm install
   ```

2. **Environment Configuration**
   Copy `.env.example` to `.env` and generate the application key.
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

   **Configure AI (Optional but recommended):**
   Add your Google Gemini API key to the `.env` file:
   ```env
   GEMINI_API_KEY=your_google_ai_studio_key_here
   ```

3. **Database Setup & Seeding**
   The system comes with a highly realistic `DemoDataSeeder` so you can instantly see the dashboard populated with Buildings, Clients, and Active Contracts.
   ```bash
   touch database/database.sqlite
   php artisan migrate:fresh --seed
   ```
   *(This will create an `admin@itcloud.uz` user with the password `password`.)*

4. **Storage Link & Build**
   ```bash
   php artisan storage:link
   npm run build
   ```

5. **Run the Development Server**
   ```bash
   php artisan serve
   ```
   *Access the app at http://localhost:8000*

---

## 3. Background Jobs (The Penalty Engine)
To ensure the **Rolling Penalty Engine** works correctly and automatically charges overdue accounts every day, you must set up Laravel's task scheduler.

On a production Linux server, add the following Cron entry:
```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

For local testing, you can manually trigger the penalties:
```bash
php artisan erp:calculate-penalties
```

---

## 4. Python Face-ID Microservice (HR Hub)
The HR module includes an interface that expects a local Python Flask server running OpenCV on `port 5000`.

1. Create a Python virtual environment and install dependencies:
   ```bash
   python -m venv venv
   source venv/bin/activate
   pip install flask opencv-python face_recognition
   ```

2. The microservice should have two main routes:
   - `GET /video_feed`: Returns a Multipart MJPEG stream from the webcam.
   - `POST /register`: Accepts `{"user_id": 1}` and initiates face capture.

3. Run the microservice:
   ```bash
   python face_server.py
   ```
   *The ERP interface will automatically detect the camera feed at `http://localhost:5000/video_feed`.*
