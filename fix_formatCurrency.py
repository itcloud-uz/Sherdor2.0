import codecs

path = "resources/js/Pages/Admin/Finance/Index.vue"
with codecs.open(path, 'r', 'utf-8') as f:
    content = f.read()

content = content.replace("formatCurrency", "formatMoney")

with codecs.open(path, 'w', 'utf-8') as f:
    f.write(content)
print("Replaced formatCurrency with formatMoney successfully!")
