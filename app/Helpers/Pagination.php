<?php
namespace App\Helpers;

class Pagination
{
    /**
     * Genera el HTML de la paginacion.
     *
     * @param int    $currentPage   Pagina actual (1-based)
     * @param int    $totalPages    Total de paginas
     * @param int    $totalRecords  Total de registros
     * @param string $baseUrl       URL base (ej: /admin/patients)
     * @param array  $extraParams   Parametros GET adicionales (ej: [search => juan])
     */
    public static function render(
        int    $currentPage,
        int    $totalPages,
        int    $totalRecords,
        string $baseUrl,
        array  $extraParams = []
    ): string {
        if ($totalPages <= 1) {
            return '<div class="pagination-info text-sm text-muted" style="padding: 0.75rem 1rem;">'
                 . 'Total: <strong>' . $totalRecords . '</strong> registro(s)'
                 . '</div>';
        }

        $buildUrl = function(int $page) use ($baseUrl, $extraParams): string {
            $params = array_merge($extraParams, ['page' => $page]);
            return $baseUrl . '?' . http_build_query($params);
        };

        $prevDisabled = $currentPage <= 1;
        $nextDisabled = $currentPage >= $totalPages;

        $delta  = 2;
        $start  = max(1, $currentPage - $delta);
        $end    = min($totalPages, $currentPage + $delta);
        if ($currentPage - $delta < 1) $end   = min($totalPages, $end + ($delta - ($currentPage - 1)));
        if ($currentPage + $delta > $totalPages) $start = max(1, $start - ($delta - ($totalPages - $currentPage)));

        $html = '<div class="pagination-wrapper">';
        $html .= '<span class="text-sm text-muted">Total: <strong>' . $totalRecords . '</strong> registro(s) &mdash; Página <strong>' . $currentPage . '</strong> de <strong>' . $totalPages . '</strong></span>';
        $html .= '<nav class="pagination">';

        if ($prevDisabled) {
            $html .= '<span class="page-btn disabled"><i class="fa-solid fa-chevron-left"></i></span>';
        } else {
            $html .= '<a href="' . htmlspecialchars($buildUrl($currentPage - 1)) . '" class="page-btn"><i class="fa-solid fa-chevron-left"></i></a>';
        }

        if ($start > 1) {
            $html .= '<a href="' . htmlspecialchars($buildUrl(1)) . '" class="page-btn">1</a>';
            if ($start > 2) {
                $html .= '<span class="page-btn disabled">&hellip;</span>';
            }
        }

        for ($i = $start; $i <= $end; $i++) {
            $active = $i === $currentPage ? " active" : "";
            if ($i === $currentPage) {
                $html .= '<span class="page-btn' . $active . '">' . $i . '</span>';
            } else {
                $html .= '<a href="' . htmlspecialchars($buildUrl($i)) . '" class="page-btn">' . $i . '</a>';
            }
        }

        if ($end < $totalPages) {
            if ($end < $totalPages - 1) {
                $html .= '<span class="page-btn disabled">&hellip;</span>';
            }
            $html .= '<a href="' . htmlspecialchars($buildUrl($totalPages)) . '" class="page-btn">' . $totalPages . '</a>';
        }

        if ($nextDisabled) {
            $html .= '<span class="page-btn disabled"><i class="fa-solid fa-chevron-right"></i></span>';
        } else {
            $html .= '<a href="' . htmlspecialchars($buildUrl($currentPage + 1)) . '" class="page-btn"><i class="fa-solid fa-chevron-right"></i></a>';
        }

        $html .= '</nav>';
        $html .= '</div>';

        return $html;
    }
}
