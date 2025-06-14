<?php
// src/Controller/RedirectController.php
namespace App\Controller;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;

class RedirectController
{
    #[Route('/', name: 'redirect_to_login', methods: ['GET'])]
    public function index(): RedirectResponse
    {
        return new RedirectResponse('/api/login');
    }
}
