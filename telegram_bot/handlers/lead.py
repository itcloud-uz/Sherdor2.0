from aiogram import Router, F, types
from aiogram.filters import CommandStart, Command
from aiogram.fsm.context import FSMContext
from states import LeadForm
from keyboards.reply import get_contact_keyboard, get_main_menu
from api_client import send_lead_to_crm, verify_user
from aiogram.types import ReplyKeyboardRemove

router = Router()

@router.message(CommandStart())
async def start_handler(message: types.Message, state: FSMContext):
    chat_id = str(message.chat.id)
    
    # Verify user with Laravel API
    data, status = await verify_user(chat_id)
    
    if status == 200:
        # This is an employee/admin
        user_name = data.get('user', {}).get('name', 'Admin')
        await message.answer(
            f"Assalomu alaykum, {user_name}! ERP tizimiga xush kelibsiz.\n"
            "Quyidagi menyudan kerakli amalni tanlang:",
            reply_markup=get_main_menu()
        )
    else:
        # Not an employee, so treat as a Lead (customer)
        await message.answer(
            "👋 Assalomu alaykum! Sher-Dor savdo va xizmat ko'rsatish markaziga xush kelibsiz.\n\n"
            "Iltimos, ismingizni kiriting:",
            reply_markup=ReplyKeyboardRemove()
        )
        await state.set_state(LeadForm.waiting_for_name)

@router.message(LeadForm.waiting_for_name)
async def process_name(message: types.Message, state: FSMContext):
    name = message.text
    if not name:
        return await message.answer("Iltimos, ismingizni matn ko'rinishida kiriting:")
        
    await state.update_data(name=name)
    
    await message.answer(
        f"Rahmat, {name}! Endi siz bilan bog'lanishimiz uchun telefon raqamingizni yuboring.\n"
        "Pastdagi '📱 Raqamni yuborish' tugmasini bosing:",
        reply_markup=get_contact_keyboard()
    )
    await state.set_state(LeadForm.waiting_for_phone)

@router.message(LeadForm.waiting_for_phone)
async def process_phone(message: types.Message, state: FSMContext):
    # Accept contact or text
    if message.contact:
        phone = message.contact.phone_number
    elif message.text:
        phone = message.text
    else:
        return await message.answer("Iltimos, raqamingizni yuboring yoki matn orqali kiriting:")
        
    data = await state.get_data()
    name = data.get("name", "Mijoz")
    
    # Send to CRM
    response_data, status = await send_lead_to_crm(name, phone)
    
    if status in (200, 201):
        await message.answer(
            "✅ Rahmat! Ma'lumotlaringiz qabul qilindi. Menejerimiz tez orada siz bilan bog'lanadi.",
            reply_markup=ReplyKeyboardRemove()
        )
    else:
        # Error handling
        error_msg = response_data.get('message', 'Tizimda xatolik yuz berdi.')
        await message.answer(
            f"❌ Kechirasiz, xatolik yuz berdi: {error_msg}\nIltimos, keyinroq urinib ko'ring.",
            reply_markup=ReplyKeyboardRemove()
        )
        
    await state.clear()
