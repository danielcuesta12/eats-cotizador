<?php
header('Content-Type: text/plain; charset=UTF-8');
echo mb_convert_encoding('&#127828;', 'UTF-8', 'HTML-ENTITIES') . "\n";