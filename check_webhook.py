import urllib.request
import json

tokens = {
    'Hayot_PIN': '8710373510:AAGPFPPB89v3D6AKN7XJ0GgnPwLMBMD9aJA',
    'Hayot_BACKUP': '8710373510:AAGPFPPB89v3D6AKN7XJ0GgnPwLMBMD9aJA',
    'Hayot_FINANCE': '8589732003:AAGm3fIO7RigsrykRHTvc4BTxFz3wM6tB0g',
    'Sherdor_PIN': '8738481163:AAHObhjMQhF6suFnFvF0gF0SsQ--mdKcCMU',
    'Sherdor_BACKUP': '8516861678:AAEPUEQF-7KxFPhwl45gsSrVvl73-wv_Jm4',
    'Sherdor_FINANCE': '8768661709:AAEADA-Mwu2vE4kIzrMp-lSRgGqx61Wu_lU'
}

for name, token in tokens.items():
    url = f'https://api.telegram.org/bot{token}/getWebhookInfo'
    try:
        req = urllib.request.Request(url)
        with urllib.request.urlopen(req) as response:
            data = json.loads(response.read().decode('utf-8'))
            print(f'{name} -> Webhook URL: {data["result"]["url"]}')
    except Exception as e:
        print(f'{name} -> Error: {e}')
