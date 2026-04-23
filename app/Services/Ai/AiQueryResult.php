<?php

namespace App\Services\Ai;

class AiQueryResult
{
    /**
     * @param  array<int, string>  $columns
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function __construct(
        public array $columns,
        public array $rows,
        public string $summary,
        public array $debug = [],
    ) {
    }

    public function toArray(): array
    {
        return [
            'columns' => $this->columns,
            'rows' => $this->rows,
            'summary' => $this->summary,
            'debug' => $this->debug,
        ];
    }
}

