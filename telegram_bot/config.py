import os
from dotenv import load_dotenv

# Laravel'ning asosiy .env faylini o'qish (telegram_bot papkasidan bir pog'ona yuqorida)
dotenv_path = os.path.join(os.path.dirname(os.path.dirname(__file__)), '.env')
load_dotenv(dotenv_path)

BOT_TOKEN = os.getenv("BACKUP_BOT_TOKEN", "8516861678:AAEPUEQF-7KxFPhwl45gsSrVvl73-wv_Jm4")
API_BASE_URL = os.getenv("API_BASE_URL", "https://sher-dor.uz/api")
API_SECRET_KEY = os.getenv("BOT_SECRET_KEY", "default_secret_key_for_dev") # Must match BOT_SECRET_KEY in Laravel .env
