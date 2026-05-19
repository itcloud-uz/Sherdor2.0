with open(r'd:\Sherdor 2.0\resources\js\Pages\Admin\HR\Index.vue', 'r', encoding='utf-8') as f:
    text = f.read()

text = text.replace("confirm('Brigadani o'chirmoqchimisiz?')", "confirm(\"Brigadani o'chirmoqchimisiz?\")")
text = text.replace("confirm('Ishchini o'chirmoqchimisiz?')", "confirm(\"Ishchini o'chirmoqchimisiz?\")")
text = text.replace("confirm('Ushbu texnika egasini ro'yxatdan o'chirasizmi?')", "confirm(\"Ushbu texnika egasini ro'yxatdan o'chirasizmi?\")")
text = text.replace("confirm('Ushbu texnikani haqiqatan ham o'chirasizmi?')", "confirm(\"Ushbu texnikani haqiqatan ham o'chirasizmi?\")")
text = text.replace("alert('Xatolik yuz berdi. Iltimos qaytadan urinib ko'ring.')", "alert(\"Xatolik yuz berdi. Iltimos qaytadan urinib ko'ring.\")")

with open(r'd:\Sherdor 2.0\resources\js\Pages\Admin\HR\Index.vue', 'w', encoding='utf-8') as f:
    f.write(text)

print('Fixed script confirmations')
