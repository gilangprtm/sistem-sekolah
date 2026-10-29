<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use SimpleXMLElement;
use ZipArchive;

final class XlsxTemplateReader
{
    /**
     * @param  list<string>  $headers
     * @return array<int, array<string, string|null>>
     */
    public function read(UploadedFile $file, array $headers, string $templateName): array
    {
        $path = $file->getRealPath();
        $archive = new ZipArchive;
        if ($path === false || $archive->open($path) !== true) {
            $this->fail('File Excel tidak valid.');
        }

        try {
            $sheetXml = $this->part($archive, 'xl/worksheets/sheet1.xml');
            $sharedStringsXml = $this->part($archive, 'xl/sharedStrings.xml');
        } finally {
            $archive->close();
        }
        if ($sheetXml === false) {
            $this->fail('File Excel tidak valid.');
        }

        $sheet = $this->parseXml($sheetXml);
        $sharedStrings = $sharedStringsXml === false ? [] : $this->readSharedStrings($sharedStringsXml);
        $rows = [];
        $maxRow = 0;
        $sheetRoot = $sheet->children('http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        foreach ($sheetRoot->sheetData->row ?? [] as $sheetRow) {
            $rowAttributes = $sheetRow->attributes();
            $rowNumber = (int) ($rowAttributes['r'] ?? 0);
            $rowNumber = $rowNumber > 0 ? $rowNumber : $maxRow + 1;
            if ($rowNumber > 303) {
                $this->fail('Excel maksimal berisi 300 baris.');
            }
            $maxRow = max($maxRow, $rowNumber);
            $values = array_fill(0, count($headers), null);
            foreach ($sheetRow->c ?? [] as $cell) {
                $attributes = $cell->attributes();
                $column = $this->columnIndex((string) ($attributes['r'] ?? ''));
                if ($column === null) {
                    $this->fail('File Excel memiliki referensi kolom yang tidak valid.');
                }
                if ($column >= count($headers)) {
                    $this->fail("Baris {$rowNumber}: jumlah kolom tidak sesuai template.", "rows.{$rowNumber}");
                }
                $values[$column] = $this->cellValue($cell, $sharedStrings);
            }
            $rows[$rowNumber] = $values;
        }
        if ($maxRow < 3 || ! isset($rows[1], $rows[2], $rows[3])) {
            $this->fail('Template Excel harus memiliki tiga baris metadata di awal.');
        }
        $header = array_map(static fn (?string $value): string => trim((string) $value), $rows[1]);
        if ($header !== $headers) {
            $this->fail("Header Excel tidak sesuai template {$templateName}.");
        }
        $dataRows = [];
        for ($rowNumber = 4; $rowNumber <= $maxRow; $rowNumber++) {
            $values = $rows[$rowNumber] ?? array_fill(0, count($headers), null);
            if (count(array_filter($values, static fn (?string $value): bool => $value !== null)) === 0) {
                $this->fail("Baris {$rowNumber}: baris Excel tidak boleh kosong.", "rows.{$rowNumber}");
            }
            $dataRows[$rowNumber] = array_combine($headers, $values);
        }
        if ($dataRows === []) {
            $this->fail('Excel harus berisi minimal satu baris data mulai baris 4.');
        }

        return $dataRows;
    }

    private function part(ZipArchive $archive, string $name): string|false
    {
        $stat = $archive->statName($name);
        if ($stat !== false && $stat['size'] > 16 * 1024 * 1024) {
            $this->fail('Isi file Excel terlalu besar.');
        }

        return $archive->getFromName($name);
    }

    /** @return list<string> */
    private function readSharedStrings(string $xml): array
    {
        $shared = $this->parseXml($xml)->children('http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $strings = [];
        foreach ($shared->si ?? [] as $item) {
            $strings[] = trim(implode('', array_map(
                static fn (SimpleXMLElement $text): string => (string) $text,
                iterator_to_array($item->xpath('.//*[local-name()="t"]') ?: []),
            )));
        }

        return $strings;
    }

    /** @param list<string> $sharedStrings */
    private function cellValue(SimpleXMLElement $cell, array $sharedStrings): ?string
    {
        $attributes = $cell->attributes();
        $type = (string) ($attributes['t'] ?? '');
        $value = $type === 'inlineStr'
            ? implode('', array_map(
                static fn (SimpleXMLElement $text): string => (string) $text,
                iterator_to_array($cell->xpath('.//*[local-name()="t"]') ?: []),
            ))
            : (string) ($cell->v ?? '');
        if ($type === 's') {
            $value = $sharedStrings[(int) $value] ?? '';
        }
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function columnIndex(string $reference): ?int
    {
        if (! preg_match('/^([A-Z]+)\\d+$/i', $reference, $matches)) {
            return null;
        }
        $index = 0;
        foreach (str_split(strtoupper($matches[1])) as $letter) {
            $index = ($index * 26) + ord($letter) - 64;
            if ($index > 16384) {
                return null;
            }
        }

        return $index - 1;
    }

    private function parseXml(string $xml): SimpleXMLElement
    {
        if (stripos($xml, '<!DOCTYPE') !== false || stripos($xml, '<!ENTITY') !== false) {
            $this->fail('File Excel tidak valid.');
        }
        $previous = libxml_use_internal_errors(true);
        try {
            $parsed = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        if ($parsed === false) {
            $this->fail('File Excel tidak valid.');
        }

        return $parsed;
    }

    private function fail(string $message, string $key = 'file'): never
    {
        throw ValidationException::withMessages([$key => $message]);
    }
}
