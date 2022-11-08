<?php

namespace Helper;

class File extends \Image
{
    public static $mimeMap = [
        "image" => [
            "image/jpeg",
            "image/png",
            "image/gif",
            "image/bmp",
        ],
        "icon" => [
            "audio/.+" => "_audio",
            "application/.*zip" => "_archive",
            "application/x-php" => "_code",
            "(application|text)/xml" => "_code",
            "text/html" => "_code",
            "image/.+" => "_image",
            "application/x-photoshop" => "_image",
            "video/.+" => "_video",
            "application/.*pdf" => "pdf",
            "text/[ct]sv" => "csv",
            "text/.+-separated-values" => "csv",
            "text/.+" => "txt",
            "application/sql" => "txt",
            "application/vnd\.oasis\.opendocument\.graphics" => "odg",
            "application/vnd\.oasis\.opendocument\.spreadsheet" => "ods",
            "application/vnd\.oasis\.opendocument\.presentation" => "odp",
            "application/vnd\.oasis\.opendocument\.text" => "odt",
            "application/(msword|vnd\.(ms-word|openxmlformats-officedocument\.wordprocessingml.+))" => "doc",
            "application/(msexcel|vnd\.(ms-excel|openxmlformats-officedocument\.spreadsheetml.+))" => "xls",
            "application/(mspowerpoint|vnd\.(ms-powerpoint|openxmlformats-officedocument\.presentationml.+))" => "ppt",
        ],
    ];

    /**
     * Get an icon name by MIME type
     *
     * Returns "_blank" when no icon matches
     *
     * @param  string $contentType
     * @return string
     */
    public static function mimeIcon($contentType)
    {
        foreach (self::$mimeMap["icon"] as $regex => $name) {
            if (preg_match("@^" . $regex . "$@i", $contentType)) {
                return $name;
            }
        }
        return "_blank";
    }
}
