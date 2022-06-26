<?php
namespace Techio\CSVHandler;

use Exception;

class CSVHandler
{
    private string $columnSeparator;
    private string $lineSeparator;
    private bool $escapeData;
    private array $headerFields;
    private array $lines;

    public function getColumnSeparator(): string
    {
        return $this->columnSeparator;
    }

    public function setColumnSeparator(string $columnSeparator): void
    {
        $this->columnSeparator = $columnSeparator;
    }

    public function setLineSeparator(string $lineSeparator): void
    {
        $this->lineSeparator = $lineSeparator;
    }

    public function getLineSeparator(): string
    {
        return $this->lineSeparator;
    }

    public function getEscapeData(): bool
    {
        return $this->escapeData;
    }

    public function setEscapeData(bool $escapeData): void
    {
        $this->escapeData = $escapeData;
    }

    public function getHeaderFields(): array
    {
        return $this->headerFields;
    }

    public function setHeaderFields(array $headerFields): void
    {
        foreach($headerFields as $headerField){
            $fieldType = gettype($headerField);

            if($fieldType != 'string' && $fieldType != 'integer' && $fieldType != 'double' && $fieldType != 'boolean'){
                throw new Exception("Invalid type for header field: $fieldType", 403);
            }
        }
        $this->headerFields = $headerFields;
    }

    public function setHeaderField(int $position, $value): void
    {
        if(isset($this->headerFields[$position - 1])){
            $fieldType = gettype($value);

            if($fieldType != 'string' && $fieldType != 'integer' && $fieldType != 'double' && $fieldType != 'boolean'){
                throw new Exception("Invalid type for header field: $fieldType", 403);
            }

            $this->headerFields[$position - 1] = $value;
        }else{
            throw new Exception("Doesn't exist a header field on $position position", 404);
        }
    }

    public function addColumn(array $values) {
        foreach($values as $value){
            $fieldType = gettype($value);

            if($fieldType != 'string' && $fieldType != 'integer' && $fieldType != 'double' && $fieldType != 'boolean'){
                throw new Exception("Invalid type for value: $fieldType", 403);
            }
        }

        if(count($this->headerFields)){
            array_push($this->headerFields, array_shift($values));
        }

        $lines = [];
        foreach($this->lines as $line){
            array_push($line, count($values) ? array_shift($values) : null);
            $lines[] = $line;
        }
        
        $this->setLines($lines);
    }

    public function getLines(): array
    {
        return $this->lines;
    }

    public function setLines(array $lines): void
    {
        $this->lines = [];
        $this->addLines($lines);
    }

    public function addLine(array $line): void
    {
        foreach($line as $field){
            $fieldType = gettype($field);

            if($fieldType != 'string' && $fieldType != 'integer' && $fieldType != 'double' && $fieldType != 'boolean'){
                throw new Exception("Invalid type for value: $fieldType", 403);
            }
        }

        array_push($this->lines, $line);
    }

    public function addLines(array $lines): void
    {
        foreach($lines as $line){
            $this->addLine($line);
        }
    }

    public function __construct(array $lines, bool $hasHeader = true, string $columnSeparator = ',', string $lineSeparator = "\n", bool $escapeData = true)
    {
        foreach($lines as $line) {
            if(gettype($line) != 'array'){
                throw new Exception("Line is not an array", 403);
            }
        }

        if ($hasHeader) {
            $headerFields = array_shift($lines);

            
            $this->setHeaderFields($headerFields);
            $this->setLines($lines);
        } else {
            $this->setHeaderFields([]);
            $this->setLines($lines);
        }

        $this->columnSeparator = $columnSeparator;
        $this->lineSeparator = $lineSeparator;
        $this->escapeData = $escapeData;
    }

    public function getValueFromPosition(int $line, int $column)
    {
        if (isset($this->lines[$line - 1][$column - 1])) {
            return $this->lines[$line - 1][$column - 1];
        } else {
            throw new Exception("Doesn't exist a value on $line line and $column column.", 400);
        }
    }

    public function setValueFromPosition(int $line, int $column, $value)
    {
        if (isset($this->lines[$line - 1][$column - 1])) {
            $this->lines[$line - 1][$column - 1] = $value;
        } else {
            throw new Exception("Doesn't exist a value on $line line and $column column.", 400);
        }
    }

    public function getColumnValues(int $column): array
    {
        return array_map(function ($lineFields) use ($column) {
            return $lineFields[$column - 1] ?? null;
        }, $this->lines);
    }

    public function getLineValues(int $line): array
    {
        if(isset($this->lines[$line - 1])){
            return $this->lines[$line - 1];
        }else{
            throw new Exception("Line doesn't exists", 404);
        }
    }

    public static function createFromFile(string $filename, bool $hasHeader = true, string $columnSeparator = ',', string $lineSeparator = "\n")
    {
        if (!is_file($filename)) {
            throw new Exception("File doesn't exits", 404);
        }

        $fileContent = file_get_contents($filename);

        $lineColumns = array_map(function ($line) use ($columnSeparator) {
            return explode($columnSeparator, $line);
        }, explode($lineSeparator, $fileContent));

        $csvHandler = new CSVHandler($lineColumns, $hasHeader, $columnSeparator, $lineSeparator);

        return $csvHandler;
    }

    private function escapeLine(array $line)
    {
        return array_map(function ($field) {
            $fieldType = gettype($field);

            if ($fieldType == 'int' || $fieldType == 'double') {
                return $field;
            } else if ($fieldType == 'string') {
                $field = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $field);
                if ($field) {
                    if(strlen($field) && $field[0] == '"' && $field[strlen($field)-1] == '"'){
                        return $field;
                    }else{
                        return '"' . $field . '"';
                    }
                } else {
                    return null;
                }
            } else if ($fieldType == 'NULL') {
                return null;
            } else {
                return json_encode($field);
            }
        }, $line);
    }

    public function getPlain() {
        $lines = [];
        if(count($this->headerFields)){
            $lines[] = $this->escapeData ? $this->escapeLine($this->headerFields) : $this->headerFields;
        }

        if($this->escapeData) {
            foreach($this->lines as $line){
                $lines[] = $this->escapeLine($line);
            }
        }else{
            $lines[] = array_merge($lines, $this->lines);
        }

        return join($this->lineSeparator, array_map(function ($line) {
            return join($this->columnSeparator, $line);
        }, $lines));
    }

    public function sendContent(?string $filename = null)
    {
        header('Content-Encoding: UTF-8');
        header("Content-type: text/csv; charset=UTF-8");
        header("Content-Disposition: attachment; filename=".($filename ?: basename($_SERVER['PHP_SELF'], '.php').'.csv'));

        echo "\xEF\xBB\xBF";
        echo $this->getPlain();
        exit;
    }

    public function exportFile(string $filename)
    {
        $file = fopen($filename, 'w');

        fputs($file, "\xEF\xBB\xBF");

        fputs($file, $this->getPlain());

        fclose($file);
    }
}