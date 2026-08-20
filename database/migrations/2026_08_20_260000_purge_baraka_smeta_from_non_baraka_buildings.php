<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\SmetaItem;
use App\Models\Building;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Delete any smeta items attached to non-Baraka buildings that have Baraka notes/names
        $nonBarakaBuildingIds = Building::where('name', 'NOT LIKE', '%Baraka%')->pluck('id');

        SmetaItem::whereIn('building_id', $nonBarakaBuildingIds)
            ->where(function($q) {
                $q->where('notes', 'LIKE', '%Ekspertiza%')
                  ->orWhere('notes', 'LIKE', '%QQS%')
                  ->orWhere('notes', 'LIKE', '%asbob-uskunalar%')
                  ->orWhere('item_name', 'LIKE', '%Blok %')
                  ->orWhere('item_name', 'LIKE', '%Yer osti Avtoturargoh%');
            })
            ->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
    }
};
