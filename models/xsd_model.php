<?php

libxml_use_internal_errors(true);

function xsd_get_error()
{
    $result = '';
    foreach (libxml_get_errors() as $error) {
        $result[] = $error->code . ': ' . trim($error->message);
    }
    libxml_clear_errors();

    return implode("\n", $result);
}
