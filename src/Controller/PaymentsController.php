<?php
namespace App\Controller;

use App\Entity\Client;
use App\Entity\Order;
use App\Entity\Payment;
use App\Enum\OrderStatus;
use App\Repository\OrderRepository;
use App\Repository\PaymentRepository;
use Doctrine\ORM\EntityManagerInterface;
use PayPalCheckoutSdk\Core\PayPalHttpClient;
use PayPalCheckoutSdk\Core\ProductionEnvironment;
use PayPalCheckoutSdk\Core\SandboxEnvironment;
use PayPalCheckoutSdk\Orders\OrdersCaptureRequest;
use PayPalCheckoutSdk\Orders\OrdersCreateRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/payments')]
final class PaymentsController extends AbstractController
{
    private function ppClient(): PayPalHttpClient
    {
        $clientId = $_ENV['PAYPAL_CLIENT_ID'] ?? '';
        $clientSecret = $_ENV['PAYPAL_CLIENT_SECRET'] ?? '';
        $env = (($_ENV['PAYPAL_MODE'] ?? 'sandbox') === 'live')
            ? new ProductionEnvironment($clientId, $clientSecret)
            : new SandboxEnvironment($clientId, $clientSecret);

        return new PayPalHttpClient($env);
    }

    #[Route('', name: 'app_payments_index', methods: ['GET'])]
    public function index(Request $req, OrderRepository $ordersRepo, PaymentRepository $paymentRepo): Response
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            // Vue ADMIN : liste des paiements
            $payments = $paymentRepo->findAllForAdmin();

            return $this->render('payments/index.html.twig', [
                'is_admin' => true,
                'payments' => $payments,
                'orders'   => [],
            ]);
        }

        // Vue CLIENT : sélection des commandes à régler
        $this->denyAccessUnlessGranted('ROLE_CLIENT');

        $client = $this->getUser()?->getClient();
        if (!$client instanceof Client) {
            throw $this->createAccessDeniedException();
        }

        $orders = $ordersRepo->findBillableUnpaidByClient($client);

        return $this->render('payments/index.html.twig', [
            'is_admin' => false,
            'orders'   => $orders,
            'payments' => [],
        ]);
    }

    #[Route('/checkout/start', name: 'app_payments_checkout_start', methods: ['POST'])]
    public function checkoutStart(Request $req): Response
    {
        $this->denyAccessUnlessGranted('ROLE_CLIENT');

        if (!$this->isCsrfTokenValid('checkout-start', (string) $req->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', (array) $req->request->all('orders')))));

        if (!$ids) {
            $this->addFlash('warning', 'Please select at least one order.');

            return $this->redirectToRoute('app_payments_index');
        }

        $req->getSession()->set('checkout.order_ids', $ids);

        return $this->redirectToRoute('app_payments_checkout');
    }

    #[Route('/checkout', name: 'app_payments_checkout', methods: ['GET'])]
    public function checkout(Request $req, OrderRepository $ordersRepo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_CLIENT');

        $client = $this->getUser()?->getClient();
        if (!$client instanceof Client) {
            throw $this->createAccessDeniedException();
        }

        $ids = (array) $req->getSession()->get('checkout.order_ids', []);
        if (!$ids) {
            $this->addFlash('warning', 'Your cart is empty.');

            return $this->redirectToRoute('app_payments_index');
        }

        $orders = [];
        $totalCents = 0;

        foreach ($ids as $id) {
            $o = $ordersRepo->find($id);
            if (!$o instanceof Order) {
                continue;
            }
            if ($o->getClient() !== $client) {
                continue;
            }
            if ($o->isPaid()) {
                continue;
            }
            if (!\in_array($o->getStatus(), [OrderStatus::DELIVERED, OrderStatus::REVISION, OrderStatus::FINISHED], true)) {
                continue;
            }

            $orders[] = $o;
            $totalCents += (int) $o->getPrice();
        }

        if (!$orders) {
            $this->addFlash('warning', 'No eligible orders to pay.');

            return $this->redirectToRoute('app_payments_index');
        }

        return $this->render('payments/checkout.html.twig', [
            'orders'     => $orders,
            'totalCents' => $totalCents,
            'step'       => (int) ($req->query->get('step', 1)),
        ]);
    }

    #[Route('/checkout/paypal/create', name: 'app_payments_checkout_paypal_create', methods: ['POST'])]
    public function paypalCreate(Request $req, OrderRepository $ordersRepo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_CLIENT');

        $client = $this->getUser()?->getClient();
        if (!$client instanceof Client) {
            throw $this->createAccessDeniedException();
        }

        $ids = (array) $req->getSession()->get('checkout.order_ids', []);
        if (!$ids) {
            return $this->redirectToRoute('app_payments_checkout');
        }

        $totalCents = 0;
        $eligibleIds = [];

        foreach ($ids as $id) {
            $o = $ordersRepo->find($id);
            if (
                $o instanceof Order &&
                $o->getClient() === $client &&
                !$o->isPaid() &&
                \in_array($o->getStatus(), [OrderStatus::DELIVERED, OrderStatus::REVISION, OrderStatus::FINISHED], true)
            ) {
                $totalCents += (int) $o->getPrice();
                $eligibleIds[] = $id;
            }
        }

        if ($totalCents <= 0 || !$eligibleIds) {
            $this->addFlash('warning', 'No eligible orders to pay.');

            return $this->redirectToRoute('app_payments_checkout');
        }
        $req->getSession()->set('checkout.order_ids', $eligibleIds);

        $pp = $this->ppClient();
        $r = new OrdersCreateRequest();
        $r->prefer('return=representation');
        $r->body = [
            'intent'              => 'CAPTURE',
            'purchase_units'      => [[
                'amount'      => [
                    'currency_code' => 'EUR',
                    'value'         => number_format($totalCents / 100, 2, '.', ''),
                ],
                'description' => sprintf('ThumbUp orders: %s', implode(',', $eligibleIds)),
                'custom_id'   => implode(',', $eligibleIds),
            ]],
            'application_context' => [
                'brand_name'  => 'ThumbUp',
                'landing_page'=> 'LOGIN',
                'user_action' => 'PAY_NOW',
                'return_url'  => $this->generateUrl(
                    'app_payments_checkout_paypal_return',
                    [],
                    \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL
                ),
                'cancel_url'  => $this->generateUrl(
                    'app_payments_checkout_paypal_cancel',
                    [],
                    \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL
                ),
            ],
        ];

        $res = $pp->execute($r);

        $approveUrl = null;
        foreach ($res->result->links ?? [] as $ln) {
            if (($ln->rel ?? '') === 'approve') {
                $approveUrl = $ln->href ?? null;
                break;
            }
        }

        if (!$approveUrl) {
            $this->addFlash('danger', 'PayPal error: approval link not found.');

            return $this->redirectToRoute('app_payments_checkout');
        }

        return new RedirectResponse($approveUrl);
    }

    #[Route('/checkout/paypal/return', name: 'app_payments_checkout_paypal_return', methods: ['GET'])]
    public function paypalReturn(
        Request $req,
        OrderRepository $ordersRepo,
        PaymentRepository $payRepo,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_CLIENT');

        $token = (string) $req->query->get('token');
        if (!$token) {
            $this->addFlash('danger', 'Missing PayPal token.');

            return $this->redirectToRoute('app_payments_checkout');
        }

        $pp = $this->ppClient();
        $capReq = new OrdersCaptureRequest($token);
        $capReq->prefer('return=representation');
        $res = $pp->execute($capReq);

        $status = strtoupper((string) ($res->result->status ?? ''));

        $unit   = $res->result->purchase_units[0] ?? null;
        $amount = $unit->amount->value ?? '0.00';
        $amountCents = (int) round(((float) $amount) * 100);
        $idsCsv = (string) ($unit->custom_id ?? '');
        $ids    = $idsCsv ? array_filter(array_map('intval', explode(',', $idsCsv))) : [];

        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $client = $user?->getClient();

        if (!$client instanceof Client) {
            $this->addFlash('danger', 'Client not found for this user.');

            return $this->redirectToRoute('app_payments_checkout');
        }

        /** @var Payment|null $pay */
        $pay = $payRepo->findOneByPaypalOrderId($token);

        if (!$pay) {
            $pay = (new Payment())
                ->setUser($user)
                ->setClient($client)
                ->setPaymentMethod('paypal')
                ->setPaypalOrderId($token)
                ->setAmountCents($amountCents)
                ->setCurrency('EUR')
                ->setOrdersCsv($idsCsv)
                ->setStatus($status)
                ->setRawPayload(json_encode($res->result));

            $em->persist($pay);
        } else {
            $pay->setStatus($status);
            $pay->setRawPayload(json_encode($res->result));
        }

        $cap = $res->result->purchase_units[0]->payments->captures[0]->id ?? null;
        if ($cap) {
            $pay->setPaypalCaptureId((string) $cap);
        }

        if ($status === 'COMPLETED') {
            $updated = 0;
            foreach ($ids as $id) {
                $o = $ordersRepo->find($id);
                if (
                    $o instanceof Order &&
                    $o->getClient() === $client &&
                    !$o->isPaid() &&
                    \in_array($o->getStatus(), [OrderStatus::DELIVERED, OrderStatus::REVISION, OrderStatus::FINISHED], true)
                ) {
                    $o->setPaid(true);
                    $o->setUpdatedAt(new \DateTimeImmutable());
                    $updated++;
                }
            }

            if ($updated > 0) {
                $em->flush();
            }

            $req->getSession()->remove('checkout.order_ids');

            return $this->redirectToRoute('app_payments_success', ['id' => $pay->getId()]);
        }

        $em->flush();

        $this->addFlash('warning', 'Payment not completed.');

        return $this->redirectToRoute('app_payments_checkout', ['step' => 2]);
    }

    #[Route('/checkout/paypal/cancel', name: 'app_payments_checkout_paypal_cancel', methods: ['GET'])]
    public function paypalCancel(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_CLIENT');

        return $this->redirectToRoute('app_payments_checkout', ['step' => 2]);
    }

    #[Route('/webhook/paypal', name: 'app_payments_webhook_paypal', methods: ['POST'])]
    public function webhook(
        Request $req,
        OrderRepository $ordersRepo,
        PaymentRepository $payRepo,
        EntityManagerInterface $em
    ): Response {
        $json    = $req->getContent();
        $payload = json_decode($json, true) ?? [];
        $event   = (string) ($payload['event_type'] ?? '');
        $resource = $payload['resource'] ?? [];

        if ($event === 'PAYMENT.CAPTURE.COMPLETED' || $event === 'CHECKOUT.ORDER.APPROVED') {
            $paypalOrderId = (string) ($resource['supplementary_data']['related_ids']['order_id'] ?? $resource['id'] ?? '');
            if ($paypalOrderId) {
                $p = $payRepo->findOneByPaypalOrderId($paypalOrderId);
                if ($p) {
                    $p->setStatus('COMPLETED');
                    $p->setRawPayload($json);

                    $ids = array_filter(array_map('intval', explode(',', (string) $p->getOrdersCsv())));
                    $client = $p->getClient();
                    $updated = 0;

                    foreach ($ids as $id) {
                        $o = $ordersRepo->find($id);
                        if (
                            $o instanceof Order &&
                            $o->getClient() === $client &&
                            !$o->isPaid() &&
                            \in_array($o->getStatus(), [OrderStatus::DELIVERED, OrderStatus::REVISION, OrderStatus::FINISHED], true)
                        ) {
                            $o->setPaid(true);
                            $o->setUpdatedAt(new \DateTimeImmutable());
                            $updated++;
                        }
                    }

                    if ($updated > 0) {
                        $em->flush();
                    }
                }
            }
        }

        return new Response('', 204);
    }

    #[Route('/success/{id}', name: 'app_payments_success', methods: ['GET'])]
    public function success(int $id, PaymentRepository $payRepo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_CLIENT');

        $p = $payRepo->find($id);
        if (!$p || $p->getClient() !== $this->getUser()?->getClient()) {
            throw $this->createNotFoundException();
        }

        return $this->render('payments/success.html.twig', ['p' => $p]);
    }

    #[Route('/payments/history', name: 'app_payments_history', methods: ['GET'])]
    public function history(PaymentRepository $payments): Response
    {
        /** @var \App\Entity\User|null $user */
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException();
        }

        $items = $payments->createQueryBuilder('p')
            ->andWhere('p.user = :u')
            ->setParameter('u', $user)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('payments/history.html.twig', [
            'payments' => $items,
            'user'     => $user,
        ]);
    }
}
