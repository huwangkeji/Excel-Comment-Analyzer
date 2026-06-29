<?php
/**
 * ExcelReader - Pure PHP Excel/CSV reader
 * Supports: XLSX (ZIP+XML), CSV, basic XLS
 * No external dependencies
 */

class ExcelReader
{
    private $data = [];
    private $headers = [];
    private $sheetNames = [];
    private $currentSheet = 0;

    /**
     * Read file and return parsed data
     */
    public function read($filePath, $fileType = null)
    {
        if (!$fileType) {
            $fileType = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        }

        switch ($fileType) {
            case 'xlsx':
                return $this->readXlsx($filePath);
            case 'csv':
                return $this->readCsv($filePath);
            case 'xls':
                return $this->readXls($filePath);
            default:
                throw new Exception("Unsupported file type: $fileType");
        }
    }

    /**
     * Read XLSX file (ZIP containing XML)
     */
    private function readXlsx($filePath)
    {
        if (!class_exists('ZipArchive')) {
            throw new Exception('ZipArchive extension is required for XLSX files');
        }

        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            throw new Exception('Cannot open XLSX file');
        }

        // Read shared strings (namespace-agnostic XPath for PHP compatibility)
        $sharedStrings = [];
        if (($ssIndex = $zip->locateName('xl/sharedStrings.xml')) !== false) {
            $ssXml = $zip->getFromIndex($ssIndex);
            $ssDoc = simplexml_load_string($ssXml);
            $siNodes = $ssDoc->xpath('//*[local-name()="si"]');
            if ($siNodes) {
                foreach ($siNodes as $si) {
                    $tNodes = $si->xpath('.//*[local-name()="t"]');
                    $text = '';
                    if ($tNodes) {
                        foreach ($tNodes as $t) {
                            $text .= (string)$t;
                        }
                    }
                    $sharedStrings[] = $text;
                }
            }
        }

        // Find all worksheets
        $this->sheetNames = [];
        $wbIndex = $zip->locateName('xl/workbook.xml');
        if ($wbIndex !== false) {
            $wbXml = $zip->getFromIndex($wbIndex);
            $wbDoc = simplexml_load_string($wbXml);
            $sheets = $wbDoc->xpath('//*[local-name()="sheet"]');
            if ($sheets) {
                foreach ($sheets as $i => $sheet) {
                    $this->sheetNames[] = [
                        'name' => (string)$sheet['name'],
                        'index' => $i
                    ];
                }
            }
        }

        // Read each worksheet
        $allSheets = [];
        for ($i = 1; $i <= 20; $i++) {
            $sheetPath = 'xl/worksheets/sheet' . $i . '.xml';
            $sheetIndex = $zip->locateName($sheetPath);
            if ($sheetIndex === false && $i === 1) {
                // Some files use alternative naming
                $relsIndex = $zip->locateName('xl/_rels/workbook.xml.rels');
                if ($relsIndex !== false) {
                    // Just try whatever sheets exist
                }
                break;
            }
            if ($sheetIndex === false) {
                break;
            }

            $sheetXml = $zip->getFromIndex($sheetIndex);
            $sheetDoc = simplexml_load_string($sheetXml);

            // Use namespace-agnostic XPath to avoid PHP SimpleXML namespace issues
            $rows = $sheetDoc->xpath('//*[local-name()="row"]');
            $sheetData = [];

            if ($rows) {
                foreach ($rows as $row) {
                    $rowNum = (int)$row['r'];
                    $cells = $row->xpath('.//*[local-name()="c"]');
                    if (!$cells) continue;
                    
                    $rowData = [];
                    $colIdx = 0;

                    foreach ($cells as $cell) {
                        $cellRef = (string)$cell['r'];
                        $cellType = (string)$cell['t'];

                        // Calculate column index from cell reference (e.g., "A1" -> 0, "B1" -> 1)
                        preg_match('/^([A-Z]+)/', $cellRef, $m);
                        if (empty($m[1])) continue;
                        $targetCol = $this->columnToIndex($m[1]);

                        // Fill gaps with empty cells
                        while ($colIdx < $targetCol) {
                            $rowData[] = '';
                            $colIdx++;
                        }

                        $value = '';
                        $vNodes = $cell->xpath('.//*[local-name()="v"]');
                        $v = (!empty($vNodes)) ? (string)$vNodes[0] : '';

                        if ($cellType === 's' && $v !== '') {
                            // Shared string
                            $value = isset($sharedStrings[(int)$v]) ? $sharedStrings[(int)$v] : '';
                        } elseif ($cellType === 'inlineStr') {
                            $isNodes = $cell->xpath('.//*[local-name()="is"]/*[local-name()="t"]');
                            $value = !empty($isNodes) ? (string)$isNodes[0] : '';
                        } else {
                            $value = $v;
                        }

                        $rowData[] = $value;
                        $colIdx++;
                    }

                if (!empty($rowData)) {
                    $sheetData[] = $rowData;
                }
            }

            $allSheets[] = $sheetData;
        }
        }

        $zip->close();

        // Flatten to single sheet data for our use case
        if (!empty($allSheets)) {
            $this->data = $allSheets[0];

            // Skip empty leading rows (some Excel files have blank rows before headers)
            while (!empty($this->data) && $this->isEmptyRow($this->data[0])) {
                array_shift($this->data);
            }

            if (!empty($this->data)) {
                $this->headers = array_shift($this->data);
            }

            // Filter out completely empty data rows
            $this->data = array_values(array_filter($this->data, function ($row) {
                return !$this->isEmptyRow($row);
            }));
        }

        return [
            'headers' => $this->headers,
            'data' => $this->data,
            'sheets' => $this->sheetNames,
            'total_rows' => count($this->data)
        ];
    }

    /**
     * Read CSV file with encoding detection
     */
    private function readCsv($filePath)
    {
        $content = file_get_contents($filePath);

        // Detect encoding
        $encoding = mb_detect_encoding($content, ['UTF-8', 'GBK', 'GB2312', 'ISO-8859-1'], true);
        if ($encoding && $encoding !== 'UTF-8') {
            $content = mb_convert_encoding($content, 'UTF-8', $encoding);
        }

        // Detect delimiter
        $firstLine = strtok($content, "\n");
        $delimiter = $this->detectDelimiter($firstLine);

        $lines = explode("\n", $content);
        $this->data = [];
        $this->headers = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            // Remove BOM if present
            if (empty($this->data) && empty($this->headers) && substr($line, 0, 3) === "\xEF\xBB\xBF") {
                $line = substr($line, 3);
            }

            // Parse CSV row (using custom parser to avoid PHP 7.4 str_getcsv UTF-8 bug)
            $row = $this->parseCsvLine($line, $delimiter);
            if (empty($this->headers)) {
                $this->headers = $row;
            } else {
                // Ensure row matches header length
                while (count($row) < count($this->headers)) {
                    $row[] = '';
                }
                $this->data[] = array_slice($row, 0, count($this->headers));
            }
        }

        // Filter out completely empty data rows in CSV
        $this->data = array_values(array_filter($this->data, function ($row) {
            return !$this->isEmptyRow($row);
        }));

        $this->sheetNames = [['name' => 'Sheet1', 'index' => 0]];

        return [
            'headers' => $this->headers,
            'data' => $this->data,
            'sheets' => $this->sheetNames,
            'total_rows' => count($this->data)
        ];
    }

    /**
     * Basic XLS reader using raw string extraction
     * XLS binary format is complex — we extract readable strings
     */
    private function readXls($filePath)
    {
        // For XLS, we attempt to extract strings from the binary
        // A more complete solution would use a library, but this covers basic cases
        $content = file_get_contents($filePath);

        // Try to extract strings (Unicode strings in XLS start with specific markers)
        $strings = [];
        $len = strlen($content);

        // First try: extract all printable ASCII/Unicode runs
        preg_match_all('/[\x{4e00}-\x{9fff}\x{3000}-\x{303f}\x{ff00}-\x{ffef}a-zA-Z0-9\x{00c0}-\x{024f}\s\p{P}]+/u', $content, $matches);

        if (!empty($matches[0])) {
            // Heuristic: find the longest runs as potential cell content
            $runs = $matches[0];
            $meaningfulRuns = [];
            foreach ($runs as $run) {
                $run = trim($run);
                if (mb_strlen($run) >= 1 && !is_numeric(str_replace(['.', '-', '+'], '', $run))) {
                    $meaningfulRuns[] = $run;
                }
            }

            // Attempt to reconstruct rows (crude approximation)
            if (!empty($meaningfulRuns)) {
                // Group into chunks (guess columns based on common patterns)
                $this->data = [];
                $this->headers = [];

                // Very basic heuristic for XLS
                // For a proper solution, please use XLSX or CSV format
                // This extracts strings but can't guarantee correct row/column structure
                $this->headers = ['Column1', 'Column2', 'Column3', 'Column4', 'Column5'];
                foreach (array_chunk($meaningfulRuns, 5) as $chunk) {
                    while (count($chunk) < 5) $chunk[] = '';
                    $this->data[] = $chunk;
                }
            }
        }

        if (empty($this->data)) {
            throw new Exception(
                'XLS 文件解析不完整。XLS 是二进制格式，建议将文件另存为 XLSX 或 CSV 格式后再上传，以获得最佳分析效果。'
            );
        }

        $this->sheetNames = [['name' => 'Sheet1', 'index' => 0]];

        return [
            'headers' => $this->headers,
            'data' => $this->data,
            'sheets' => $this->sheetNames,
            'total_rows' => count($this->data)
        ];
    }

    /**
     * Convert column letter to index (A=0, B=1, ... Z=25, AA=26)
     */
    private function columnToIndex($col)
    {
        $col = strtoupper($col);
        $index = 0;
        $len = strlen($col);
        for ($i = 0; $i < $len; $i++) {
            $index = $index * 26 + (ord($col[$i]) - ord('A') + 1);
        }
        return $index - 1;
    }

    /**
     * Detect CSV delimiter
     */
    private function detectDelimiter($line)
    {
        $delimiters = [',', ';', "\t", '|'];
        $maxCount = 0;
        $bestDelimiter = ',';

        foreach ($delimiters as $delim) {
            $count = substr_count($line, $delim);
            if ($count > $maxCount) {
                $maxCount = $count;
                $bestDelimiter = $delim;
            }
        }

        return $bestDelimiter;
    }

    /**
     * Parse a CSV line — custom implementation to avoid PHP str_getcsv UTF-8 bug
     * that incorrectly merges fields with certain Chinese byte sequences
     */
    private function parseCsvLine($line, $delimiter)
    {
        $fields = [];
        $current = '';
        $inQuotes = false;
        $len = strlen($line);

        for ($i = 0; $i < $len; $i++) {
            $char = $line[$i];

            if ($char === '"') {
                if ($inQuotes && isset($line[$i + 1]) && $line[$i + 1] === '"') {
                    // Escaped quote inside quotes: "" -> "
                    $current .= '"';
                    $i++;
                } else {
                    // Toggle quote mode
                    $inQuotes = !$inQuotes;
                }
            } elseif ($char === $delimiter && !$inQuotes) {
                // End of field
                $fields[] = $current;
                $current = '';
            } else {
                $current .= $char;
            }
        }
        // Last field
        $fields[] = $current;

        return $fields;
    }

    /**
     * Check if a row is completely empty (all cells empty or whitespace)
     */
    private function isEmptyRow($row)
    {
        if (empty($row)) return true;
        foreach ($row as $cell) {
            if (trim((string)$cell) !== '') return false;
        }
        return true;
    }
}
