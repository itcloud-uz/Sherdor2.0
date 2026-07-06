import os
from dotenv import load_dotenv

# Laravel'ning asosiy .env faylini o'qish (telegram_bot papkasidan bir pog'ona yuqorida)
dotenv_path = os.path.join(os.path.dirname(os.path.dirname(__file__)), '.env')
load_dotenv(dotenv_path)

BOT_TOKEN = os.getenv("FINANCE_BOT_TOKEN")
APP_URL = os.getenv("APP_URL", "http://localhost")
API_BASE_URL = os.getenv("API_BASE_URL", f"{APP_URL}/api")
API_SECRET_KEY = os.getenv("BOT_SECRET_KEY", "default_secret_key_for_dev")
