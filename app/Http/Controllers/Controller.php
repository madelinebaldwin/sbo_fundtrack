<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\Pagination\LengthAwarePaginator as LengthAwarePaginatorContract;
use Illuminate\Http\RedirectResponse;

abstract class Controller
{
    protected function redirectIfPaginationPageIsUnavailable(LengthAwarePaginatorContract $paginator): ?RedirectResponse
    {
        if ($paginator->currentPage() <= $paginator->lastPage()) {
            return null;
        }

        return redirect()->to($paginator->url($paginator->lastPage()));
    }
}
