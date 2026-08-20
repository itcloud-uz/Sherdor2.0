import sys

file_path = 'resources/views/exports/brigade_ledger_excel.blade.php'
with open(file_path, 'r', encoding='utf-8') as f:
    content = f.read()

target1 = "            <th colspan=\"2\" style=\"font-weight: bold; border: 1px solid #000000; text-align: center;\">TO'LANDI / USHLANDI</th>\n            <th rowspan=\"2\" style=\"font-weight: bold; border: 1px solid #000000; text-align: center; vertical-align: middle;\">Qoldiq</th>"
replacement1 = "            <th colspan=\"3\" style=\"font-weight: bold; border: 1px solid #000000; text-align: center;\">TO'LANDI / USHLANDI</th>\n            <th rowspan=\"2\" style=\"font-weight: bold; border: 1px solid #000000; text-align: center; vertical-align: middle;\">Qoldiq</th>"

target2 = "            <th style=\"font-weight: bold; border: 1px solid #000000; text-align: center;\">To'landi / Avans</th>\n            <th style=\"font-weight: bold; border: 1px solid #000000; text-align: center;\">Ushlanma (Jarima)</th>"
replacement2 = "            <th style=\"font-weight: bold; border: 1px solid #000000; text-align: center;\">To'landi / Avans</th>\n            <th style=\"font-weight: bold; border: 1px solid #000000; text-align: center;\">To'lov turi</th>\n            <th style=\"font-weight: bold; border: 1px solid #000000; text-align: center;\">Ushlanma (Jarima)</th>"

target3 = "                    <td style=\"border: 1px solid #000000; text-align: right;\"></td>\n                    <td style=\"border: 1px solid #000000; text-align: right;\">{{ \ > 0 ? number_format(\, 0, '.', ' ') : '' }}</td>"
replacement3 = "                    <td style=\"border: 1px solid #000000; text-align: right;\"></td>\n                    <td style=\"border: 1px solid #000000; text-align: right;\"></td>\n                    <td style=\"border: 1px solid #000000; text-align: right;\">{{ \ > 0 ? number_format(\, 0, '.', ' ') : '' }}</td>"

target4 = "                    <td style=\"border: 1px solid #000000; text-align: right;\">{{ in_array(\->type, ['payment', 'advance_payment']) ? number_format(\->amount, 0, '.', ' ') : '' }}</td>\n                    <td style=\"border: 1px solid #000000; text-align: right;\">{{ \->type === 'penalty' ? number_format(\->amount, 0, '.', ' ') : '' }}</td>"
replacement4 = "                    <td style=\"border: 1px solid #000000; text-align: right;\">{{ in_array(\->type, ['payment', 'advance_payment']) ? number_format(\->amount, 0, '.', ' ') : '' }}</td>\n                    <td style=\"border: 1px solid #000000; text-align: center;\">\n                        @if(in_array(\->type, ['payment', 'advance_payment']) && \->financeTransaction)\n                            @if(\->financeTransaction->payment_method == 'cash') Naqd\n                            @elseif(\->financeTransaction->payment_method == 'card') Plastik\n                            @elseif(\->financeTransaction->payment_method == 'bank_transfer') Bank\n                            @else {{ ucfirst(\->financeTransaction->payment_method) }}\n                            @endif\n                        @endif\n                    </td>\n                    <td style=\"border: 1px solid #000000; text-align: right;\">{{ \->type === 'penalty' ? number_format(\->amount, 0, '.', ' ') : '' }}</td>"

target5 = "            <td style=\"font-weight: bold; border: 1px solid #000000; text-align: right;\">{{ number_format(\, 0, '.', ' ') }}</td>\n            <td style=\"font-weight: bold; border: 1px solid #000000; text-align: right;\">{{ number_format(\, 0, '.', ' ') }}</td>"
replacement5 = "            <td style=\"font-weight: bold; border: 1px solid #000000; text-align: right;\">{{ number_format(\, 0, '.', ' ') }}</td>\n            <td style=\"font-weight: bold; border: 1px solid #000000; text-align: right;\"></td>\n            <td style=\"font-weight: bold; border: 1px solid #000000; text-align: right;\">{{ number_format(\, 0, '.', ' ') }}</td>"

target6 = "        <tr>\n            <th colspan=\"9\""
replacement6 = "        <tr>\n            <th colspan=\"10\""

target7 = "            <td colspan=\"3\" style=\"font-weight: bold; border: 1px solid #000000; text-align: center;\">JAMI</td>"
replacement7 = "            <td colspan=\"3\" style=\"font-weight: bold; border: 1px solid #000000; text-align: center;\">JAMI</td>"


content = content.replace(target1, replacement1)
content = content.replace(target2, replacement2)
content = content.replace(target3, replacement3)
content = content.replace(target4, replacement4)
content = content.replace(target5, replacement5)
content = content.replace(target6, replacement6)

with open(file_path, 'w', encoding='utf-8') as f:
    f.write(content)
print('Success Blade Export')
