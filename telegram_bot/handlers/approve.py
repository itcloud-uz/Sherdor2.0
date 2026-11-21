from aiogram import Router, F
from aiogram.types import Message, CallbackQuery
from api_client import get_pending_transactions, approve_transaction
from keyboards.inline import get_approval_keyboard

router = Router()

from aiogram.types import Message, CallbackQuery, InlineKeyboardMarkup, InlineKeyboardButton, WebAppInfo, ReplyKeyboardRemove
from config import APP_URL

@router.message(F.text == "🤝 Tasdiq kutayotganlar")
async def show_pending(message: Message):
    miniapp_url = f"{APP_URL.rstrip('/')}/miniapp/transactions"
    inline_kb = InlineKeyboardMarkup(
        inline_keyboard=[
            [
                InlineKeyboardButton(
                    text="⏳ Mini Appda Tasdiqlash",
                    web_app=WebAppInfo(url=miniapp_url)
                )
            ]
        ]
    )
    await message.answer(
        "Tranzaksiyalarni tasdiqlash yoki rad etish Mini App orqali amalga oshiriladi:",
        reply_markup=inline_kb
    )
    await message.answer(
        "Pastki menyu tugmalari olib tashlandi.",
        reply_markup=ReplyKeyboardRemove()
    )


@router.callback_query(F.data.startswith("approve_"))
async def process_approve(callback: CallbackQuery):
    transaction_id = int(callback.data.split("_")[1])
    chat_id = str(callback.message.chat.id)
    
    data, status = await approve_transaction(chat_id, transaction_id)
    
    if status == 200:
        await callback.message.edit_text(callback.message.html_text + "\n\n✅ <b>TASDIQLANDI</b>", parse_mode="HTML")
        await callback.answer("Muvaffaqiyatli tasdiqlandi!")
    else:
        err = data.get("error", "Xatolik")
        await callback.answer(f"Xato: {err}", show_alert=True)

@router.callback_query(F.data.startswith("reject_"))
async def process_reject(callback: CallbackQuery):
    # For now we don't have a reject endpoint, but we can just mark it in UI
    await callback.message.edit_text(callback.message.html_text + "\n\n❌ <b>RAD ETILDI</b>", parse_mode="HTML")
    await callback.answer("Rad etildi.")
