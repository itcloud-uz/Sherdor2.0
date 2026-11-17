from aiogram import Router, Bot
from aiogram.filters import CommandStart
from aiogram.types import Message, InlineKeyboardMarkup, InlineKeyboardButton, WebAppInfo, MenuButtonWebApp
from api_client import verify_user
from keyboards.reply import get_main_menu
from config import APP_URL

router = Router()

@router.message(CommandStart())
async def cmd_start(message: Message, bot: Bot):
    chat_id = str(message.chat.id)
    
    # Verify user with Laravel API
    data, status = await verify_user(chat_id)
    
    if status == 200:
        user_name = data.get('user', {}).get('name', 'Xodim')
        miniapp_url = f"{APP_URL.rstrip('/')}/miniapp/transactions"
        
        # Telegram interfeysining pastki burchagidagi "Menyu" tugmasini WebApp ga ulash
        try:
            await bot.set_chat_menu_button(
                chat_id=message.chat.id,
                menu_button=MenuButtonWebApp(text="Kirim/Chiqim", web_app=WebAppInfo(url=miniapp_url))
            )
        except Exception:
            pass
            
        inline_kb = InlineKeyboardMarkup(
            inline_keyboard=[
                [
                    InlineKeyboardButton(
                        text="📱 Mini Appni ochish (Kirim / Chiqim)",
                        web_app=WebAppInfo(url=miniapp_url)
                    )
                ]
            ]
        )
        
        await message.answer(
            f"Assalomu alaykum, <b>{user_name}</b>! ERP Moliya tizimiga xush kelibsiz.\n\n"
            "Kirim va chiqimlarni qulay interfeysda kiritish uchun quyidagi <b>Mini App</b> tugmasini bosing:",
            reply_markup=inline_kb,
            parse_mode="HTML"
        )
        
        await message.answer(
            "Yoki quyidagi menyudan foydalanishingiz mumkin:",
            reply_markup=get_main_menu()
        )
    else:
        error_msg = data.get('error', 'Sizda tizimga kirish huquqi yo\'q.')
        await message.answer(f"🛑 Xatolik: {error_msg}\nSizning Chat ID: <code>{chat_id}</code>", parse_mode="HTML")
