<?php

namespace App\Controllers;

class TestController
{
    public function index(): void
    {
        header('Content-Type: application/json');

        echo json_encode([
            'success' => true,
            'message' => 'teste'
        ]);
    }
}