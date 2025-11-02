<?php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\OrderAsset;
use App\Enum\OrderStatus;
use App\Form\OrderAssetType;
use App\Repository\OrderAssetRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/orders')]
final class OrderAssetController extends AbstractController
{
    #[Route('/{id}/assets/upload', name: 'app_order_asset_upload', methods: ['POST'])]
    public function upload(Order $order, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        if (!$this->isCsrfTokenValid('asset-upload'.$order->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $asset = new OrderAsset();
        $asset->setOrder($order);

        $form = $this->createForm(OrderAssetType::class, $asset);
        $form->handleRequest($request);

        if (!$form->isSubmitted()) {
            $this->addFlash('warning', 'No file submitted.');
            return $this->redirect($request->headers->get('referer') ?: $this->generateUrl('app_order_show', ['id'=>$order->getId()]));
        }

        $uploaded = $form->get('file')->getData();
        if (!$uploaded) {
            $this->addFlash('danger', 'Upload failed (file missing or rejected by PHP). Try a smaller file.');
            return $this->redirect($request->headers->get('referer') ?: $this->generateUrl('app_order_show', ['id'=>$order->getId()]));
        }

        if (method_exists($uploaded, 'getError') && $uploaded->getError() !== UPLOAD_ERR_OK) {
            $msg = method_exists($uploaded, 'getErrorMessage') ? $uploaded->getErrorMessage() : 'Upload error.';
            $this->addFlash('danger', $msg);
            return $this->redirect($request->headers->get('referer') ?: $this->generateUrl('app_order_show', ['id'=>$order->getId()]));
        }

        $asset->setFile($uploaded);

        // Set order status to DELIVERED on successful upload
        if ($order->getStatus() !== OrderStatus::DELIVERED) {
            $order->setStatus(OrderStatus::DELIVERED);
        }

        $em->persist($asset);
        $em->flush();

        $this->addFlash('success', 'Thumbnail uploaded.');
        $back = $request->query->get('back') ?: $request->headers->get('referer');
        return $back ? $this->redirect($back) : $this->redirectToRoute('app_order_show', ['id' => $order->getId()]);
    }

    #[Route('/assets/{id}/download', name: 'app_order_asset_download', methods: ['GET'])]
    public function download(OrderAsset $asset): Response
    {
        $order = $asset->getOrder();
        $this->denyAccessUnlessGranted('ORDER_VIEW', $order);

        $path = $this->getParameter('kernel.project_dir').'/public/uploads/order-assets/'.$asset->getFileName();
        $fs = new Filesystem();
        if (!$fs->exists($path)) { throw $this->createNotFoundException(); }

        $response = new BinaryFileResponse($path);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $asset->getFileName());
        if ($asset->getMimeType()) { $response->headers->set('Content-Type', $asset->getMimeType()); }
        return $response;
    }

    #[Route('/assets/{id}/delete', name: 'app_order_asset_delete', methods: ['POST'])]
    public function delete(OrderAsset $asset, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        if (!$this->isCsrfTokenValid('asset-delete'.$asset->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $order = $asset->getOrder();
        $orderId = $order->getId();

        // Count assets for this order before deletion
        $countBefore = (int) $em->createQueryBuilder()
            ->select('COUNT(a.id)')
            ->from(OrderAsset::class, 'a')
            ->andWhere('a.order = :order')
            ->setParameter('order', $order)
            ->getQuery()
            ->getSingleScalarResult();

        // Remove physical file (wherever it was stored)
        $projectDir = $this->getParameter('kernel.project_dir');
        $paths = [
            $projectDir.'/var/uploads/order-assets/'.$asset->getFileName(),
            $projectDir.'/public/uploads/order-assets/'.$asset->getFileName(),
        ];
        $fs = new Filesystem();
        foreach ($paths as $p) {
            if ($p && $fs->exists($p)) { $fs->remove($p); break; }
        }

        // Delete DB row
        $em->createQuery('DELETE FROM App\Entity\OrderAsset a WHERE a.id = :id')
            ->setParameter('id', $asset->getId())
            ->execute();

        // If it was the last asset, set status back to DOING
        if ($countBefore <= 1 && $order->getStatus() !== OrderStatus::DOING) {
            $order->setStatus(OrderStatus::DOING);
            $em->flush();
        }

        $this->addFlash('success', 'Thumbnail removed.');
        return $this->redirect($request->query->get('back') ?: $this->generateUrl('app_order_show', ['id' => $orderId]));
    }
}
