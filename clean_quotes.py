import re

path = r'd:\Sherdor 2.0\resources\js\Pages\Admin\HR\Index.vue'
with open(path, 'r', encoding='utf-8') as f:
    text = f.read()

text = text.replace(r'\"Qo\'shish\"', '"Qo\'shish"')
text = text.replace(r'\"Yangi Ishchi Qo\'shish\"', '"Yangi Ishchi Qo\'shish"')
text = text.replace(r'\"+ Texnika Egasi Qo\'shish\"', '"+ Texnika Egasi Qo\'shish"')
text = text.replace(r'\"Yangi Texnika Qo\'shish\"', '"Yangi Texnika Qo\'shish"')
text = text.replace(r'\"To\'langan\"', '"To\'langan"')
text = text.replace(r'\"To\'lanmagan\"', '"To\'lanmagan"')

with open(path, 'w', encoding='utf-8') as f:
    f.write(text)

print('Done cleaning quotes')
