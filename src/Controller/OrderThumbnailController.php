<?php
namespace App\Controller;

use App\Entity\Order;
use App\Entity\Thumbnail;
use App\Enum\OrderStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/orders')]
final class OrderThumbnailController extends AbstractController
{
    #[Route('/{id}/thumbnails/upload', name: 'app_thumbnail_upload', methods: ['POST'])]
    public function upload(Order $order, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        if (!$this->isCsrfTokenValid('thumb-upload'.$order->getId(), (string)$request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $files = $request->files->all('thumb_files');
        if (!$files || !is_array($files)) {
            $this->addFlash('warning', 'No file submitted.');
            return $this->redirect($request->headers->get('referer') ?: $this->generateUrl('app_order_show', ['id'=>$order->getId()]));
        }

        $valid = 0;
        foreach ($files as $f) {
            if (!$f) continue;
            $mime = $f->getMimeType() ?: '';
            if (!in_array($mime, ['image/png','image/jpeg','image/webp'], true)) continue;

            $t = new Thumbnail();
            $t->setOrder($order);
            $t->setFile($f);
            $em->persist($t);
            $valid++;
        }

        if ($valid === 0) {
            $this->addFlash('danger', 'Upload failed (no images).');
            return $this->redirect($request->headers->get('referer') ?: $this->generateUrl('app_order_show', ['id'=>$order->getId()]));
        }

        if (in_array($order->getStatus(), [OrderStatus::ACCEPTED, OrderStatus::DOING, OrderStatus::REVISION], true)) {
            $order->setStatus(OrderStatus::DELIVERED);
            $order->setUpdatedAt(new \DateTimeImmutable());
        }

        $em->flush();
        $this->addFlash('success', 'Final thumbnails uploaded.');
        return $this->redirect($request->query->get('back') ?: $request->headers->get('referer') ?: $this->generateUrl('app_order_show', ['id'=>$order->getId()]));
    }

    #[Route('/thumbnails/{id}/download', name: 'app_thumbnail_download', methods: ['GET'])]
    public function download(Thumbnail $thumb): Response
    {
        $order = $thumb->getOrder();
        $this->denyAccessUnlessGranted('ORDER_VIEW', $order);

        $path = $this->getParameter('kernel.project_dir').'/public/uploads/order-thumbnails/'.$thumb->getFileName();
        $fs = new Filesystem();
        if (!$fs->exists($path)) { throw $this->createNotFoundException(); }

        $r = new BinaryFileResponse($path);
        $r->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $thumb->getFileName());
        if ($thumb->getMimeType()) { $r->headers->set('Content-Type', $thumb->getMimeType()); }
        return $r;
    }

    #[Route('/thumbnails/{id}/delete', name: 'app_thumbnail_delete', methods: ['POST'])]
    public function delete(Thumbnail $thumb, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        if (!$this->isCsrfTokenValid('thumb-delete'.$thumb->getId(), (string)$request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $order = $thumb->getOrder();
        $orderId = $order->getId();

        $fs = new Filesystem();
        $p = $this->getParameter('kernel.project_dir').'/public/uploads/order-thumbnails/'.$thumb->getFileName();
        if ($thumb->getFileName() && $fs->exists($p)) { $fs->remove($p); }

        $em->remove($thumb);
        $em->flush();

        if ($order->getThumbnails()->count() === 0 && in_array($order->getStatus(), [OrderStatus::DELIVERED, OrderStatus::FINISHED, OrderStatus::REVISION], true)) {
            $order->setStatus(OrderStatus::DOING);
            $order->setUpdatedAt(new \DateTimeImmutable());
            $em->flush();
        }

        $this->addFlash('success', 'Final thumbnail removed.');
        return $this->redirect($request->query->get('back') ?: $this->generateUrl('app_order_show', ['id'=>$orderId]));
    }
}
