from aiogram.types import ReplyKeyboardMarkup, KeyboardButton, WebAppInfo
from config import APP_URL

def get_main_menu():
    miniapp_url = f"{APP_URL.rstrip('/')}/miniapp/transactions"
    return ReplyKeyboardMarkup(
        keyboard=[
            [
                KeyboardButton(text="📱 Mini App (Kirim / Chiqim)", web_app=WebAppInfo(url=miniapp_url))
            ],
            [
                KeyboardButton(text="💰 Kirim qilish"),
                KeyboardButton(text="💸 Chiqim qilish")
            ],
            [
                KeyboardButton(text="🤝 Tasdiq kutayotganlar")
            ]
        ],
        resize_keyboard=True,
    )

def get_contact_keyboard():
    return ReplyKeyboardMarkup(
        keyboard=[
            [KeyboardButton(text="📱 Raqamni yuborish", request_contact=True)]
        ],
        resize_keyboard=True,
        one_time_keyboard=True
    )
