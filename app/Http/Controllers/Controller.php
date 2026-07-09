<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

abstract class Controller
{
    /**
     * Resolve the view to render for an admin page.
     *
     * The super admin gets the redesigned console: if a matching view exists
     * under admin/pro/… it wins. Everyone else (VAs, leads agents) keeps the
     * original view. Same controller, same data — only the template differs.
     */
    protected function adminView(string $view): string
    {
        $me = Auth::guard('admin')->user();

        if ($me && $me->isSuper()) {
            $pro = Str::replaceFirst('admin.', 'admin.pro.', $view);
            if (view()->exists($pro)) {
                return $pro;
            }
        }

        return $view;
    }

    /** True when adminView() would hand this request to the pro console. */
    protected function isProView(string $view): bool
    {
        return $this->adminView($view) !== $view;
    }

    /**
     * Paginate an already-sorted collection without going back to the database.
     * Used where ordering depends on computed accessors, so the sort has to
     * happen in PHP but we still want a pager.
     */
    protected function paginateCollection(Collection $items, int $perPage, Request $request): LengthAwarePaginator
    {
        $page = LengthAwarePaginator::resolveCurrentPage();

        return new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );
    }
}
