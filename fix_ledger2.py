import sys

file_path = 'resources/views/exports/client_ledger.blade.php'
with open(file_path, 'r', encoding='utf-8') as f:
    content = f.read()

replacement = '''
    <!-- MAIN SCHEDULE & PAYMENTS TABLE -->
    @if(->payment_type === 'full')
        <div style="text-align: center; margin-top: 20px; font-size: 14px; font-weight: bold;">
            Ushbu shartnoma bo'yicha to'lov to'liq (bir martalik) belgilangan. Umumiy summa: {{ number_format(->total_amount, 0, ',', ' ') }} so'm.
        </div>
        <br>
        <table class="ledger-table">
            <thead>
                <tr>
                    <th colspan="3" style="width: 50%;">Haqiqatda qilingan to'lov</th>
                    <th colspan="2" style="width: 30%;">Mijozga qaytarilgan</th>
                    <th rowspan="2" style="width: 20%;">Qoldiq</th>
                </tr>
                <tr>
                    <th>Sana</th>
                    <th>So'mma</th>
                    <th>Turi</th>
                    <th>Sana</th>
                    <th>So'mma</th>
                </tr>
            </thead>
            <tbody>
                @php 
                     = ;
                @endphp
                @foreach( as  => )
                    @php
                         -= ->amount;
                         = ->payment_method === 'bank_transfer' ? 'Bank' : (->payment_method === 'card' ? 'Plastik' : 'Naqt');
                    @endphp
                    <tr>
                        <td class="text-center">{{ \Carbon\Carbon::parse(->transaction_date ?: ->created_at)->format('d.m.Y') }}</td>
                        <td class="text-right font-bold">{{ number_format(->amount, 0, ',', ' ') }}</td>
                        <td class="text-center">{{  }}</td>
                        <td class="text-center"></td>
                        <td class="text-right"></td>
                        <td class="text-right font-bold">{{ number_format(max(0, ), 0, ',', ' ') }}</td>
                    </tr>
                @endforeach
                @foreach( as  => )
                    @php
                         += ->amount;
                    @endphp
                    <tr>
                        <td class="text-center"></td>
                        <td class="text-right"></td>
                        <td class="text-center"></td>
                        <td class="text-center">{{ \Carbon\Carbon::parse(->transaction_date ?: ->created_at)->format('d.m.Y') }}</td>
                        <td class="text-right font-bold">{{ number_format(->amount, 0, ',', ' ') }}</td>
                        <td class="text-right font-bold">{{ number_format(max(0, ), 0, ',', ' ') }}</td>
                    </tr>
                @endforeach
                @if(->count() === 0 && ->count() === 0)
                    <tr>
                        <td colspan="6" class="text-center">To'lovlar mavjud emas</td>
                    </tr>
                @endif
            </tbody>
        </table>
    @else
        <table class="ledger-table">
'''

target = '''    <!-- MAIN SCHEDULE & PAYMENTS TABLE -->
    <table class="ledger-table">'''

if target in content:
    content = content.replace(target, replacement)
    content = content.replace('</table>\n\n    <!-- SUMMARY TOTALS BOX -->', '</table>\n    @endif\n\n    <!-- SUMMARY TOTALS BOX -->')
    with open(file_path, 'w', encoding='utf-8') as f:
        f.write(content)
    print("Updated client_ledger.blade.php")
else:
    print("Target not found.")

