from aiogram.fsm.state import State, StatesGroup

class TransactionState(StatesGroup):
    waiting_for_amount = State()
    waiting_for_account = State()
    waiting_for_payment_method = State()
    waiting_for_category = State()
    waiting_for_extra_field = State()
    waiting_for_extra_search = State()
    waiting_for_description = State()

class LeadForm(StatesGroup):
    waiting_for_name = State()
    waiting_for_phone = State()
