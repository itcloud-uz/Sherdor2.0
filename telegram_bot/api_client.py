import aiohttp
from config import API_BASE_URL, API_SECRET_KEY

async def _request(method: str, endpoint: str, data: dict = None):
    headers = {
        "Authorization": f"Bearer {API_SECRET_KEY}",
        "Accept": "application/json"
    }
    url = f"{API_BASE_URL}/{endpoint.lstrip('/')}"
    
    async with aiohttp.ClientSession() as session:
        try:
            if method.upper() == 'GET':
                async with session.get(url, headers=headers, params=data) as response:
                    return await response.json(), response.status
            else:
                async with session.post(url, headers=headers, json=data) as response:
                    return await response.json(), response.status
        except Exception as e:
            print(f"API Error: {e}")
            return {"error": str(e)}, 500

async def verify_user(chat_id: str):
    data, status = await _request("POST", "/verify-user", {"chat_id": str(chat_id)})
    return data, status

async def send_transaction(chat_id: str, type_: str, amount: float, account_type: str, payment_method: str, category: str, description: str):
    payload = {
        "chat_id": str(chat_id),
        "type": type_,
        "amount": amount,
        "account_type": account_type,
        "payment_method": payment_method,
        "category": category,
        "description": description
    }
    return await _request("POST", "/transactions", payload)

async def get_pending_transactions():
    return await _request("GET", "/transactions/pending")

async def approve_transaction(chat_id: str, transaction_id: int):
    payload = {
        "chat_id": str(chat_id),
        "transaction_id": transaction_id
    }
    return await _request("POST", "/transactions/approve", payload)
