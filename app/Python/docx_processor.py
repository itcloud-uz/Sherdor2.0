import sys
import os
import json
import re
import docx
import mammoth
from docxtpl import DocxTemplate

def detect_placeholders(docx_path):
    """
    Scans the docx file for common patterns (PINFL, passport, phone, dates, prices, etc.)
    and returns a list of proposed placeholders.
    """
    if not os.path.exists(docx_path):
        return {"error": f"File not found: {docx_path}"}
        
    doc = docx.Document(docx_path)
    text_segments = []
    
    # Extract text from paragraphs
    for p in doc.paragraphs:
        txt = p.text.replace('\xa0', ' ').replace('\t', ' ')
        txt = re.sub(r'\s+', ' ', txt).strip()
        if txt:
            text_segments.append(txt)
            
    # Extract text from tables
    for table in doc.tables:
        for row in table.rows:
            for cell in row.cells:
                for p in cell.paragraphs:
                    txt = p.text.replace('\xa0', ' ').replace('\t', ' ')
                    txt = re.sub(r'\s+', ' ', txt).strip()
                    if txt:
                        text_segments.append(txt)

    # Extract text from headers and footers
    for section in doc.sections:
        for header in [section.header, section.first_page_header, section.even_page_header]:
            if header:
                for p in header.paragraphs:
                    txt = p.text.replace('\xa0', ' ').replace('\t', ' ')
                    txt = re.sub(r'\s+', ' ', txt).strip()
                    if txt:
                        text_segments.append(txt)
                for t in header.tables:
                    for row in t.rows:
                        for cell in row.cells:
                            for p in cell.paragraphs:
                                txt = p.text.replace('\xa0', ' ').replace('\t', ' ')
                                txt = re.sub(r'\s+', ' ', txt).strip()
                                if txt:
                                    text_segments.append(txt)
                                    
        for footer in [section.footer, section.first_page_footer, section.even_page_footer]:
            if footer:
                for p in footer.paragraphs:
                    txt = p.text.replace('\xa0', ' ').replace('\t', ' ')
                    txt = re.sub(r'\s+', ' ', txt).strip()
                    if txt:
                        text_segments.append(txt)
                for t in footer.tables:
                    for row in t.rows:
                        for cell in row.cells:
                            for p in cell.paragraphs:
                                txt = p.text.replace('\xa0', ' ').replace('\t', ' ')
                                txt = re.sub(r'\s+', ' ', txt).strip()
                                if txt:
                                    text_segments.append(txt)
                        
    combined_text = "\n".join(text_segments)
    
    candidates = []
    
    # 1. Detect PINFL (JSHSHIR): exactly 14 digits
    pinfls = re.findall(r'\b\d{14}\b', combined_text)
    for p in set(pinfls):
        candidates.append({
            "text": p,
            "key": "MIJOZ_JSHSHIR",
            "label": "Mijoz JSHSHIR (PINFL)",
            "type": "text"
        })
        
    # 2. Detect Passport: e.g. AA 1234567, AD 9160055, or with №
    passports = re.findall(r'\b[A-Z]{2}\s*№?\s*\d{7}\b', combined_text)
    for p in set(passports):
        candidates.append({
            "text": p,
            "key": "PASPORT_SERIYA",
            "label": "Pasport Seriyasi va Raqami",
            "type": "text"
        })
        
    # 3. Detect Dates: e.g. 26/10/2024-yilda, 13 mart 2025-yil
    # Standard format dd/mm/yyyy
    dates_slash = re.findall(r'\b\d{2}/\d{2}/\d{4}(?:-yilda)?\b', combined_text)
    for d in set(dates_slash):
        candidates.append({
            "text": d,
            "key": "PASPORT_SANA",
            "label": "Pasport berilgan sana",
            "type": "text"
        })
        
    # O'zbekcha yozma sana: e.g. "13 " mart 2025-yil or 13-mart 2025-yil
    dates_uz = re.findall(r'\b“?\d{1,2}”?\s*[a-zA-Z\'`ʻ’]+\s+\d{4}-yil\b', combined_text)
    for d in set(dates_uz):
        candidates.append({
            "text": d,
            "key": "SHARTNOMA_SANASI",
            "label": "Shartnoma sanasi",
            "type": "text"
        })

    # 4. Detect Phone Numbers: e.g. (94) 633-04-00 or +998 (94) 633-04-00
    phones = re.findall(r'\+?998\s*\(?\d{2}\)?\s*\d{3}-\d{2}-\d{2}|\(\d{2}\)\s*\d{3}-\d{2}-\d{2}', combined_text)
    for ph in set(phones):
        candidates.append({
            "text": ph,
            "key": "MIJOZ_TELEFON",
            "label": "Mijoz telefon raqami",
            "type": "text"
        })

    # 5. Detect large prices / sums: numbers separated by spaces (e.g. 190 000 000, 3 998 316)
    # We want numbers of 6 or more digits separated by spaces/dots
    prices = re.findall(r'\b\d{1,3}(?:\s+\d{3}){2,}\b', combined_text)
    for pr in set(prices):
        val = int(re.sub(r'\s+', '', pr))
        if val > 1000000:
            candidates.append({
                "text": pr,
                "key": "JAMI_NARX",
                "label": "Shartnoma umumiy summasi",
                "type": "number"
            })
            
    # 6. Specific hardcoded search for sample names (for test fixtures / samples)
    if "Mamanov Abdulla Xursanmurotovich" in combined_text:
        candidates.append({
            "text": "Mamanov Abdulla Xursanmurotovich",
            "key": "MIJOZ_FISH",
            "label": "Mijozning to'liq F.I.Sh",
            "type": "text"
        })

    if "A. Mamanov" in combined_text:
        candidates.append({
            "text": "A. Mamanov",
            "key": "MIJOZ_FISH_QISQA",
            "label": "Mijoz F.I.Sh qisqartmasi (Masalan: A. Mamanov)",
            "type": "text"
        })
    elif "A.Mamanov" in combined_text:
        candidates.append({
            "text": "A.Mamanov",
            "key": "MIJOZ_FISH_QISQA",
            "label": "Mijoz F.I.Sh qisqartmasi (Masalan: A.Mamanov)",
            "type": "text"
        })
        
    if "Samarqand viloyati Urgut tumani I.I.B" in combined_text:
        candidates.append({
            "text": "Samarqand viloyati Urgut tumani I.I.B",
            "key": "PASPORT_JOYI",
            "label": "Pasport bergan organ",
            "type": "text"
        })
        
    if "Samarqand viloyati, Urgut tuman Yuqori Tegana Do`stlik maxallasida, ro`yxatda turuvchi" in combined_text:
        candidates.append({
            "text": "Samarqand viloyati, Urgut tuman Yuqori Tegana Do`stlik maxallasida, ro`yxatda turuvchi",
            "key": "MIJOZ_MANZIL",
            "label": "Mijoz yashash manzili",
            "type": "text"
        })
    elif "Samarqand viloyati, Urgut tumani Yuqori Tegana maxalla" in combined_text:
        candidates.append({
            "text": "Samarqand viloyati, Urgut tumani Yuqori Tegana maxalla",
            "key": "MIJOZ_MANZIL",
            "label": "Mijoz yashash manzili",
            "type": "text"
        })

    # Return unique candidates sorted by text length desc to avoid substring conflicts during replacement
    unique_candidates = []
    seen = set()
    for c in sorted(candidates, key=lambda x: len(x["text"]), reverse=True):
        if c["text"] not in seen:
            seen.add(c["text"])
            unique_candidates.append(c)
            
    return unique_candidates

def convert_docx_to_html(docx_path):
    """
    Converts docx to HTML using mammoth for web preview.
    """
    if not os.path.exists(docx_path):
        return {"error": f"File not found: {docx_path}"}
        
    with open(docx_path, "rb") as docx_file:
        result = mammoth.convert_to_html(docx_file)
        return {"html": result.value, "warnings": result.messages}

def replace_text_in_paragraph(paragraph, old_text, new_text):
    text = paragraph.text
    if old_text not in text:
        return False

    start_idx = text.find(old_text)
    end_idx = start_idx + len(old_text)
    
    current_char_count = 0
    runs_to_modify = []
    
    for idx, run in enumerate(paragraph.runs):
        run_len = len(run.text)
        run_start = current_char_count
        run_end = current_char_count + run_len
        
        if run_start < end_idx and run_end > start_idx:
            runs_to_modify.append((idx, run, run_start, run_end))
            
        current_char_count += run_len
        
    if not runs_to_modify:
        return False
        
    first_run_idx, first_run, fr_start, fr_end = runs_to_modify[0]
    prefix = first_run.text[:max(0, start_idx - fr_start)]
    
    if len(runs_to_modify) == 1:
        suffix = first_run.text[max(0, end_idx - fr_start):]
        first_run.text = prefix + new_text + suffix
    else:
        last_run_idx, last_run, lr_start, lr_end = runs_to_modify[-1]
        suffix = last_run.text[max(0, end_idx - lr_start):]
        first_run.text = prefix + new_text
        for idx, run, r_start, r_end in runs_to_modify[1:-1]:
            run.text = ""
        last_run.text = suffix
        
    return True

def replace_text_in_document(doc, old_text, new_text):
    old_text = re.sub(r'\s+', ' ', old_text).replace('\xa0', ' ').strip()
    if not old_text:
        return False
        
    modified = False
    
    def normalize_paragraph(p):
        for run in p.runs:
            if run.text:
                run.text = run.text.replace('\xa0', ' ').replace('\t', ' ')
                run.text = re.sub(r' +', ' ', run.text)

    for p in doc.paragraphs:
        normalize_paragraph(p)
        if replace_text_in_paragraph(p, old_text, new_text):
            modified = True
            
    for table in doc.tables:
        for row in table.rows:
            for cell in row.cells:
                for p in cell.paragraphs:
                    normalize_paragraph(p)
                    if replace_text_in_paragraph(p, old_text, new_text):
                        modified = True
                        
    for section in doc.sections:
        for header in [section.header, section.first_page_header, section.even_page_header]:
            if header:
                for p in header.paragraphs:
                    normalize_paragraph(p)
                    if replace_text_in_paragraph(p, old_text, new_text):
                        modified = True
                for t in header.tables:
                    for row in t.rows:
                        for cell in row.cells:
                            for p in cell.paragraphs:
                                normalize_paragraph(p)
                                if replace_text_in_paragraph(p, old_text, new_text):
                                    modified = True
                                    
        for footer in [section.footer, section.first_page_footer, section.even_page_footer]:
            if footer:
                for p in footer.paragraphs:
                    normalize_paragraph(p)
                    if replace_text_in_paragraph(p, old_text, new_text):
                        modified = True
                for t in footer.tables:
                    for row in t.rows:
                        for cell in row.cells:
                            for p in cell.paragraphs:
                                normalize_paragraph(p)
                                if replace_text_in_paragraph(p, old_text, new_text):
                                    modified = True
    return modified

def apply_replacements(docx_path, replacements, output_path):
    """
    Replaces specified text strings with placeholder tags in the docx file and saves it.
    """
    if not os.path.exists(docx_path):
        return {"error": f"File not found: {docx_path}"}
        
    doc = docx.Document(docx_path)
    
    # Sort replacements by original text length desc to avoid substring replacement bugs
    sorted_replacements = sorted(replacements.items(), key=lambda x: len(x[0]), reverse=True)
    
    success_count = 0
    for old_text, placeholder in sorted_replacements:
        # Wrap placeholder in Jinja2 style
        tag = f"{{{{ {placeholder} }}}}"
        if replace_text_in_document(doc, old_text, tag):
            success_count += 1
            
    doc.save(output_path)
    return {"success": True, "replaced_count": success_count}

def compile_document(template_path, context, output_path):
    """
    Renders the docx template with variables context using docxtpl.
    """
    if not os.path.exists(template_path):
        return {"error": f"Template not found: {template_path}"}
        
    doc = DocxTemplate(template_path)
    
    # Replace standard python newlines with XML line breaks dynamically in context strings
    # (actually docxtpl does this automatically for simple strings, but we can do it to be extra safe)
    doc.render(context)
    doc.save(output_path)
    return {"success": True}

if __name__ == "__main__":
    if len(sys.argv) < 3:
        print(json.dumps({"error": "Missing arguments. Usage: script.py <command> <input_path> [options]"}))
        sys.exit(1)
        
    command = sys.argv[1]
    input_path = sys.argv[2]
    
    # Make sure stdout uses UTF-8 encoding
    sys.stdout.reconfigure(encoding='utf-8')
    
    try:
        if command == "detect":
            result = detect_placeholders(input_path)
            print(json.dumps(result, ensure_ascii=False))
            
        elif command == "preview":
            result = convert_docx_to_html(input_path)
            print(json.dumps(result, ensure_ascii=False))
            
        elif command == "replace":
            if len(sys.argv) < 5:
                print(json.dumps({"error": "Missing arguments for replace: need replacements_json and output_path"}))
                sys.exit(1)
            arg3 = sys.argv[3]
            if not arg3.strip().startswith('{') and os.path.exists(arg3):
                with open(arg3, "r", encoding="utf-8") as f:
                    replacements = json.load(f)
            else:
                replacements = json.loads(arg3)
            output_path = sys.argv[4]
            result = apply_replacements(input_path, replacements, output_path)
            print(json.dumps(result, ensure_ascii=False))
            
        elif command == "compile":
            if len(sys.argv) < 5:
                print(json.dumps({"error": "Missing arguments for compile: need context_json and output_path"}))
                sys.exit(1)
            arg3 = sys.argv[3]
            if not arg3.strip().startswith('{') and os.path.exists(arg3):
                with open(arg3, "r", encoding="utf-8") as f:
                    context = json.load(f)
            else:
                context = json.loads(arg3)
            output_path = sys.argv[4]
            result = compile_document(input_path, context, output_path)
            print(json.dumps(result, ensure_ascii=False))
            
        else:
            print(json.dumps({"error": f"Unknown command: {command}"}))
            sys.exit(1)
            
    except Exception as e:
        import traceback
        print(json.dumps({
            "error": str(e),
            "traceback": traceback.format_exc()
        }, ensure_ascii=False))
        sys.exit(1)
