<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Unduh .xlsx. Teks selalu ditulis sebagai teks (bukan rumus), supaya isian pembeli seperti "=..." aman dibuka di Excel. */
class XlsxExport
{
    public static function download(string $filename, array $headings, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headings, $rows) {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $line = 1;

            foreach ([$headings, ...$rows] as $row) {
                foreach (array_values($row) as $index => $value) {
                    $numeric = is_int($value) || is_float($value);
                    $sheet->setCellValueExplicit(Coordinate::stringFromColumnIndex($index + 1).$line, $numeric ? $value : (string) $value, $numeric ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING);
                }
                $line++;
            }

            $last = Coordinate::stringFromColumnIndex(max(1, count($headings)));
            $sheet->getStyle("A1:{$last}1")->getFont()->setBold(true);
            $sheet->freezePane('A2');
            foreach (range(1, count($headings)) as $column) {
                $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($column))->setAutoSize(true);
            }

            (new Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename.'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
