<?php

namespace App\Controller;

use App\Form\FilterAccountsType;
use App\Repository\ReportsRepository;
use MongoDB\Exception\Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use MongoDB;

class ReportsController extends AbstractController
{

    #[Route('/', name: 'reports')]
    public function index(Request $request, ReportsRepository $reportsRepository): Response
    {
        // Getting the accountId sent as a parameter
        $accountId = $request->get('accountId');
        $accounts = $reportsRepository->findAccountsWithMetricsSummary($accountId)->toArray();

        // If the repository's result-set is empty
        if(count($accounts) === 0) {
            if(!$reportsRepository->hasActiveAccounts()) {
                // There are no account documents in the DB, or they are not active
                $this->addFlash('accounts_table_error', 'No active accounts.');
            } else {
                // The requested accountID doesn't exist
                $this->addFlash('accounts_table_error', 'No data available for the supplied Account ID.');
            }
        }

        return $this->render('reports/index.html.twig', [
            'controller_name' => 'ReportsController',
            'accountId' => $accountId,
            'accounts' => $accounts
        ]);
    }
}
