# 🌆 Sher-Dor 2.0: Vision-Punk ERP & CRM System

Welcome to **Sher-Dor 2.0**, a state-of-the-art Enterprise Resource Planning and Customer Relationship Management system built exclusively for the modern construction and real estate sector. 

Ditching the boring corporate interfaces, Sher-Dor 2.0 introduces a **"Vision-Punk"** glassmorphic aesthetic (Neon Cyans, deep blacks, pulsing alerts) that makes managing multi-million dollar construction projects, HR logistics, and client ledgers feel like commanding a futuristic spaceship.

---

## 🚀 Key Features

### 🏢 1. Real Estate & Facility Hub
- **Interactive Floor Maps**: Manage Buildings, Floors, and Units.
- **Smart Statuses**: Visual cues for Available (Neon Cyan), Reserved (Neon Yellow), and Sold (Neon Magenta) properties.

### 💼 2. Smart CRM & Sales Pipeline
- **Lead Kanban Board**: Drag and drop leads from New to Qualified to Contract.
- **Client Personal Ledger (Akt Sverka)**: A highly detailed modal that tracks payments, expected vs. actual deposits, and Remaining Debt.

### 🏦 3. The "Rolling Penalty" Engine (Finance Core)
- **Automated Calculations**: A custom artisan cron job (`erp:calculate-penalties`) automatically calculates daily penalties on overdue balances based on customizable rates.
- **Multi-Format Export**: Generate pixel-perfect Client Ledgers in PDF, DOCX (Word), and XLSX (Excel) instantly without complex server extensions.
- **Maker-Checker Security**: All cash flow goes through a two-step verification process to prevent fraud.

### 👥 4. Biometric HR & Heavy Machinery Logistics
- **Face-ID Integration**: Built-in support for Python OpenCV microservices. The system recognizes workers via webcams and automatically logs attendance.
- **Machinery Tracking**: Track Excavators and Cranes by the hour, automatically calculating costs and directly charging them to the Target Building's budget.

### 🤝 5. Multi-Sig Partner Hub
- **Consensus Protocol**: High-value expenses cannot be approved by one person. The system requires multiple Founders/Partners to "cast their vote" before money is released.

### 🤖 6. AI Command Center
- **Gemini Integration**: A built-in AI agent to help you draft contracts, analyze expenses, or search for data in natural language.

---

## 🛠 Tech Stack
- **Backend**: Laravel 11.x, PHP 8.2+, SQLite/PostgreSQL
- **Frontend**: Vue 3 (Composition API), Inertia.js, Tailwind CSS (Custom Vision-Punk Theme)
- **AI/Microservices**: Python (OpenCV/Flask for Face-ID), Google Gemini API
- **Exports**: Barryvdh/DomPDF, Custom Header Workarounds for Office Docs.

---

## 📖 Next Steps
Please read the [INSTALLATION_GUIDE.md](INSTALLATION_GUIDE.md) to set up the system on your local machine or production server.
