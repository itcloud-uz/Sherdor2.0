import sys
import json
import cv2
import numpy as np
import os
import fitz  # PyMuPDF
from pyzbar.pyzbar import decode

def scan_pdf(file_path):
    try:
        if not os.path.exists(file_path):
            raise Exception("File does not exist")
            
        doc = fitz.open(file_path)
        qr_data = None
        
        detector = cv2.QRCodeDetector()
        
        # Iterate through pages to find a QR code
        for page_num in range(len(doc)):
            page = doc[page_num]
            # Render page to an image
            pix = page.get_pixmap(matrix=fitz.Matrix(2, 2))
            img_data = pix.tobytes("png")
            nparr = np.frombuffer(img_data, np.uint8)
            img = cv2.imdecode(nparr, cv2.IMREAD_COLOR)
            
            # Try to decode QR code using pyzbar (usually more robust than cv2.QRCodeDetector)
            try:
                decoded_objects = decode(img)
                for obj in decoded_objects:
                    qr_data = obj.data.decode('utf-8')
                    if qr_data:
                        break
            except Exception:
                pass
                
            # Fallback to OpenCV QR detector
            if not qr_data:
                data, bbox, _ = detector.detectAndDecode(img)
                if data:
                    qr_data = data
            
            if qr_data:
                break
                
        doc.close()
        
        if qr_data:
            # Try to parse it as JSON if possible, otherwise return raw text
            try:
                parsed_data = json.loads(qr_data)
                result = {
                    'status': 'success',
                    'data': parsed_data,
                    'is_json': True
                }
            except json.JSONDecodeError:
                result = {
                    'status': 'success',
                    'data': qr_data,
                    'is_json': False
                }
        else:
            result = {
                'status': 'not_found',
                'message': 'No QR code found in the document'
            }
            
        print(json.dumps(result))

    except Exception as e:
        print(json.dumps({
            'status': 'error',
            'message': str(e)
        }))

if __name__ == '__main__':
    if len(sys.argv) > 1:
        scan_pdf(sys.argv[1])
    else:
        print(json.dumps({'status': 'error', 'message': 'No file path provided'}))
