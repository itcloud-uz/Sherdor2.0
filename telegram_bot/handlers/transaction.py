from aiogram import Router, F
from aiogram.types import Message, CallbackQuery, InlineKeyboardMarkup, InlineKeyboardButton
from aiogram.fsm.context import FSMContext
from states import TransactionState
from api_client import send_transaction
from keyboards.reply import get_main_menu

router = Router()

def get_account_keyboard():
    return InlineKeyboardMarkup(
        inline_keyboard=[
            [
                InlineKeyboardButton(text="📥 Umumiy Tushum", callback_data="acc_main"),
                InlineKeyboardButton(text="🏢 Sof Balans (Kompaniya)", callback_data="acc_bank")
            ],
            [
                InlineKeyboardButton(text="💵 Kredit Balans (Kassa)", callback_data="acc_credit"),
                InlineKeyboardButton(text="📉 Umumiy Qarzlar", callback_data="acc_debt")
            ]
        ]
    )

def get_payment_keyboard():
    return InlineKeyboardMarkup(
        inline_keyboard=[
            [
                InlineKeyboardButton(text="💵 Naqd Kassa", callback_data="pay_cash"),
                InlineKeyboardButton(text="🏦 Bank hisobi", callback_data="pay_bank_transfer")
            ],
            [
                InlineKeyboardButton(text="💳 Plastik Karta", callback_data="pay_card")
            ]
        ]
    )

def get_category_keyboard(type_: str):
    if type_ == 'income':
        cats = [
            ["Mijoz to'lovi", "Sarmoya (Investitsiya)"],
            ["Qarz qaytishi", "Boshqa daromadlar"]
        ]
    else:
        cats = [
            ["Oylik maoshlar", "Ofis xarajatlari"],
            ["Qurilish materiallari", "Soliq va to'lovlar"],
            ["Boshqa xarajatlar"]
        ]
    
    inline_keyboard = []
    for row in cats:
        inline_keyboard.append([InlineKeyboardButton(text=c, callback_data=f"cat_{c}") for c in row])
        
    return InlineKeyboardMarkup(inline_keyboard=inline_keyboard)

@router.message(F.text.in_(["💰 Kirim qilish", "💸 Chiqim qilish"]))
async def start_transaction(message: Message, state: FSMContext):
    type_ = "income" if "Kirim" in message.text else "expense"
    await state.update_data(transaction_type=type_)
    await state.set_state(TransactionState.waiting_for_amount)
    await message.answer(f"Siz '{message.text}' bo'limini tanladingiz.\n\nIltimos, summani kiriting (faqat raqam):", reply_markup=get_main_menu())

@router.message(TransactionState.waiting_for_amount)
async def process_amount(message: Message, state: FSMContext):
    try:
        amount = float(message.text.replace(" ", "").replace(",", ""))
        if amount <= 0:
            raise ValueError
    except ValueError:
        await message.answer("Xato kiritdingiz. Iltimos, faqat musbat raqam kiriting:")
        return
        
    await state.update_data(amount=amount)
    data = await state.get_data()
    type_ = data.get("transaction_type")
    
    await state.set_state(TransactionState.waiting_for_account)
    
    action_text = "QAYSI HISOBGA tushmoqda?" if type_ == 'income' else "QAYSI HISOBDAN chiqyapti?"
    await message.answer(f"Summa qabul qilindi: {amount:,.2f}\n\nPul {action_text}", reply_markup=get_account_keyboard())

@router.callback_query(F.data.startswith("acc_"))
async def process_account(callback: CallbackQuery, state: FSMContext):
    acc_map = {
        "acc_main": "main",
        "acc_bank": "bank",
        "acc_credit": "credit",
        "acc_debt": "debt"
    }
    account_type = acc_map.get(callback.data, "main")
    await state.update_data(account_type=account_type)
    
    await state.set_state(TransactionState.waiting_for_payment_method)
    
    acc_names = {
        "acc_main": "Umumiy Tushum",
        "acc_bank": "Sof Balans (Kompaniya)",
        "acc_credit": "Kredit Balans (Kassa)",
        "acc_debt": "Umumiy Qarzlar"
    }
    
    try:
        await callback.message.edit_text(callback.message.html_text + f"\n\nTanlandi: <b>{acc_names.get(callback.data)}</b>", parse_mode="HTML")
    except Exception:
        pass
        
    await callback.answer()
    await callback.message.answer("Pul qaysi turda qabul qilindi/sarflandi?", reply_markup=get_payment_keyboard())

@router.callback_query(F.data.startswith("pay_"))
async def process_payment_method(callback: CallbackQuery, state: FSMContext):
    pay_map = {
        "pay_cash": "cash",
        "pay_bank_transfer": "bank_transfer",
        "pay_card": "card"
    }
    payment_method = pay_map.get(callback.data, "cash")
    await state.update_data(payment_method=payment_method)
    
    data = await state.get_data()
    type_ = data.get("transaction_type", "income")
    
    await state.set_state(TransactionState.waiting_for_category)
    
    pay_names = {
        "pay_cash": "Naqd Kassa",
        "pay_bank_transfer": "Bank hisobi",
        "pay_card": "Plastik Karta"
    }
    
    action_text = "Pul QAYERDAN kelyapti?" if type_ == 'income' else "Pul NIMA MAQSADDA sarflanyapti?"
    
    try:
        await callback.message.edit_text(callback.message.html_text + f"\n\nTo'lov turi: <b>{pay_names.get(callback.data)}</b>", parse_mode="HTML")
    except Exception:
        pass
        
    await callback.answer()
    await callback.message.answer(action_text, reply_markup=get_category_keyboard(type_))

@router.callback_query(F.data.startswith("cat_"))
async def process_category_callback(callback: CallbackQuery, state: FSMContext):
    await callback.answer()
    category = callback.data.replace("cat_", "")
    await _handle_category(callback.message, category, state, is_callback=True)

@router.message(TransactionState.waiting_for_category)
async def process_category_message(message: Message, state: FSMContext):
    category = message.text
    await _handle_category(message, category, state, is_callback=False)

async def _handle_category(message: Message, category: str, state: FSMContext, is_callback: bool = False):
    await state.update_data(category=category)
    await state.set_state(TransactionState.waiting_for_description)
    
    if is_callback:
        await message.edit_text(message.html_text + f"\n\nTanlandi: <b>{category}</b>", parse_mode="HTML")
        await message.answer("Ajoyib. Endi tranzaksiya uchun qisqacha izoh kiriting:")
    else:
        await message.answer(f"Kategoriya qabul qilindi: {category}\n\nAjoyib. Endi tranzaksiya uchun qisqacha izoh kiriting:")

@router.message(TransactionState.waiting_for_description)
async def process_description(message: Message, state: FSMContext):
    description = message.text
    data = await state.get_data()
    
    amount = data.get("amount")
    type_ = data.get("transaction_type")
    account_type = data.get("account_type", "main")
    payment_method = data.get("payment_method", "cash")
    category = data.get("category", "Noma'lum")
    
    chat_id = str(message.chat.id)
    
    # Send to API
    api_response, status = await send_transaction(chat_id, type_, amount, account_type, payment_method, category, description)
    
    await state.clear()
    
    if status == 200:
        await message.answer(
            f"✅ Muvaffaqiyatli saqlandi!\n\n"
            f"Turi: {'Daromad' if type_ == 'income' else 'Xarajat'}\n"
            f"Summa: {amount:,.2f} UZS\n"
            f"Hisob: {account_type.upper()}\n"
            f"To'lov turi: {payment_method.upper()}\n"
            f"Kategoriya: {category}\n"
            f"Izoh: {description}",
            reply_markup=get_main_menu()
        )
    else:
        err = api_response.get("error", "Noma'lum xatolik")
        await message.answer(f"🛑 Xatolik yuz berdi: {err}", reply_markup=get_main_menu())
