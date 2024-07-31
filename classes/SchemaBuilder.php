<?php

namespace APP\plugins\generic\plauditPreEndorsement\classes;

class SchemaBuilder
{
    public static function get($schemaName)
    {
        $schemaFile = sprintf(
            '%s/plugins/generic/plauditPreEndorsement/schemas/%s.json',
            BASE_SYS_DIR,
            $schemaName
        );
        if (file_exists($schemaFile)) {
            $schema = json_decode(file_get_contents($schemaFile));
            if (!$schema) {
                throw new \Exception(
                    'Schema failed to decode. This usually means it is invalid JSON. Requested: '
                    . $schemaFile
                    . '. Last JSON error: '
                    . json_last_error()
                );
            }
        }
        return $schema;
    }
}
