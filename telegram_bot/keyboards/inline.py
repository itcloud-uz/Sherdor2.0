from aiogram.types import InlineKeyboardMarkup, InlineKeyboardButton

def get_approval_keyboard(transaction_id: int):
    return InlineKeyboardMarkup(
        inline_keyboard=[
            [
                InlineKeyboardButton(text="✔️ Tasdiqlash", callback_data=f"approve_{transaction_id}"),
                InlineKeyboardButton(text="❌ Rad etish", callback_data=f"reject_{transaction_id}")
            ]
        ]
    )
