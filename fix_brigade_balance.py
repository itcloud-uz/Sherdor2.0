import codecs

path = "resources/js/Pages/Admin/HR/QurilishIshHaqi.vue"
with codecs.open(path, 'r', 'utf-8') as f:
    content = f.read()

# Replace calculatedBalance with balance in template
content = content.replace("brigade.calculatedBalance > 0", "brigade.balance > 0")
content = content.replace("formatMoney(brigade.calculatedBalance)", "formatMoney(brigade.balance)")
content = content.replace("brigade.calculatedBalance < 0", "brigade.balance < 0")
content = content.replace("formatMoney(Math.abs(brigade.calculatedBalance))", "formatMoney(Math.abs(brigade.balance))")

# Fix globalStats
content = content.replace("totalBalance += (worksPrice - paid);", "totalBalance += Number(brigade.balance) || 0;")

with codecs.open(path, 'w', 'utf-8') as f:
    f.write(content)

print("Balance logic fixed")
