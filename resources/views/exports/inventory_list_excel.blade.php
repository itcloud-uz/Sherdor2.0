<table>
    <thead>
        <tr>
            <th style="font-weight: bold; background-color: #f3f4f6;">№</th>
            <th style="font-weight: bold; background-color: #f3f4f6;">Mahsulot nomi</th>
            <th style="font-weight: bold; background-color: #f3f4f6;">Kategoriya</th>
            <th style="font-weight: bold; background-color: #f3f4f6;">Umumiy miqdor</th>
            <th style="font-weight: bold; background-color: #f3f4f6;">O'lchov birligi</th>
            <th style="font-weight: bold; background-color: #f3f4f6;">Birlik narxi (so'm)</th>
            <th style="font-weight: bold; background-color: #f3f4f6;">Jami summa (so'm)</th>
            <th style="font-weight: bold; background-color: #f3f4f6;">Min. miqdor</th>
            <th style="font-weight: bold; background-color: #f3f4f6;">Ta'minotchi</th>
            <th style="font-weight: bold; background-color: #f3f4f6;">Holati</th>
        </tr>
    </thead>
    <tbody>
        @foreach($inventory as $index => $item)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $item->name }}</td>
                <td>{{ $item->category ?: '—' }}</td>
                <td>{{ $item->quantity }}</td>
                <td>{{ $item->unit }}</td>
                <td>{{ $item->average_cost ?: $item->price }}</td>
                <td>{{ $item->quantity * ($item->average_cost ?: $item->price) }}</td>
                <td>{{ $item->min_quantity }}</td>
                <td>{{ $item->supplier_name ?: '—' }}</td>
                <td>{{ $item->status ?: 'faol' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
