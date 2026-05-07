from aiogram.types import ReplyKeyboardMarkup, KeyboardButton

def get_main_menu():
    return ReplyKeyboardMarkup(
        keyboard=[
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
