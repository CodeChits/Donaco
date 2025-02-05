<?php

namespace App\Http\Controllers;

use App\Repositories\DonationRepository;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use App\Repositories\ProjectRepository;
use App\Rules\ValidateIfImageIsNsfw;
use Illuminate\Support\Facades\Log;
use Throwable;

class DonationController extends Controller
{
    protected DonationRepository $donationRepository;
    protected PaymentService $paymentService;
    protected ProjectRepository $projectRepository;

    public function __construct(
        DonationRepository $donationRepository,
        PaymentService $paymentService,
        ProjectRepository $projectRepository,
    ) {
        $this->donationRepository = $donationRepository;
        $this->paymentService = $paymentService;
        $this->projectRepository = $projectRepository;
    }

    public function index(): View
    {
        $projects = $this->projectRepository->getAllProjects();
        $recentDonations = $this->donationRepository->getRecentDonations();
        $rankingDonations = $this->donationRepository->getRankingDonations();
        $projectTotals = $this->donationRepository->getProjectTotals();

        $pixManualData = [
            'key' => config('pix.manual_key'),
            'type' => config('pix.manual_type'),
            'name' => config('pix.manual_name'),
            'enabled' => config('pix.manual_enabled', false)
        ];

        return view('donations.index', compact(
            'projects',
            'recentDonations',
            'rankingDonations',
            'projectTotals',
            'pixManualData'
        ));
    }

    public function store(Request $request)
    {
        try {
            // Log dos dados recebidos
            Log::info('Dados da doação recebidos:', $request->all());

            $data = $request->validate([
                'project_id' => 'required|exists:projects,id',
                'nickname' => 'required|string|max:255',
                'email' => 'required|email|max:255',
                'value' => 'required|string|regex:/^\d+(?:,\d{2})?$/',
                'message' => 'nullable|string|max:500',
                'payment_method' => 'required|in:mercadopago,manual',
                'proof_file' => [
                    'required_if:payment_method,manual',
                    'file',
                    'mimes:pdf,png,jpg,jpeg',
                    'max:2048',
                    new ValidateIfImageIsNsfw(),
                ],
            ]);

            // Trata o valor baseado no método de pagamento
            if ($data['payment_method'] === 'mercadopago') {
                // Converte para centavos para o Mercado Pago
                $value = str_replace(['.', ','], '', $data['value']); // Remove pontos e vírgulas
                $value = ltrim($value, '0'); // Remove zeros à esquerda
                if (strlen($value) === 1) {
                    $value = '0.0' . $value; // Adiciona zeros para centavos (ex: 1 -> 0.01)
                } elseif (strlen($value) === 2) {
                    $value = '0.' . $value; // Adiciona zero para centavos (ex: 10 -> 0.10)
                } else {
                    $value = substr_replace($value, '.', -2, 0); // Adiciona ponto antes dos últimos 2 dígitos
                }
                $data['value'] = (float) $value;
            } else {
                // Para outros métodos, mantém o formato decimal
                $data['value'] = str_replace(',', '.', $data['value']);
            }

            // Processar o pagamento
            $paymentResult = $this->paymentService->processPayment($data);

            if ($paymentResult['success']) {
                // Criar a doação
                $donationData = array_merge($data, [
                    'status' => $paymentResult['status'],
                    'external_reference' => $paymentResult['external_reference']
                ]);

                // Se for pagamento manual e tiver arquivo de comprovante
                if ($request->hasFile('proof_file')) {
                    $file = $request->file('proof_file');
                    $fileName = time() . '_' . $file->getClientOriginalName();
                    $file->storeAs('', $fileName, 'proofs');
                    $donationData['proof_file'] = 'proofs/' . $fileName;
                }

                $donation = $this->donationRepository->create($donationData);

                return response()->json([
                    'success' => true,
                    'message' => __('donations.messages.created_success'),
                    'data' => [
                        'donation_id' => $donation->id,
                        'qr_code' => $paymentResult['qr_code'] ?? null,
                        'qr_code_base64' => $paymentResult['qr_code_base64'] ?? null,
                        'ticket_url' => $paymentResult['ticket_url'] ?? null,
                        'external_reference' => $paymentResult['external_reference']
                    ]
                ]);
            }
        } catch (Throwable $e) {
            Log::error('Erro ao processar doação:', [
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => __('donations.messages.errors.donation_failed'),
                'error' => $e->getMessage()
            ], 422);
        }
    }
}
