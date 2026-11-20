<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockLot extends Model
{
    protected $fillable = ['stock_item_id', 'goods_receipt_line_id', 'lot_number', 'expires_on', 'quantity_remaining'];

    protected $casts = ['expires_on' => 'date', 'quantity_remaining' => 'decimal:3'];
}
