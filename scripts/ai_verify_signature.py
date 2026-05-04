import sys
import json
import cv2
import numpy as np
import os
import fitz  # PyMuPDF

def verify_document(file_path):
    try:
        images = []
        if file_path.lower().endswith('.pdf'):
            doc = fitz.open(file_path)
            # Process only the last page usually where signatures are
            page = doc[-1]
            pix = page.get_pixmap(matrix=fitz.Matrix(2, 2))
            img_data = pix.tobytes("png")
            nparr = np.frombuffer(img_data, np.uint8)
            img = cv2.imdecode(nparr, cv2.IMREAD_COLOR)
            images.append(img)
            doc.close()
        else:
            img = cv2.imread(file_path)
            if img is not None:
                images.append(img)
            else:
                raise Exception("Could not read image file")

        # Assume we only look at the last image/page
        img = images[0]
        h, w = img.shape[:2]
        
        # We focus on the bottom half of the page for signatures and seals
        bottom_half = img[int(h*0.5):h, 0:w]

        # Convert to HSV to detect blue/purple ink for seal
        hsv = cv2.cvtColor(bottom_half, cv2.cvtColor(img, cv2.COLOR_BGR2HSV) if False else cv2.COLOR_BGR2HSV)
        
        # Define range for blue/purple ink
        lower_blue = np.array([100, 50, 50])
        upper_blue = np.array([160, 255, 255])
        
        mask_blue = cv2.inRange(hsv, lower_blue, upper_blue)
        blue_pixels = cv2.countNonZero(mask_blue)

        # Detect any dark strokes for signature (black or dark blue)
        gray = cv2.cvtColor(bottom_half, cv2.COLOR_BGR2GRAY)
        # Threshold to get dark pixels
        _, dark_mask = cv2.threshold(gray, 100, 255, cv2.THRESH_BINARY_INV)
        dark_pixels = cv2.countNonZero(dark_mask)

        seal_detected = blue_pixels > 1000  # Arbitrary threshold
        signature_detected = dark_pixels > 3000

        seal_confidence = min(99.0, (blue_pixels / 5000.0) * 100)
        sig_confidence = min(99.0, (dark_pixels / 10000.0) * 100)

        status = 'passed' if (seal_detected and signature_detected) else 'failed'
        
        error_msg = ""
        if not seal_detected and not signature_detected:
            error_msg = "Imzo va kompaniya muhri topilmadi."
        elif not seal_detected:
            error_msg = "Kompaniya muhri (ko'k/binafsha rang) topilmadi."
        elif not signature_detected:
            error_msg = "Imzo topilmadi."

        result = {
            'status': status,
            'logs': {
                'seal_detection': {
                    'detected': bool(seal_detected),
                    'confidence': round(seal_confidence, 2),
                    'error': error_msg if not seal_detected else "Muhr tasdiqlandi."
                },
                'signature_detection': {
                    'buyer_signature': bool(signature_detected),
                    'confidence': round(sig_confidence, 2),
                    'error': error_msg if not signature_detected else "Imzo tasdiqlandi."
                },
                'final_decision': 'APPROVED' if status == 'passed' else 'REJECTED'
            }
        }
        
        print(json.dumps(result))

    except Exception as e:
        print(json.dumps({
            'status': 'error',
            'logs': {
                'seal_detection': {'detected': False, 'confidence': 0, 'error': str(e)},
                'signature_detection': {'buyer_signature': False, 'confidence': 0, 'error': str(e)},
                'final_decision': 'ERROR'
            }
        }))

if __name__ == '__main__':
    if len(sys.argv) > 1:
        verify_document(sys.argv[1])
    else:
        print(json.dumps({'status': 'error', 'error': 'No file path provided'}))
