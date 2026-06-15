import logging
from aiogram import Router, F
from aiogram.types import Message, CallbackQuery, InlineKeyboardMarkup, InlineKeyboardButton
from aiogram.fsm.context import FSMContext
from states import TransactionState
from api_client import send_transaction, get_metadata
from keyboards.reply import get_main_menu

router = Router()

def build_inline_keyboard(buttons_data, cols=2):
    keyboard = []
    row = []
    for text, cb_data in buttons_data:
        row.append(InlineKeyboardButton(text=text, callback_data=cb_data))
        if len(row) == cols:
            keyboard.append(row)
            row = []
    if row:
        keyboard.append(row)
    return InlineKeyboardMarkup(inline_keyboard=keyboard)

async def ask_next_field(message: Message, state: FSMContext):
    data = await state.get_data()
    pending_fields = data.get("pending_fields", [])
    metadata = data.get("metadata", {})
    
    if not pending_fields:
        await state.set_state(TransactionState.waiting_for_description)
        await message.answer("Ajoyib. Endi tranzaksiya uchun qisqacha izoh kiriting:")
        return

    next_field = pending_fields.pop(0)
    await state.update_data(pending_fields=pending_fields)

    if next_field == 'contract':
        contracts = metadata.get("contracts", [])
        if contracts:
            buttons = []
            for c in contracts:
                client = c.get('client') or {}
                unit = c.get('unit') or {}
                client_name = f"{client.get('first_name', '')} {client.get('last_name', '')}".strip()
                unit_num = str(unit.get('unit_number', ''))
                label = f"{client_name} (Xonadon: {unit_num})"
                buttons.append((label, f"extra_contract_{c['id']}"))
            
            if len(contracts) <= 15:
                await state.set_state(TransactionState.waiting_for_extra_field)
                await message.answer(
                    "<b>Mijoz shartnomasini tanlang:</b>",
                    reply_markup=build_inline_keyboard(buttons, cols=1),
                    parse_mode="HTML"
                )
            else:
                await state.set_state(TransactionState.waiting_for_extra_field)
                buttons_display = buttons[:15]
                buttons_display.append(("🔍 Qidirish (Ism yoki xonadon bo'yicha)", "contract_search_mode"))
                await message.answer(
                    "<b>Mijoz shartnomasini tanlang yoki qidiruvdan foydalaning:</b>",
                    reply_markup=build_inline_keyboard(buttons_display, cols=1),
                    parse_mode="HTML"
                )
        else:
            await message.answer("🛑 Tizimda faol shartnomalar topilmadi. Amaliyot to'xtatildi.")
            await state.clear()
            return
    elif next_field == 'partner':
        partners = metadata.get("partners", [])
        if len(partners) <= 15:
            await state.set_state(TransactionState.waiting_for_extra_field)
            buttons = [(p['name'], f"extra_partner_{p['id']}") for p in partners]
            await message.answer("Tashabbuskor / Hamkorni tanlang:", reply_markup=build_inline_keyboard(buttons, cols=1))
        else:
            await state.set_state(TransactionState.waiting_for_extra_search)
            await state.update_data(search_type='partner')
            await message.answer("Hamkor ismini qidirish uchun kiriting:")
    elif next_field == 'worker':
        await state.set_state(TransactionState.waiting_for_extra_search)
        await state.update_data(search_type='worker')
        await message.answer("Ishchini qidirish uchun uning ismini kiriting:")
    elif next_field == 'supplier':
        suppliers = metadata.get("suppliers", [])
        if len(suppliers) <= 15:
            await state.set_state(TransactionState.waiting_for_extra_field)
            buttons = [(s['name'], f"extra_supplier_{s['id']}") for s in suppliers]
            await message.answer("Ta'minotchini tanlang:", reply_markup=build_inline_keyboard(buttons, cols=1))
        else:
            await state.set_state(TransactionState.waiting_for_extra_search)
            await state.update_data(search_type='supplier')
            await message.answer("Ta'minotchi nomini qidirish uchun kiriting:")
    elif next_field == 'brigade':
        brigades = metadata.get("brigades", [])
        if len(brigades) <= 15:
            await state.set_state(TransactionState.waiting_for_extra_field)
            buttons = [(b['name'], f"extra_brigade_{b['id']}") for b in brigades]
            await message.answer("Brigadani tanlang:", reply_markup=build_inline_keyboard(buttons, cols=1))
        else:
            await state.set_state(TransactionState.waiting_for_extra_search)
            await state.update_data(search_type='brigade')
            await message.answer("Brigadani qidirish uchun nomini kiriting:")
    elif next_field == 'machine_owner':
        owners = metadata.get("machine_owners", [])
        if len(owners) <= 15:
            await state.set_state(TransactionState.waiting_for_extra_field)
            buttons = [(o['name'], f"extra_machine_owner_{o['id']}") for o in owners]
            await message.answer("Texnika egasini tanlang:", reply_markup=build_inline_keyboard(buttons, cols=1))
        else:
            await state.set_state(TransactionState.waiting_for_extra_search)
            await state.update_data(search_type='machine_owner')
            await message.answer("Texnika egasini qidirish uchun nomini kiriting:")
    elif next_field == 'building':
        buildings = metadata.get("buildings", [])
        if len(buildings) <= 15:
            await state.set_state(TransactionState.waiting_for_extra_field)
            buttons = [(b['name'], f"extra_building_{b['id']}") for b in buildings]
            await message.answer("Obyektni tanlang:", reply_markup=build_inline_keyboard(buttons, cols=1))
        else:
            await state.set_state(TransactionState.waiting_for_extra_search)
            await state.update_data(search_type='building')
            await message.answer("Obyekt nomini qidirish uchun kiriting:")

@router.message(F.text.in_(["💰 Kirim qilish", "💸 Chiqim qilish"]))
async def start_transaction(message: Message, state: FSMContext):
    type_ = "income" if "Kirim" in message.text else "expense"
    await state.clear()
    
    loading_msg = await message.answer("Ma'lumotlar yuklanmoqda, iltimos kuting...")
    
    metadata, status = await get_metadata()
    if status != 200:
        await loading_msg.edit_text("🛑 Tizim ma'lumotlarini yuklashda xatolik yuz berdi. Iltimos, keyinroq qayta urining.")
        return
        
    await state.update_data(transaction_type=type_, metadata=metadata)
    await state.set_state(TransactionState.waiting_for_amount)
    
    action_title = "💰 Kirim (Daromad)" if type_ == 'income' else "💸 Chiqim (Xarajat)"
    await loading_msg.edit_text(
        f"Siz <b>{action_title}</b> bo'limini tanladingiz.\n\n"
        f"Iltimos, summani kiriting (faqat raqam):",
        parse_mode="HTML"
    )

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
    metadata = data.get("metadata", {})
    registers = metadata.get("registers", [])
    
    if not registers:
        await message.answer("🛑 Tizimda faol kassalar topilmadi. Amaliyot to'xtatildi.")
        await state.clear()
        return
        
    await state.set_state(TransactionState.waiting_for_account)
    
    buttons = [(f"💵 {r['name']} ({r['currency']})", f"acc_{r['id']}") for r in registers]
    
    type_ = data.get("transaction_type")
    action_text = "qaysi hisobga tushmoqda?" if type_ == 'income' else "qaysi hisobdan chiqyapti?"
    
    await message.answer(
        f"Summa qabul qilindi: {amount:,.2f} UZS\n\nPul {action_text}",
        reply_markup=build_inline_keyboard(buttons, cols=1)
    )

@router.callback_query(F.data.startswith("acc_"))
async def process_account(callback: CallbackQuery, state: FSMContext):
    await callback.answer()
    
    register_id = int(callback.data.replace("acc_", ""))
    await state.update_data(account_id=register_id)
    
    data = await state.get_data()
    metadata = data.get("metadata", {})
    registers = metadata.get("registers", [])
    
    register = next((r for r in registers if r['id'] == register_id), None)
    register_name = register['name'] if register else f"Kassa #{register_id}"
    await state.update_data(account_name=register_name)
    
    try:
        await callback.message.edit_text(
            callback.message.html_text + f"\n\nKassa: <b>{register_name}</b>",
            parse_mode="HTML"
        )
    except Exception:
        pass
        
    await state.set_state(TransactionState.waiting_for_payment_method)
    
    buttons = [
        ("💵 Naqd Kassa", "pay_cash"),
        ("🏦 Bank hisobi", "pay_bank_transfer"),
        ("💳 Plastik Karta", "pay_card")
    ]
    
    await callback.message.answer("To'lov turini tanlang:", reply_markup=build_inline_keyboard(buttons, cols=2))

@router.callback_query(F.data.startswith("pay_"))
async def process_payment_method(callback: CallbackQuery, state: FSMContext):
    await callback.answer()
    
    pay_map = {
        "pay_cash": "cash",
        "pay_bank_transfer": "bank_transfer",
        "pay_card": "card"
    }
    pay_names = {
        "pay_cash": "Naqd Kassa",
        "pay_bank_transfer": "Bank hisobi",
        "pay_card": "Plastik Karta"
    }
    
    payment_method = pay_map.get(callback.data, "cash")
    payment_name = pay_names.get(callback.data, "Naqd Kassa")
    
    await state.update_data(payment_method=payment_method, payment_name=payment_name)
    
    data = await state.get_data()
    type_ = data.get("transaction_type")
    metadata = data.get("metadata", {})
    categories = metadata.get("categories", [])
    
    filtered_cats = [c for c in categories if c['type'] == type_]
    
    if not filtered_cats:
        await callback.message.answer("🛑 Tizimda ushbu turdagi kategoriyalar topilmadi. Amaliyot to'xtatildi.")
        await state.clear()
        return
        
    try:
        await callback.message.edit_text(
            callback.message.html_text + f"\n\nTo'lov turi: <b>{payment_name}</b>",
            parse_mode="HTML"
        )
    except Exception:
        pass
        
    await state.set_state(TransactionState.waiting_for_category)
    
    buttons = [(c['name'], f"cat_{c['id']}") for c in filtered_cats]
    
    action_text = "Pul QAYERDAN kelyapti?" if type_ == 'income' else "Pul NIMA MAQSADDA sarflanyapti?"
    await callback.message.answer(action_text, reply_markup=build_inline_keyboard(buttons, cols=2))

@router.callback_query(F.data.startswith("cat_"))
async def process_category_callback(callback: CallbackQuery, state: FSMContext):
    await callback.answer()
    
    category_id = int(callback.data.replace("cat_", ""))
    await state.update_data(category_id=category_id)
    
    data = await state.get_data()
    metadata = data.get("metadata", {})
    categories = metadata.get("categories", [])
    
    category = next((c for c in categories if c['id'] == category_id), None)
    category_name = category['name'] if category else f"Kategoriya #{category_id}"
    await state.update_data(category_name=category_name)
    
    try:
        await callback.message.edit_text(
            callback.message.html_text + f"\n\nKategoriya: <b>{category_name}</b>",
            parse_mode="HTML"
        )
    except Exception:
        pass
        
    cat_name_lower = category_name.lower()
    pending_fields = []
    
    if "mijoz" in cat_name_lower or "client" in cat_name_lower:
        pending_fields.append("contract")
    if any(k in cat_name_lower for k in ["ta'sischi", "hamkor", "partner", "sarmoya", "investitsiya"]):
        pending_fields.append("partner")
    if any(k in cat_name_lower for k in ["oylik", "avans", "ishchi"]):
        pending_fields.append("worker")
    if any(k in cat_name_lower for k in ["ta'minotchi", "material"]):
        pending_fields.append("supplier")
    if any(k in cat_name_lower for k in ["brigada", "yollanma"]):
        pending_fields.append("brigade")
    if any(k in cat_name_lower for k in ["texnika", "arendator"]):
        pending_fields.append("machine_owner")
    if any(k in cat_name_lower for k in ["obyekt", "material"]):
        pending_fields.append("building")
        
    await state.update_data(pending_fields=pending_fields)
    await ask_next_field(callback.message, state)

@router.message(TransactionState.waiting_for_category)
async def process_category_message(message: Message):
    await message.answer("Iltimos, yuqoridagi tugmalardan birini tanlang:")

@router.message(TransactionState.waiting_for_extra_search)
async def process_extra_search(message: Message, state: FSMContext):
    query = message.text.strip().lower()
    if not query:
        await message.answer("Iltimos, qidiruv matnini yuboring:")
        return

    data = await state.get_data()
    search_type = data.get("search_type")
    metadata = data.get("metadata", {})

    buttons = []
    
    if search_type == 'contract':
        contracts = metadata.get("contracts", [])
        matches = []
        for c in contracts:
            client = c.get('client') or {}
            unit = c.get('unit') or {}
            client_name = f"{client.get('first_name', '')} {client.get('last_name', '')}".strip()
            unit_num = str(unit.get('unit_number', ''))
            
            match_str = f"{client_name} {unit_num}".lower()
            if query in match_str:
                label = f"{client_name} (Xonadon: {unit_num})"
                matches.append((label, f"extra_contract_{c['id']}"))
        buttons = matches[:10]
        
    elif search_type == 'worker':
        workers = metadata.get("workers", [])
        matches = [w for w in workers if query in w['name'].lower()]
        buttons = [(w['name'], f"extra_worker_{w['id']}") for w in matches[:10]]
        
    elif search_type == 'partner':
        partners = metadata.get("partners", [])
        matches = [p for p in partners if query in p['name'].lower()]
        buttons = [(p['name'], f"extra_partner_{p['id']}") for p in matches[:10]]
        
    elif search_type == 'supplier':
        suppliers = metadata.get("suppliers", [])
        matches = [s for s in suppliers if query in s['name'].lower()]
        buttons = [(s['name'], f"extra_supplier_{s['id']}") for s in matches[:10]]
        
    elif search_type == 'building':
        buildings = metadata.get("buildings", [])
        matches = [b for b in buildings if query in b['name'].lower()]
        buttons = [(b['name'], f"extra_building_{b['id']}") for b in matches[:10]]
        
    elif search_type == 'brigade':
        brigades = metadata.get("brigades", [])
        matches = [b for b in brigades if query in b['name'].lower()]
        buttons = [(b['name'], f"extra_brigade_{b['id']}") for b in matches[:10]]
        
    elif search_type == 'machine_owner':
        owners = metadata.get("machine_owners", [])
        matches = [o for o in owners if query in o['name'].lower()]
        buttons = [(o['name'], f"extra_machine_owner_{o['id']}") for o in matches[:10]]

    if not buttons:
        await message.answer(f"Hech qanday natija topilmadi ('{query}'). Iltimos, qaytadan boshqa so'z bilan qidiring:")
        return

    markup = build_inline_keyboard(buttons, cols=1)
    await message.answer("Quyidagi natijalardan birini tanlang:", reply_markup=markup)

@router.callback_query(F.data == "contract_search_mode")
async def process_contract_search_mode(callback: CallbackQuery, state: FSMContext):
    await callback.answer()
    await state.set_state(TransactionState.waiting_for_extra_search)
    await state.update_data(search_type='contract')
    await callback.message.answer(
        "Mijozning ismi-familiyasi yoki xonadon raqamini kiriting (masalan: 'Ali' yoki '12'):"
    )

@router.callback_query(F.data.startswith("extra_"))
async def process_extra_callback(callback: CallbackQuery, state: FSMContext):
    await callback.answer()
    
    parts = callback.data.split("_")
    if len(parts) == 4 and parts[1] == "machine" and parts[2] == "owner":
        entity_type = "machine_owner"
        entity_id = int(parts[3])
    else:
        entity_type = parts[1]
        entity_id = int(parts[2])
        
    if entity_type == 'contract':
        await state.update_data(contract_id=entity_id)
        data = await state.get_data()
        metadata = data.get("metadata", {})
        c = next((x for x in metadata.get("contracts", []) if x['id'] == entity_id), None)
        if c:
            client = c.get('client') or {}
            unit = c.get('unit') or {}
            client_name = f"{client.get('first_name', '')} {client.get('last_name', '')}".strip()
            unit_num = str(unit.get('unit_number', ''))
            await state.update_data(contract_name=f"{client_name} (Xonadon: {unit_num})")
    elif entity_type == 'partner':
        await state.update_data(partner_id=entity_id)
        data = await state.get_data()
        metadata = data.get("metadata", {})
        p = next((x for x in metadata.get("partners", []) if x['id'] == entity_id), None)
        if p:
            await state.update_data(partner_name=p['name'])
    elif entity_type == 'worker':
        await state.update_data(worker_id=entity_id)
        data = await state.get_data()
        metadata = data.get("metadata", {})
        w = next((x for x in metadata.get("workers", []) if x['id'] == entity_id), None)
        if w:
            await state.update_data(worker_name=w['name'])
    elif entity_type == 'supplier':
        await state.update_data(supplier_id=entity_id)
        data = await state.get_data()
        metadata = data.get("metadata", {})
        s = next((x for x in metadata.get("suppliers", []) if x['id'] == entity_id), None)
        if s:
            await state.update_data(supplier_name=s['name'])
    elif entity_type == 'building':
        await state.update_data(building_id=entity_id)
        data = await state.get_data()
        metadata = data.get("metadata", {})
        b = next((x for x in metadata.get("buildings", []) if x['id'] == entity_id), None)
        if b:
            await state.update_data(building_name=b['name'])
    elif entity_type == 'brigade':
        await state.update_data(target_type='brigade', target_id=entity_id)
        data = await state.get_data()
        metadata = data.get("metadata", {})
        br = next((x for x in metadata.get("brigades", []) if x['id'] == entity_id), None)
        if br:
            await state.update_data(brigade_name=br['name'])
    elif entity_type == 'machine_owner':
        await state.update_data(target_type='machine_owner', target_id=entity_id)
        data = await state.get_data()
        metadata = data.get("metadata", {})
        mo = next((x for x in metadata.get("machine_owners", []) if x['id'] == entity_id), None)
        if mo:
            await state.update_data(machine_owner_name=mo['name'])

    try:
        state_data = await state.get_data()
        selected_name = ""
        if entity_type == 'contract':
            selected_name = state_data.get("contract_name", "")
        elif entity_type == 'partner':
            selected_name = state_data.get("partner_name", "")
        elif entity_type == 'worker':
            selected_name = state_data.get("worker_name", "")
        elif entity_type == 'supplier':
            selected_name = state_data.get("supplier_name", "")
        elif entity_type == 'building':
            selected_name = state_data.get("building_name", "")
        elif entity_type == 'brigade':
            selected_name = state_data.get("brigade_name", "")
        elif entity_type == 'machine_owner':
            selected_name = state_data.get("machine_owner_name", "")
            
        display_name = selected_name if selected_name else f"{entity_type.upper()} #{entity_id}"
        await callback.message.edit_text(
            callback.message.html_text + f"\n\nTanlandi: <b>{display_name}</b>",
            parse_mode="HTML"
        )
    except Exception:
        pass

    await ask_next_field(callback.message, state)

@router.message(TransactionState.waiting_for_extra_field)
async def process_extra_field_message(message: Message):
    await message.answer("Iltimos, yuqoridagi variantlardan birini tanlang:")

@router.message(TransactionState.waiting_for_description)
async def process_description(message: Message, state: FSMContext):
    description = message.text.strip()
    data = await state.get_data()
    
    amount = data.get("amount")
    type_ = data.get("transaction_type")
    account_id = data.get("account_id")
    payment_method = data.get("payment_method")
    category_id = data.get("category_id")
    
    extra_params = {}
    if "contract_id" in data:
        extra_params["contract_id"] = data["contract_id"]
    if "partner_id" in data:
        extra_params["partner_id"] = data["partner_id"]
    if "worker_id" in data:
        extra_params["worker_id"] = data["worker_id"]
    if "supplier_id" in data:
        extra_params["supplier_id"] = data["supplier_id"]
    if "building_id" in data:
        extra_params["building_id"] = data["building_id"]
    if "target_type" in data:
        extra_params["target_type"] = data["target_type"]
        extra_params["target_id"] = data["target_id"]
        
    chat_id = str(message.chat.id)
    
    loading_msg = await message.answer("Tranzaksiya saqlanmoqda, iltimos kuting...")
    
    api_response, status = await send_transaction(
        chat_id=chat_id,
        type_=type_,
        amount=amount,
        account_id=account_id,
        payment_method=payment_method,
        category_id=category_id,
        description=description,
        extra_params=extra_params
    )
    
    await state.clear()
    
    if status == 200:
        type_title = "Kirim (Daromad)" if type_ == 'income' else "Chiqim (Xarajat)"
        acc_name = data.get("account_name", f"Kassa #{account_id}")
        pay_name = data.get("payment_name", payment_method)
        cat_name = data.get("category_name", f"Kategoriya #{category_id}")
        
        success_text = (
            f"✅ <b>Muvaffaqiyatli saqlandi va tasdiqlash kutilmoqda!</b>\n\n"
            f"🏷️ <b>Turi:</b> {type_title}\n"
            f"💰 <b>Summa:</b> {amount:,.2f} UZS\n"
            f"🏦 <b>Kassa:</b> {acc_name}\n"
            f"💳 <b>To'lov turi:</b> {pay_name}\n"
            f"📂 <b>Kategoriya:</b> {cat_name}\n"
        )
        
        if "contract_name" in data:
            success_text += f"👤 <b>Mijoz/Shartnoma:</b> {data['contract_name']}\n"
        if "partner_name" in data:
            success_text += f"👤 <b>Hamkor:</b> {data['partner_name']}\n"
        if "worker_name" in data:
            success_text += f"👤 <b>Ishchi:</b> {data['worker_name']}\n"
        if "supplier_name" in data:
            success_text += f"👤 <b>Ta'minotchi:</b> {data['supplier_name']}\n"
        if "building_name" in data:
            success_text += f"🏢 <b>Obyekt:</b> {data['building_name']}\n"
        if "brigade_name" in data:
            success_text += f"👥 <b>Brigada:</b> {data['brigade_name']}\n"
        if "machine_owner_name" in data:
            success_text += f"👤 <b>Texnika egasi:</b> {data['machine_owner_name']}\n"
            
        success_text += f"💬 <b>Izoh:</b> {description}"
        
        await loading_msg.edit_text(success_text, parse_mode="HTML", reply_markup=get_main_menu())
    else:
        err = api_response.get("error", "Noma'lum xatolik")
        await loading_msg.edit_text(f"🛑 <b>Xatolik yuz berdi:</b> {err}", parse_mode="HTML", reply_markup=get_main_menu())
