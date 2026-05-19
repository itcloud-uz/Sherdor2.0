with open(r'd:\Sherdor 2.0\resources\js\Pages\Admin\HR\Index.vue', 'r', encoding='utf-8') as f:
    text = f.read()

text = text.replace('\\"Yangi Ishchi Qo\'shish\\"', '"Yangi Ishchi Qo\'shish"')
text = text.replace('\\"+ Texnika Egasi Qo\'shish\\"', '"+ Texnika Egasi Qo\'shish"')
text = text.replace('\\"Qo\'shish\\"', '"Qo\'shish"')
text = text.replace('\\"Yangi Texnika Qo\'shish\\"', '"Yangi Texnika Qo\'shish"')
text = text.replace('\\"To\'langan\\"', '"To\'langan"')
text = text.replace('\\"To\'lanmagan\\"', '"To\'lanmagan"')

with open(r'd:\Sherdor 2.0\resources\js\Pages\Admin\HR\Index.vue', 'w', encoding='utf-8') as f:
    f.write(text)

print('Cleaned up all \\" occurrences')
