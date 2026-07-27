<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('money_transfer_id')->nullable()->after('invoice_id');
            $table->foreign('money_transfer_id')->references('id')->on('money_transfers')->onDelete('cascade');
        });

        // Link existing transfers
        $transfers = \DB::table('money_transfers')->get();
        foreach ($transfers as $t) {
            \DB::table('transactions')
                ->where('notes', 'like', "%O'tkazma] ID: #{$t->id}%")
                ->update(['money_transfer_id' => $t->id]);
        }
    }

    public function down()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['money_transfer_id']);
            $table->dropColumn('money_transfer_id');
        });
    }
};
