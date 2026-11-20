<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;

class HomeController
{
    public function index(Request $request): Response
    {
        return Response::view('pages/home', [
            'title' => 'Home',
        ]);
    }
}
