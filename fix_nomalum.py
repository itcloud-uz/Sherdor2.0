with open(r'd:\Sherdor 2.0\resources\js\Pages\Admin\HR\Index.vue', 'r', encoding='utf-8') as f:
    text = f.read()

text = text.replace("'Noma'lum'", '"Noma\'lum"')
text = text.replace("'Noma'lum Shaxs'", '"Noma\'lum Shaxs"')

with open(r'd:\Sherdor 2.0\resources\js\Pages\Admin\HR\Index.vue', 'w', encoding='utf-8') as f:
    f.write(text)

print('Fixed all Noma\\'lum')
