from aiogram import Router, Bot
from aiogram.filters import CommandStart
from aiogram.types import Message, InlineKeyboardMarkup, InlineKeyboardButton, WebAppInfo, MenuButtonWebApp, ReplyKeyboardRemove
from api_client import verify_user
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
        
        # Foydalanuvchiga xabar yuborish va pastki oddiy tugmalarni to'liq tozalash (ReplyKeyboardRemove)
        await message.answer(
            f"Assalomu alaykum, <b>{user_name}</b>! ERP Moliya tizimiga xush kelibsiz.\n\n"
            "Barcha kirim, chiqim va tasdiqlash amaliyotlari qulay <b>Mini App</b> orqali amalga oshiriladi.\n\n"
            "Dasturga kirish uchun quyidagi tugmani bosing:",
            reply_markup=inline_kb,
            parse_mode="HTML"
        )
        
        # Eskirgan pastki tugmalar qolib ketgan bo'lsa ularni o'chirish
        await message.answer(
            "👇 Mini Appni pastki chap burchakdagi <b>Menyu</b> tugmasi orqali ham istalgan vaqtda ochishingiz mumkin.",
            reply_markup=ReplyKeyboardRemove(),
            parse_mode="HTML"
        )
    else:
        error_msg = data.get('error', 'Sizda tizimga kirish huquqi yo\'q.')
        await message.answer(
            f"🛑 Xatolik: {error_msg}\nSizning Chat ID: <code>{chat_id}</code>", 
            reply_markup=ReplyKeyboardRemove(),
            parse_mode="HTML"
        )

