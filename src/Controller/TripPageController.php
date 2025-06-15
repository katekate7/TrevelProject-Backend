<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class TripPageController extends AbstractController
{
    #[Route('/trip', name: 'app_trip_page')]
    public function index(): Response
    {
        return $this->render('trip/index.html.twig');
    }
}
