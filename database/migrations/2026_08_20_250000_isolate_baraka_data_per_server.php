<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Models\Building;
use App\Models\SmetaItem;
use App\Models\Unit;
use App\Models\Floor;
use App\Models\Podez;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $basePath = base_path();
        
        // Detect if this is Baraka Residence server
        $isBarakaServer = str_contains(strtolower($basePath), 'baraka') 
            || (file_exists('/var/www/barakaresidence.uz') && !file_exists('/var/www/sellerarp') && !file_exists('/home/itcloud/Sherdor2.0'));

        // On local dev machine, check if database name or environment specifies Baraka
        if (app()->environment('local')) {
            $isBarakaServer = true;
        }

        if (!$isBarakaServer) {
            // THIS IS SHERDOR OR HAYOT HOUSE SERVER!
            // Clean up any Baraka Residence buildings and smeta items that were accidentally seeded here!
            
            // 1. Delete SmetaItems created for Baraka
            SmetaItem::where('category_name', 'Qurilish-montaj ishlari (QMI)')
                ->where('item_name', 'LIKE', '%Blok A-%')
                ->delete();

            SmetaItem::where('item_name', 'LIKE', '%QQS%')
                ->where('notes', 'LIKE', '%Rasmiy tasdiqlangan QQS%')
                ->delete();

            SmetaItem::where('item_name', 'LIKE', '%Muhandislik asbob-uskunalari%')
                ->where('notes', 'LIKE', '%Tasdiqlangan asbob-uskunalar%')
                ->delete();

            // 2. Delete Baraka Residence buildings, floors, podezes, and units from Sherdor/Hayot servers
            $barakaBuildings = Building::where('name', 'BARAKA RESIDENCE')->orWhere('name', 'LIKE', '%Baraka%')->get();
            foreach ($barakaBuildings as $b) {
                $floorIds = Floor::where('building_id', $b->id)->pluck('id');
                Unit::whereIn('floor_id', $floorIds)->delete();
                Floor::where('building_id', $b->id)->delete();
                Podez::where('building_id', $b->id)->delete();
                SmetaItem::where('building_id', $b->id)->delete();
                $b->delete();
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
    }
};
