import aiohttp
from config import API_BASE_URL, API_SECRET_KEY

async def _request(method: str, endpoint: str, data: dict = None):
    headers = {
        "Authorization": f"Bearer {API_SECRET_KEY}",
        "Accept": "application/json",
        "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"
    }
    url = f"{API_BASE_URL}/{endpoint.lstrip('/')}"

    async with aiohttp.ClientSession() as session:
        try:
            if method.upper() == 'GET':
                req = session.get(url, headers=headers, params=data)
            else:
                req = session.post(url, headers=headers, json=data)
                
            async with req as response:
                try:
                    response_data = await response.json()
                    
                    # --- MANA SHU JOSUS QO'SHILDI ---
                    print(f"\n--- LARAVEL JAVOBI ({endpoint}) ---")
                    print(f"Status kod: {response.status}")
                    print(f"Asl xato: {response_data}")
                    print("----------------------------------\n")
                    # --------------------------------
                    
                except Exception:
                    error_text = await response.text()
                    print(f"Serverdan JSON bo'lmagan javob keldi! Status: {response.status}. Matn: {error_text[:150]}")
                    response_data = {"error": "Serverdan xato javob keldi."}
                    
                return response_data, response.status

        except Exception as e:
            print(f"API Connection Error: {e}")
            return {"error": str(e)}, 500

async def verify_user(chat_id: str):
    data, status = await _request("POST", "/bot/v1/verify-user", {"chat_id": str(chat_id)})
    return data, status

async def send_transaction(chat_id: str, type_: str, amount: float, account_type: str, payment_method: str, category: str, description: str = ""):
    payload = {
        "chat_id": str(chat_id),
        "type": type_,
        "amount": amount,
        "account_type": account_type,
        "payment_method": payment_method,
        "category": category,
        "description": description,
        "source": "telegram"
    }
    return await _request("POST", "/bot/v1/transactions", payload)

async def get_pending_transactions():
    return await _request("GET", "/bot/v1/transactions/pending")

async def approve_transaction(chat_id: str, transaction_id: int):
    payload = {
        "chat_id": str(chat_id),
        "transaction_id": transaction_id
    }
    return await _request("POST", "/bot/v1/transactions/approve", payload)

async def send_lead_to_crm(name: str, phone: str):
    payload = {
        "full_name": name,
        "phone": phone,
        "source": "telegram"
    }
    return await _request("POST", "/leads", payload)
