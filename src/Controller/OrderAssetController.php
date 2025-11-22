<?php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\OrderAsset;
use App\Enum\OrderStatus;
use App\Form\OrderAssetType;
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
        $this->denyAccessUnlessGranted('ORDER_EDIT', $order);

        if (!$this->isCsrfTokenValid('asset-upload'.$order->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $files = $request->files->all('asset_files');
        if (!$files || !is_array($files)) {
            $this->addFlash('warning', 'No file submitted.');
            return $this->redirect(
                $request->headers->get('referer') ?: $this->generateUrl('app_order_show', ['id' => $order->getId()])
            );
        }

        $valid = 0;
        foreach ($files as $f) {
            if (!$f) {
                continue;
            }

            $asset = new OrderAsset();
            $asset->setOrder($order);
            $asset->setFile($f);
            $em->persist($asset);
            $valid++;
        }

        if ($valid === 0) {
            $this->addFlash('danger', 'Upload failed (no files).');
            return $this->redirect(
                $request->headers->get('referer') ?: $this->generateUrl('app_order_show', ['id' => $order->getId()])
            );
        }

        $em->flush();
        $this->addFlash('success', 'Files uploaded.');
        return $this->redirect(
            $request->query->get('back')
                ?: $request->headers->get('referer')
                ?: $this->generateUrl('app_order_show', ['id' => $order->getId()])
        );
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
        $order = $asset->getOrder();

        // Propriétaire (client) OU admin
        $this->denyAccessUnlessGranted('ORDER_EDIT', $order);

        if (!$this->isCsrfTokenValid('asset-delete'.$asset->getId(), (string)$request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $orderId = $order->getId();
        $projectDir = $this->getParameter('kernel.project_dir');
        $paths = [
            $projectDir.'/var/uploads/order-assets/'.$asset->getFileName(),
            $projectDir.'/public/uploads/order-assets/'.$asset->getFileName(),
        ];
        $fs = new \Symfony\Component\Filesystem\Filesystem();
        foreach ($paths as $p) {
            if ($p && $fs->exists($p)) { $fs->remove($p); break; }
        }

        $em->remove($asset);
        $em->flush();

        $this->addFlash('success', 'File removed.');
        return $this->redirect($request->query->get('back') ?: $this->generateUrl('app_order_show', ['id' => $orderId]));
    }

}
