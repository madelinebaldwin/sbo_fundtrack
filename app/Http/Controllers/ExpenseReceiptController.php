<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExpenseReceiptController extends Controller
{
    public function __invoke(Expense $expense): StreamedResponse|Response
    {
        abort_if($expense->receipt_path === null || ! Storage::disk()->exists($expense->receipt_path), 404);

        return Storage::disk()->download($expense->receipt_path);
    }
}
