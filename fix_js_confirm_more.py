with open(r'd:\Sherdor 2.0\resources\js\Pages\Admin\HR\Index.vue', 'r', encoding='utf-8') as f:
    text = f.read()

text = text.replace("confirm('Texnika egasini o'chirmoqchimisiz?')", "confirm(\"Texnika egasini o'chirmoqchimisiz?\")")
text = text.replace("confirm('Haqiqatan ham ushbu foydalanuvchini o'chirmoqchimisiz?')", "confirm(\"Haqiqatan ham ushbu foydalanuvchini o'chirmoqchimisiz?\")")
text = text.replace("confirm('Haqiqatan ham ushbu xodimni o'chirmoqchimisiz?')", "confirm(\"Haqiqatan ham ushbu xodimni o'chirmoqchimisiz?\")")

with open(r'd:\Sherdor 2.0\resources\js\Pages\Admin\HR\Index.vue', 'w', encoding='utf-8') as f:
    f.write(text)

print('Fixed MORE script confirmations')
