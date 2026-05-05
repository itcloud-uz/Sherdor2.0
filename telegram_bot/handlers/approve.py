from aiogram import Router, F
from aiogram.types import Message, CallbackQuery
from api_client import get_pending_transactions, approve_transaction
from keyboards.inline import get_approval_keyboard

router = Router()

@router.message(F.text == "🤝 Tasdiq kutayotganlar")
async def show_pending(message: Message):
    data, status = await get_pending_transactions()
    
    if status != 200:
        await message.answer("API xatosi yuz berdi yoki ruxsat yo'q.")
        return
        
    transactions = data.get("transactions", [])
    
    if not transactions:
        await message.answer("Tasdiq kutayotgan tranzaksiyalar yo'q.")
        return
        
    for txn in transactions:
        t_type = "Daromad" if txn['type'] == 'income' else "Xarajat"
        creator = txn.get('creator', {}).get('name', 'Noma\'lum')
        amount = float(txn['amount'])
        
        text = (
            f"📝 <b>Yangi So'rov:</b> #{txn['id']}\n"
            f"👤 <b>Yaratuvchi:</b> {creator}\n"
            f"🏷️ <b>Turi:</b> {t_type}\n"
            f"💰 <b>Summa:</b> {amount:,.2f} {txn['currency']}\n"
            f"💬 <b>Izoh:</b> {txn.get('notes', '')}\n"
            f"📅 <b>Sana:</b> {txn['created_at']}"
        )
        
        await message.answer(text, reply_markup=get_approval_keyboard(txn['id']), parse_mode="HTML")

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
