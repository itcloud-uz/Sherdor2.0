from aiogram import Router
from aiogram.filters import CommandStart
from aiogram.types import Message
from api_client import verify_user
from keyboards.reply import get_main_menu

router = Router()

@router.message(CommandStart())
async def cmd_start(message: Message):
    chat_id = str(message.chat.id)
    
    # Verify user with Laravel API
    data, status = await verify_user(chat_id)
    
    if status == 200:
        user_name = data.get('user', {}).get('name', 'Admin')
        await message.answer(
            f"Assalomu alaykum, {user_name}! ERP tizimiga xush kelibsiz.\n"
            "Quyidagi menyudan kerakli amalni tanlang:",
            reply_markup=get_main_menu()
        )
    else:
        error_msg = data.get('error', 'Sizda tizimga kirish huquqi yo\'q.')
        await message.answer(f"🛑 Xatolik: {error_msg}\nSizning Chat ID: <code>{chat_id}</code>", parse_mode="HTML")
