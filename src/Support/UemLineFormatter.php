<?php

declare(strict_types=1);

namespace Ultra\ErrorManager\Support;

use Monolog\Formatter\LineFormatter;
use Monolog\LogRecord;

final class UemLineFormatter extends LineFormatter
{
    /**
     * {@inheritdoc}
     * Formatta un record di log UEM in una stringa multi-linea precisa.
     */
    public function format(LogRecord $record): string
    {
        // Questo è il messaggio principale che arriva da UemLogFormatter
        $coreMessage = $record->message; 

        // Assembla la stringa finale esattamente come la vogliamo
        $output  = "[%datetime%] %channel%.%level_name%: \n";
        $output .= $coreMessage . "\n\n";

        return $this->replacePlaceholders($record->with(message: $output));
    }
}