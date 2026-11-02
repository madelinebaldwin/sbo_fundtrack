<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['event_id', 'expense_category_id', 'created_by', 'reviewed_by', 'submission_key', 'description', 'quantity', 'unit', 'unit_price', 'amount', 'transaction_date', 'receipt_path', 'notes', 'status'])]
class Expense extends Model
{
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'amount' => 'decimal:2',
            'transaction_date' => 'date',
        ];
    }

    public function formattedQuantity(): string
    {
        return rtrim(rtrim($this->quantity, '0'), '.');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
