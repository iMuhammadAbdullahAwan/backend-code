# Energy Monitoring System (Backend)

A robust backend system built with CodeIgniter 4 to monitor energy consumption, predict bills using Gemini AI, and provide energy-saving tips.

## 🚀 Features
- **Real-time Monitoring:** Collects and stores sensor data (current, voltage, temperature).
- **AI-Powered Insights:** Uses Google Gemini AI to predict monthly electricity bills and generate personalized energy-saving tips.
- **Firebase Integration:** Synchronizes data seamlessly with Firebase for real-time updates.
- **RESTful API:** Provides a clean and documented API for frontend consumption.
- **Automated Sync:** Support for cron-job based synchronization of sensor data.

## 🛠️ Tech Stack
- **Framework:** CodeIgniter 4
- **Database:** MySQL
- **AI Integration:** Google Gemini API
- **External Services:** Firebase
- **Language:** PHP 8.2+

## 📂 Documentation
- [API Documentation (Endpoints & Formats)](./API_DOCS.md)
- [Cron Setup Guide](./CRON_SETUP.md)

## ⚙️ Setup Instructions
1.  **Clone the repository:**
    ```bash
    git clone https://github.com/iMuhammadAbdullahAwan/backend-code.git
    cd backend-code
    ```
2.  **Install dependencies:**
    ```bash
    composer install
    ```
3.  **Configure Environment:**
    - Copy `env` to `.env`.
    - Set your `app.baseURL`.
    - Configure `database.default` settings.
    - Add your `GEMINI_API_KEY` and `FIREBASE_URL`.
    - Set `SYNC_SECRET` for protected endpoints.
4.  **Run Migrations:**
    ```bash
    php spark migrate
    ```
5.  **Start Development Server:**
    ```bash
    php spark serve
    ```

## 🤝 Contributing
Contributions are welcome! Please feel free to submit a Pull Request.

## 📄 License
This project is licensed under the [LICENSE](./LICENSE) file.
