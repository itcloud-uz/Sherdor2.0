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
        input_field_placeholder="Tanlang..."
    )
