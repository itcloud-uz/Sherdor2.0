import sys
import json
import os
import fitz  # PyMuPDF
import re

def parse_pdf(file_path):
    try:
        if not os.path.exists(file_path):
            raise Exception("File does not exist")
            
        doc = fitz.open(file_path)
        full_text = ""
        
        # Barcha sahifalardagi matnni o'qib olish
        for page in doc:
            full_text += page.get_text() + "\n"
            
        doc.close()
        
        # Matnni tozalash va ortiqcha bo'shliqlarni olib tashlash
        clean_text = re.sub(r'\s+', ' ', full_text)
        
        # 1. Shartnoma raqamini qidirish (Masalan: Шартнома №141, Договор №141, Contract N141)
        contract_match = re.search(r'(?:Шартнома|Договор|Shartnoma)\s*(?:№|No|N|#)\s*([A-Za-z0-9\-]+)', clean_text, re.IGNORECASE)
        contract_number = contract_match.group(1) if contract_match else ""
        
        # 2. Sanalarni qidirish (Masalan: 02.04.2026 yoki 02.04.2026 - 31.12.2026)
        dates = re.findall(r'(\d{2}\.\d{2}\.\d{4})', clean_text)
        start_date = ""
        end_date = ""
        
        if dates:
            # Odatda birinchi topilgan sana shartnoma tuzilgan sana bo'ladi
            # YYYY-MM-DD formatiga o'tkazamiz
            d1 = dates[0].split('.')
            start_date = f"{d1[2]}-{d1[1]}-{d1[0]}"
            
            if len(dates) > 1:
                # Odatda oxirgi sana shartnoma tugash sanasi bo'ladi
                d2 = dates[-1].split('.')
                end_date = f"{d2[2]}-{d2[1]}-{d2[0]}"
        
        # 3. Summani qidirish (Juda ko'p variantlar bo'lishi mumkin, eng kattasini olish mantiqliroq yoki so'z izlash)
        # Hozircha matn ichidan barcha raqamlarni olib (protsent bo'lmagan) eng katta ehtimolli summani topish
        amounts = re.findall(r'(\d{1,3}(?:[ \.,]\d{3})*(?:[ \.,]\d{2})?)\s*(?:so\'m|сум|sum|uzs)', clean_text, re.IGNORECASE)
        total_amount = ""
        if amounts:
            # Barcha bo'shliq va vergullarni olib tashlab raqamga o'giramiz
            clean_amounts = [float(re.sub(r'[ \.,]', '', a)) for a in amounts]
            total_amount = max(clean_amounts) if clean_amounts else ""

        result = {
            'status': 'success',
            'data': {
                'contract_number': contract_number,
                'start_date': start_date,
                'end_date': end_date,
                'total_amount': total_amount,
                'extracted_text_preview': clean_text[:200] + "..." # Frontendda izohga qo'shish uchun
            },
            'is_json': True
        }
        
        print(json.dumps(result))

    except Exception as e:
        print(json.dumps({
            'status': 'error',
            'message': str(e)
        }))

if __name__ == '__main__':
    if len(sys.argv) > 1:
        parse_pdf(sys.argv[1])
    else:
        print(json.dumps({'status': 'error', 'message': 'No file path provided'}))
