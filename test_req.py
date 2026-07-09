import urllib.request
import json

try:
    req = urllib.request.Request('http://127.0.0.1:8000/admin/fixed-assets')
    req.add_header('X-Inertia', 'true')
    req.add_header('X-Inertia-Version', '1')
    response = urllib.request.urlopen(req)
    data = json.loads(response.read().decode('utf-8'))
    print("Component:", data.get('component'))
except Exception as e:
    print("Error:", str(e))
