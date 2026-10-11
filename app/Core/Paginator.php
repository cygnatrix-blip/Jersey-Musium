<?php

declare(strict_types=1);

namespace App\Core;

final class Paginator
{
    public readonly int $lastPage;

    public function __construct(
        public readonly array $items,
        public readonly int $total,
        public readonly int $perPage,
        public readonly int $page,
    ) {
        $this->lastPage = max(1, (int) ceil($total / max(1, $perPage)));
    }

    /**
     * Paginate a SELECT. $sql must not include LIMIT; $countSql defaults to wrapping $sql.
     */
    public static function query(string $sql, array $params, int $perPage, ?int $page = null, ?string $countSql = null): self
    {
        $page = max(1, $page ?? (int) request()->query('page', 1));
        $total = (int) db()->scalar($countSql ?? "SELECT COUNT(*) FROM ($sql) AS t", $params);
        $offset = ($page - 1) * $perPage;
        $items = db()->select($sql . " LIMIT $perPage OFFSET $offset", $params);
        return new self($items, $total, $perPage, $page);
    }

    public function from(): int
    {
        return $this->total ? ($this->page - 1) * $this->perPage + 1 : 0;
    }

    public function to(): int
    {
        return min($this->total, $this->page * $this->perPage);
    }

    public function url(int $page): string
    {
        $q = request()->queryAll();
        $q['page'] = $page;
        if ($page === 1) {
            unset($q['page']);
        }
        return request()->path() . ($q ? '?' . http_build_query($q) : '');
    }

    public function links(): string
    {
        if ($this->lastPage <= 1) {
            return '';
        }
        $html = '<nav class="pagination" aria-label="Pagination">';
        if ($this->page > 1) {
            $html .= '<a href="' . e($this->url($this->page - 1)) . '" rel="prev">&lsaquo; Prev</a>';
        }
        $start = max(1, $this->page - 2);
        $end = min($this->lastPage, $this->page + 2);
        if ($start > 1) {
            $html .= '<a href="' . e($this->url(1)) . '">1</a>' . ($start > 2 ? '<span class="gap">…</span>' : '');
        }
        for ($i = $start; $i <= $end; $i++) {
            $html .= $i === $this->page
                ? '<span class="current" aria-current="page">' . $i . '</span>'
                : '<a href="' . e($this->url($i)) . '">' . $i . '</a>';
        }
        if ($end < $this->lastPage) {
            $html .= ($end < $this->lastPage - 1 ? '<span class="gap">…</span>' : '') . '<a href="' . e($this->url($this->lastPage)) . '">' . $this->lastPage . '</a>';
        }
        if ($this->page < $this->lastPage) {
            $html .= '<a href="' . e($this->url($this->page + 1)) . '" rel="next">Next &rsaquo;</a>';
        }
        return $html . '</nav>';
    }
}
