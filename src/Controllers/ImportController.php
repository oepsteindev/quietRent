<?php

namespace QuietRent\Controllers;

use QuietRent\Core\{Auth, Response};
use QuietRent\Services\CsvImporter;

class ImportController
{
    public function store(array $params): void
    {
        Auth::require();
        Auth::verifyCsrf();

        if (empty($_FILES['csv']) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
            Response::json(['error' => 'No file uploaded or upload error'], 400);
        }

        $tmp = $_FILES['csv']['tmp_name'];
        $result = CsvImporter::import(Auth::accountId(), $tmp);

        Response::json($result);
    }
}
